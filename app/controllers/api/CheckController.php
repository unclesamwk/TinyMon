<?php

namespace App\controllers\api;

use App\services\CheckRunner;
use Flight;
use OpenApi\Attributes as OA;

#[
    OA\Schema(
        schema: "Check",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "host_id", type: "integer"),
            new OA\Property(
                property: "type",
                type: "string",
                enum: [
                    "ping",
                    "http",
                    "port",
                    "certificate",
                    "content",
                    "content_hash",
                    "disk",
                    "load",
                    "memory",
                ],
            ),
            new OA\Property(
                property: "config",
                type: "string",
                description: "JSON-kodierte Konfiguration (Thresholds, Port, URL etc.)",
            ),
            new OA\Property(
                property: "interval_seconds",
                type: "integer",
                default: 300,
            ),
            new OA\Property(property: "enabled", type: "integer", enum: [0, 1]),
            new OA\Property(
                property: "created_at",
                type: "string",
                format: "date-time",
            ),
            new OA\Property(
                property: "last_result",
                ref: "#/components/schemas/CheckResult",
                nullable: true,
            ),
        ],
    ),
]
#[
    OA\Schema(
        schema: "CheckResult",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "check_id", type: "integer"),
            new OA\Property(
                property: "status",
                type: "string",
                enum: ["ok", "warning", "critical", "unknown"],
            ),
            new OA\Property(
                property: "value",
                type: "number",
                format: "float",
                nullable: true,
            ),
            new OA\Property(property: "message", type: "string"),
            new OA\Property(
                property: "checked_at",
                type: "string",
                format: "date-time",
            ),
        ],
    ),
]
class CheckController
{
    #[
        OA\Get(
            path: "/api/hosts/{hostId}/checks",
            summary: "Checks eines Hosts auflisten",
            tags: ["Checks"],
            security: [["sessionAuth" => []]],
            parameters: [
                new OA\Parameter(
                    name: "hostId",
                    in: "path",
                    required: true,
                    schema: new OA\Schema(type: "integer"),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Liste der Checks",
                    content: new OA\JsonContent(
                        type: "array",
                        items: new OA\Items(ref: "#/components/schemas/Check"),
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
    public static function listForHost(int $hostId): void
    {
        $db = Flight::db();
        $host = $db->fetchRow("SELECT * FROM hosts WHERE id = ?", [$hostId]);
        if (!$host) {
            Flight::halt(404, json_encode(["error" => "Host not found"]));
            return;
        }

        $checks = $db->fetchAll(
            "SELECT * FROM checks WHERE host_id = ? ORDER BY type ASC",
            [$hostId],
        );
        foreach ($checks as &$check) {
            $lastResult = $db->fetchRow(
                "SELECT * FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 1",
                [$check["id"]],
            );
            $check["last_result"] = $lastResult;
        }

        Flight::json($checks);
    }

    #[
        OA\Post(
            path: "/api/hosts/{hostId}/checks",
            summary: "Check anlegen",
            tags: ["Checks"],
            security: [["sessionAuth" => []]],
            parameters: [
                new OA\Parameter(
                    name: "hostId",
                    in: "path",
                    required: true,
                    schema: new OA\Schema(type: "integer"),
                ),
            ],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["type"],
                    properties: [
                        new OA\Property(
                            property: "type",
                            type: "string",
                            enum: [
                                "ping",
                                "http",
                                "port",
                                "certificate",
                                "content",
                                "content_hash",
                                "disk",
                                "load",
                                "memory",
                            ],
                        ),
                        new OA\Property(
                            property: "config",
                            type: "object",
                            example: '{"warning_ms": 100, "critical_ms": 500}',
                        ),
                        new OA\Property(
                            property: "interval_seconds",
                            type: "integer",
                            default: 300,
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
                    description: "Check angelegt",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Check",
                    ),
                ),
                new OA\Response(
                    response: 400,
                    description: "Ungueltige Daten",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
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
    public static function create(int $hostId): void
    {
        $db = Flight::db();
        $host = $db->fetchRow("SELECT * FROM hosts WHERE id = ?", [$hostId]);
        if (!$host) {
            Flight::halt(404, json_encode(["error" => "Host not found"]));
            return;
        }

        $data = Flight::request()->data;
        $type = trim($data->type ?? "");
        $config = $data->config ?? "{}";
        $interval = (int) ($data->interval_seconds ?? 300);
        $enabled = (int) ($data->enabled ?? 1);

        $validTypes = [
            "ping",
            "http",
            "port",
            "certificate",
            "content",
            "content_hash",
            "disk",
            "load",
            "memory",
        ];
        if (!in_array($type, $validTypes, true)) {
            Flight::halt(
                400,
                json_encode([
                    "error" =>
                        "Invalid check type. Valid: " .
                        implode(", ", $validTypes),
                ]),
            );
            return;
        }

        if (is_array($config) || is_object($config)) {
            $config = json_encode($config);
        }

        $db->runQuery(
            "INSERT INTO checks (host_id, type, config, interval_seconds, enabled) VALUES (?, ?, ?, ?, ?)",
            [$hostId, $type, $config, $interval, $enabled],
        );

        $id = $db->lastInsertId();
        $check = $db->fetchRow("SELECT * FROM checks WHERE id = ?", [$id]);
        Flight::json($check, 201);
    }

    #[
        OA\Put(
            path: "/api/checks/{id}",
            summary: "Check bearbeiten",
            tags: ["Checks"],
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
                        new OA\Property(property: "type", type: "string"),
                        new OA\Property(property: "config", type: "object"),
                        new OA\Property(
                            property: "interval_seconds",
                            type: "integer",
                        ),
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
                    description: "Check aktualisiert",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Check",
                    ),
                ),
                new OA\Response(
                    response: 404,
                    description: "Check nicht gefunden",
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
        $check = $db->fetchRow("SELECT * FROM checks WHERE id = ?", [$id]);
        if (!$check) {
            Flight::halt(404, json_encode(["error" => "Check not found"]));
            return;
        }

        $data = Flight::request()->data;
        $type = trim($data->type ?? $check["type"]);
        $config = $data->config ?? $check["config"];
        $interval =
            (int) ($data->interval_seconds ?? $check["interval_seconds"]);
        $enabled = (int) ($data->enabled ?? $check["enabled"]);

        if (is_array($config) || is_object($config)) {
            $config = json_encode($config);
        }

        $db->runQuery(
            "UPDATE checks SET type = ?, config = ?, interval_seconds = ?, enabled = ? WHERE id = ?",
            [$type, $config, $interval, $enabled, $id],
        );

        $check = $db->fetchRow("SELECT * FROM checks WHERE id = ?", [$id]);
        Flight::json($check);
    }

    #[
        OA\Delete(
            path: "/api/checks/{id}",
            summary: "Check loeschen",
            tags: ["Checks"],
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
                    description: "Check nicht gefunden",
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
        $check = $db->fetchRow("SELECT * FROM checks WHERE id = ?", [$id]);
        if (!$check) {
            Flight::halt(404, json_encode(["error" => "Check not found"]));
            return;
        }

        $db->runQuery("DELETE FROM checks WHERE id = ?", [$id]);
        Flight::json(["success" => true]);
    }

    #[
        OA\Post(
            path: "/api/checks/{id}/run",
            summary: "Check sofort ausfuehren",
            description: "Fuehrt den Check sofort aus und liefert das Ergebnis zurueck.",
            tags: ["Checks"],
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
                    description: "Check-Ergebnis",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/CheckResult",
                    ),
                ),
                new OA\Response(
                    response: 404,
                    description: "Check nicht gefunden",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function run(int $id): void
    {
        $db = Flight::db();
        $check = $db->fetchRow(
            "SELECT c.*, h.address FROM checks c JOIN hosts h ON h.id = c.host_id WHERE c.id = ?",
            [$id],
        );
        if (!$check) {
            Flight::halt(404, json_encode(["error" => "Check not found"]));
            return;
        }

        $runner = new CheckRunner($db);
        $result = $runner->executeCheck($check);
        Flight::json($result);
    }

    #[
        OA\Get(
            path: "/api/checks/{id}/results",
            summary: "Ergebnis-Historie abrufen",
            description: "Letzte Ergebnisse eines Checks (Standard: 100, max: 1000).",
            tags: ["Checks"],
            security: [["sessionAuth" => []]],
            parameters: [
                new OA\Parameter(
                    name: "id",
                    in: "path",
                    required: true,
                    schema: new OA\Schema(type: "integer"),
                ),
                new OA\Parameter(
                    name: "limit",
                    in: "query",
                    required: false,
                    schema: new OA\Schema(
                        type: "integer",
                        default: 100,
                        maximum: 1000,
                    ),
                ),
                new OA\Parameter(
                    name: "since",
                    in: "query",
                    required: false,
                    description: "ISO 8601 Zeitstempel. Wenn gesetzt, werden Ergebnisse ab diesem Zeitpunkt chronologisch zurueckgegeben.",
                    schema: new OA\Schema(type: "string", format: "date-time"),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Liste der Ergebnisse",
                    content: new OA\JsonContent(
                        type: "array",
                        items: new OA\Items(
                            ref: "#/components/schemas/CheckResult",
                        ),
                    ),
                ),
                new OA\Response(
                    response: 404,
                    description: "Check nicht gefunden",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function results(int $id): void
    {
        $db = Flight::db();
        $check = $db->fetchRow("SELECT * FROM checks WHERE id = ?", [$id]);
        if (!$check) {
            Flight::halt(404, json_encode(["error" => "Check not found"]));
            return;
        }

        $since = Flight::request()->query->since ?? null;

        if ($since) {
            $results = $db->fetchAll(
                "SELECT * FROM check_results WHERE check_id = ? AND checked_at >= ? ORDER BY checked_at ASC",
                [$id, $since],
            );
        } else {
            $limit = (int) (Flight::request()->query->limit ?? 100);
            $limit = min($limit, 1000);
            $results = $db->fetchAll(
                "SELECT * FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT ?",
                [$id, $limit],
            );
        }

        Flight::json($results);
    }
}
