<?php

namespace App\controllers\api;

use Flight;
use OpenApi\Attributes as OA;

#[
    OA\Schema(
        schema: "Host",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "name", type: "string"),
            new OA\Property(property: "address", type: "string"),
            new OA\Property(property: "description", type: "string"),
            new OA\Property(property: "topic", type: "string"),
            new OA\Property(property: "enabled", type: "integer", enum: [0, 1]),
            new OA\Property(
                property: "created_at",
                type: "string",
                format: "date-time",
            ),
            new OA\Property(
                property: "updated_at",
                type: "string",
                format: "date-time",
            ),
        ],
    ),
]
class HostController
{
    #[
        OA\Get(
            path: "/api/hosts",
            summary: "Alle Hosts auflisten",
            tags: ["Hosts"],
            security: [["sessionAuth" => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Liste aller Hosts",
                    content: new OA\JsonContent(
                        type: "array",
                        items: new OA\Items(ref: "#/components/schemas/Host"),
                    ),
                ),
            ],
        ),
    ]
    public static function list(): void
    {
        $db = Flight::db();
        $hosts = $db->fetchAll("SELECT * FROM hosts ORDER BY name ASC");

        foreach ($hosts as &$host) {
            $worst = $db->fetchRow(
                "
                SELECT COALESCE(cr.status, 'unknown') AS status FROM checks c
                LEFT JOIN check_results cr ON cr.check_id = c.id
                    AND cr.id = (SELECT id FROM check_results WHERE check_id = c.id ORDER BY checked_at DESC LIMIT 1)
                WHERE c.host_id = ? AND c.enabled = 1
                ORDER BY CASE COALESCE(cr.status, 'unknown')
                    WHEN 'critical' THEN 0
                    WHEN 'warning' THEN 1
                    WHEN 'unknown' THEN 2
                    WHEN 'ok' THEN 3
                END ASC
                LIMIT 1
            ",
                [$host["id"]],
            );
            $host["status"] = $worst["status"] ?? "unknown";

            $checkCount = $db->fetchRow(
                "SELECT COUNT(*) as cnt FROM checks WHERE host_id = ? AND enabled = 1",
                [$host["id"]],
            );
            $host["check_count"] = (int) ($checkCount["cnt"] ?? 0);
        }

        Flight::json($hosts);
    }

    #[
        OA\Get(
            path: "/api/hosts/{id}",
            summary: "Host-Detail mit Checks",
            tags: ["Hosts"],
            security: [["sessionAuth" => []]],
            parameters: [
                new OA\Parameter(
                    name: "id",
                    in: "path",
                    required: true,
                    schema: new OA\Schema(type: "integer"),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Host mit Checks",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Host",
                    ),
                ),
                new OA\Response(
                    response: 404,
                    description: "Host nicht gefunden",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function get(int $id): void
    {
        $db = Flight::db();
        $host = $db->fetchRow("SELECT * FROM hosts WHERE id = ?", [$id]);
        if (!$host) {
            Flight::halt(404, json_encode(["error" => "Host not found"]));
            return;
        }

        // Add labels
        $labelRows = $db->fetchAll(
            "SELECT key, value FROM labels WHERE entity_type = 'host' AND entity_id = ?",
            [$id],
        );
        $labels = new \stdClass();
        foreach ($labelRows as $lr) {
            $labels->{$lr["key"]} = $lr["value"];
        }
        $host["labels"] = $labels;

        $checks = $db->fetchAll(
            "SELECT * FROM checks WHERE host_id = ? ORDER BY type ASC",
            [$id],
        );
        foreach ($checks as &$check) {
            $lastResult = $db->fetchRow(
                "SELECT * FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 1",
                [$check["id"]],
            );
            if ($lastResult && isset($lastResult["metrics"]) && $lastResult["metrics"]) {
                $lastResult["metrics"] = json_decode($lastResult["metrics"], true);
            }
            $check["last_result"] = $lastResult;
        }
        unset($check);

        // Uptime 24h: worst status per hour per check (batch)
        $checkIds = array_column($checks, 'id');
        if (!empty($checkIds)) {
            $placeholders = implode(',', array_fill(0, count($checkIds), '?'));
            $uptimeRows = $db->fetchAll(
                "SELECT check_id,
                        strftime('%Y-%m-%d %H:00:00', checked_at) AS hour_bucket,
                        CASE
                          WHEN SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) > 0 THEN 'critical'
                          WHEN SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) > 0 THEN 'warning'
                          WHEN SUM(CASE WHEN status = 'unknown' THEN 1 ELSE 0 END) > 0 THEN 'unknown'
                          ELSE 'ok'
                        END AS worst_status
                 FROM check_results
                 WHERE check_id IN ($placeholders)
                   AND checked_at >= datetime('now', '-24 hours')
                 GROUP BY check_id, hour_bucket
                 ORDER BY check_id, hour_bucket",
                $checkIds
            );

            // Build lookup: check_id -> hour_bucket -> status
            $uptimeByCheck = [];
            foreach ($uptimeRows as $ur) {
                $uptimeByCheck[$ur['check_id']][$ur['hour_bucket']] = $ur['worst_status'];
            }

            // Generate 24-slot arrays
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $hours = [];
            for ($i = 23; $i >= 0; $i--) {
                $h = (clone $now)->modify("-{$i} hours");
                $hours[] = $h->format('Y-m-d H:00:00');
            }

            // Build hour labels (UTC, HH:00 format) for frontend display
            $hourLabels = [];
            foreach ($hours as $hour) {
                $hourLabels[] = substr($hour, 11, 5); // "HH:00"
            }

            foreach ($checks as &$check) {
                $checkUptime = $uptimeByCheck[$check['id']] ?? [];
                $uptime24h = [];
                foreach ($hours as $hour) {
                    $uptime24h[] = $checkUptime[$hour] ?? 'unknown';
                }
                $check['uptime_24h'] = $uptime24h;
                $check['uptime_hours'] = $hourLabels;
            }
            unset($check);

            // Sparkline data: last 20 results per check
            $sparkRows = $db->fetchAll(
                "SELECT check_id, value, status FROM (
                    SELECT check_id, value, status,
                           ROW_NUMBER() OVER (PARTITION BY check_id ORDER BY checked_at DESC) AS rn
                    FROM check_results
                    WHERE check_id IN ($placeholders)
                      AND checked_at >= datetime('now', '-2 hours')
                ) WHERE rn <= 20
                ORDER BY check_id, rn DESC",
                $checkIds
            );
            $sparkByCheck = [];
            foreach ($sparkRows as $sr) {
                $sparkByCheck[$sr['check_id']][] = [
                    'value' => $sr['value'],
                    'status' => $sr['status'],
                ];
            }
            foreach ($checks as &$check) {
                $check['recent_values'] = $sparkByCheck[$check['id']] ?? [];
            }
            unset($check);
        }

        $host["checks"] = $checks;
        Flight::json($host);
    }

    #[
        OA\Post(
            path: "/api/hosts",
            summary: "Host anlegen",
            tags: ["Hosts"],
            security: [["sessionAuth" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["name", "address"],
                    properties: [
                        new OA\Property(
                            property: "name",
                            type: "string",
                            example: "Webserver",
                        ),
                        new OA\Property(
                            property: "address",
                            type: "string",
                            example: "example.com",
                        ),
                        new OA\Property(
                            property: "description",
                            type: "string",
                        ),
                        new OA\Property(
                            property: "topic",
                            type: "string",
                            example: "production/default/deployments",
                        ),
                        new OA\Property(
                            property: "enabled",
                            type: "integer",
                            enum: [0, 1],
                            default: 1,
                        ),
                    ],
                ),
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: "Host angelegt",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Host",
                    ),
                ),
            ],
        ),
    ]
    public static function create(): void
    {
        $data = Flight::request()->data;
        $name = trim($data->name ?? "");
        $address = trim($data->address ?? "");
        $description = trim($data->description ?? "");
        $topic = trim($data->topic ?? "");
        $enabled = (int) ($data->enabled ?? 1) ? 1 : 0;

        if ($name === "" || $address === "") {
            Flight::halt(
                400,
                json_encode(["error" => "Name and address are required"]),
            );
            return;
        }

        $db = Flight::db();
        $db->runQuery(
            "INSERT INTO hosts (name, address, description, topic, enabled) VALUES (?, ?, ?, ?, ?)",
            [$name, $address, $description, $topic, $enabled],
        );

        $id = $db->lastInsertId();
        $host = $db->fetchRow("SELECT * FROM hosts WHERE id = ?", [$id]);
        Flight::json($host, 201);
    }

    #[
        OA\Put(
            path: "/api/hosts/{id}",
            summary: "Host bearbeiten",
            tags: ["Hosts"],
            security: [["sessionAuth" => []]],
            parameters: [
                new OA\Parameter(
                    name: "id",
                    in: "path",
                    required: true,
                    schema: new OA\Schema(type: "integer"),
                ),
            ],
            requestBody: new OA\RequestBody(
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "address", type: "string"),
                        new OA\Property(
                            property: "description",
                            type: "string",
                        ),
                        new OA\Property(property: "topic", type: "string"),
                        new OA\Property(
                            property: "enabled",
                            type: "integer",
                            enum: [0, 1],
                        ),
                    ],
                ),
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Host aktualisiert",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Host",
                    ),
                ),
                new OA\Response(
                    response: 404,
                    description: "Host nicht gefunden",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function update(int $id): void
    {
        $db = Flight::db();
        $host = $db->fetchRow("SELECT * FROM hosts WHERE id = ?", [$id]);
        if (!$host) {
            Flight::halt(404, json_encode(["error" => "Host not found"]));
            return;
        }

        $data = Flight::request()->data;
        $name = trim($data->name ?? $host["name"]);
        $address = trim($data->address ?? $host["address"]);
        $description = trim($data->description ?? $host["description"]);
        $topic = trim($data->topic ?? $host["topic"]);
        $enabled = (int) ($data->enabled ?? $host["enabled"]) ? 1 : 0;

        $db->runQuery(
            "UPDATE hosts SET name = ?, address = ?, description = ?, topic = ?, enabled = ?, updated_at = datetime('now') WHERE id = ?",
            [$name, $address, $description, $topic, $enabled, $id],
        );

        $host = $db->fetchRow("SELECT * FROM hosts WHERE id = ?", [$id]);
        Flight::json($host);
    }

    #[
        OA\Delete(
            path: "/api/hosts/{id}",
            summary: "Host loeschen",
            description: "Loescht Host und alle zugehoerigen Checks und Ergebnisse.",
            tags: ["Hosts"],
            security: [["sessionAuth" => []]],
            parameters: [
                new OA\Parameter(
                    name: "id",
                    in: "path",
                    required: true,
                    schema: new OA\Schema(type: "integer"),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Geloescht",
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: "success",
                                type: "boolean",
                            ),
                        ],
                    ),
                ),
                new OA\Response(
                    response: 404,
                    description: "Host nicht gefunden",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function delete(int $id): void
    {
        $db = Flight::db();
        $host = $db->fetchRow("SELECT * FROM hosts WHERE id = ?", [$id]);
        if (!$host) {
            Flight::halt(404, json_encode(["error" => "Host not found"]));
            return;
        }

        $db->runQuery("DELETE FROM hosts WHERE id = ?", [$id]);
        Flight::json(["success" => true]);
    }
}
