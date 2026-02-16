<?php

namespace App\controllers\api;

use Flight;
use OpenApi\Attributes as OA;

#[
    OA\Schema(
        schema: "PushResultResponse",
        properties: [
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
            new OA\Property(property: "status_changed", type: "boolean"),
            new OA\Property(
                property: "previous_status",
                type: "string",
                nullable: true,
            ),
        ],
    ),
]
class PushController
{
    #[
        OA\Post(
            path: "/api/push/hosts",
            summary: "Host anlegen oder aktualisieren (Upsert)",
            description: "Upsert anhand `address`. Existiert der Host, wird er aktualisiert.",
            tags: ["Push: Hosts"],
            security: [["bearerAuth" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["address"],
                    properties: [
                        new OA\Property(
                            property: "name",
                            type: "string",
                            example: "k8s-node-1",
                        ),
                        new OA\Property(
                            property: "address",
                            type: "string",
                            example: "10.0.1.5",
                        ),
                        new OA\Property(
                            property: "description",
                            type: "string",
                            example: "Worker Node 1",
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
                    response: 200,
                    description: "Host aktualisiert",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Host",
                    ),
                ),
                new OA\Response(
                    response: 201,
                    description: "Host angelegt",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Host",
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
                    response: 401,
                    description: "Nicht authentifiziert",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function upsertHost(): void
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $name = trim($data->name ?? "");
        $address = trim($data->address ?? "");
        $description = trim($data->description ?? "");
        $topic = trim($data->topic ?? "");
        $enabled = (int) ($data->enabled ?? 1) ? 1 : 0;

        if ($address === "") {
            Flight::halt(400, json_encode(["error" => "address is required"]));
            return;
        }
        if ($name === "") {
            $name = $address;
        }

        $existing = $db->fetchRow("SELECT * FROM hosts WHERE address = ?", [
            $address,
        ]);

        if ($existing) {
            $db->runQuery(
                "UPDATE hosts SET name = ?, description = ?, topic = ?, enabled = ?, updated_at = datetime('now') WHERE id = ?",
                [$name, $description, $topic, $enabled, $existing["id"]],
            );
            $host = $db->fetchRow("SELECT * FROM hosts WHERE id = ?", [
                $existing["id"],
            ]);
            Flight::json($host);
        } else {
            $db->runQuery(
                "INSERT INTO hosts (name, address, description, topic, enabled) VALUES (?, ?, ?, ?, ?)",
                [$name, $address, $description, $topic, $enabled],
            );
            $id = $db->lastInsertId();
            $host = $db->fetchRow("SELECT * FROM hosts WHERE id = ?", [$id]);
            Flight::json($host, 201);
        }
    }

    #[
        OA\Delete(
            path: "/api/push/hosts",
            summary: "Host loeschen (by address)",
            tags: ["Push: Hosts"],
            security: [["bearerAuth" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["address"],
                    properties: [
                        new OA\Property(
                            property: "address",
                            type: "string",
                            example: "10.0.1.5",
                        ),
                    ],
                ),
            ),
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
    public static function deleteHost(): void
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $address = trim($data->address ?? "");

        if ($address === "") {
            Flight::halt(400, json_encode(["error" => "address is required"]));
            return;
        }

        $host = $db->fetchRow("SELECT * FROM hosts WHERE address = ?", [
            $address,
        ]);
        if (!$host) {
            Flight::halt(404, json_encode(["error" => "Host not found"]));
            return;
        }

        $db->runQuery("DELETE FROM hosts WHERE id = ?", [$host["id"]]);
        Flight::json(["success" => true]);
    }

    #[
        OA\Post(
            path: "/api/push/checks",
            summary: "Check anlegen oder aktualisieren (Upsert)",
            description: "Upsert anhand `host_address` + `type`. Existiert der Check, wird er aktualisiert.",
            tags: ["Push: Checks"],
            security: [["bearerAuth" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["host_address", "type"],
                    properties: [
                        new OA\Property(
                            property: "host_address",
                            type: "string",
                            example: "10.0.1.5",
                        ),
                        new OA\Property(
                            property: "type",
                            type: "string",
                            example: "memory",
                        ),
                        new OA\Property(
                            property: "config",
                            type: "object",
                            example: '{"warning_pct": 80, "critical_pct": 95}',
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
                    response: 200,
                    description: "Check aktualisiert",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Check",
                    ),
                ),
                new OA\Response(
                    response: 201,
                    description: "Check angelegt",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Check",
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
    public static function upsertCheck(): void
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $hostAddress = trim($data->host_address ?? "");
        $type = trim($data->type ?? "");
        $config = $data->config ?? "{}";
        $interval = max(30, (int) ($data->interval_seconds ?? 300));
        $enabled = (int) ($data->enabled ?? 1) ? 1 : 0;

        if ($hostAddress === "" || $type === "") {
            Flight::halt(
                400,
                json_encode(["error" => "host_address and type are required"]),
            );
            return;
        }

        $host = $db->fetchRow("SELECT * FROM hosts WHERE address = ?", [
            $hostAddress,
        ]);
        if (!$host) {
            Flight::halt(404, json_encode(["error" => "Host not found"]));
            return;
        }

        if (is_array($config) || is_object($config)) {
            $config = json_encode($config);
        }

        $existing = $db->fetchRow(
            "SELECT * FROM checks WHERE host_id = ? AND type = ? AND config = ?",
            [$host["id"], $type, $config],
        );

        if (!$existing) {
            // Fallback: match by host_id + type only (for checks without config)
            $existing = $db->fetchRow(
                "SELECT * FROM checks WHERE host_id = ? AND type = ? AND (config IS NULL OR config = '{}' OR config = '')",
                [$host["id"], $type],
            );
        }

        if ($existing) {
            $db->runQuery(
                "UPDATE checks SET config = ?, interval_seconds = ?, enabled = ? WHERE id = ?",
                [$config, $interval, $enabled, $existing["id"]],
            );
            $check = $db->fetchRow("SELECT * FROM checks WHERE id = ?", [
                $existing["id"],
            ]);
            Flight::json($check);
        } else {
            $db->runQuery(
                "INSERT INTO checks (host_id, type, config, interval_seconds, enabled) VALUES (?, ?, ?, ?, ?)",
                [$host["id"], $type, $config, $interval, $enabled],
            );
            $id = $db->lastInsertId();
            $check = $db->fetchRow("SELECT * FROM checks WHERE id = ?", [$id]);
            Flight::json($check, 201);
        }
    }

    #[
        OA\Delete(
            path: "/api/push/checks",
            summary: "Check loeschen (by host_address + type)",
            tags: ["Push: Checks"],
            security: [["bearerAuth" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["host_address", "type"],
                    properties: [
                        new OA\Property(
                            property: "host_address",
                            type: "string",
                            example: "10.0.1.5",
                        ),
                        new OA\Property(
                            property: "type",
                            type: "string",
                            example: "memory",
                        ),
                    ],
                ),
            ),
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
                    description: "Host oder Check nicht gefunden",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function deleteCheck(): void
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $hostAddress = trim($data->host_address ?? "");
        $type = trim($data->type ?? "");

        if ($hostAddress === "" || $type === "") {
            Flight::halt(
                400,
                json_encode(["error" => "host_address and type are required"]),
            );
            return;
        }

        $host = $db->fetchRow("SELECT * FROM hosts WHERE address = ?", [
            $hostAddress,
        ]);
        if (!$host) {
            Flight::halt(404, json_encode(["error" => "Host not found"]));
            return;
        }

        $check = $db->fetchRow(
            "SELECT * FROM checks WHERE host_id = ? AND type = ?",
            [$host["id"], $type],
        );
        if (!$check) {
            Flight::halt(404, json_encode(["error" => "Check not found"]));
            return;
        }

        $db->runQuery("DELETE FROM checks WHERE id = ?", [$check["id"]]);
        Flight::json(["success" => true]);
    }

    #[
        OA\Post(
            path: "/api/push/results",
            summary: "Einzelnes Ergebnis einliefern",
            description: "Speichert ein Messergebnis fuer einen bestehenden Check. Erkennt Statusaenderungen.",
            tags: ["Push: Results"],
            security: [["bearerAuth" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["host_address", "check_type", "status"],
                    properties: [
                        new OA\Property(
                            property: "host_address",
                            type: "string",
                            example: "10.0.1.5",
                        ),
                        new OA\Property(
                            property: "check_type",
                            type: "string",
                            example: "memory",
                        ),
                        new OA\Property(
                            property: "status",
                            type: "string",
                            enum: ["ok", "warning", "critical", "unknown"],
                        ),
                        new OA\Property(
                            property: "value",
                            type: "number",
                            format: "float",
                            example: 62.5,
                        ),
                        new OA\Property(
                            property: "message",
                            type: "string",
                            example: "62.5% used, 3800 MB available",
                        ),
                    ],
                ),
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Ergebnis gespeichert",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/PushResultResponse",
                    ),
                ),
                new OA\Response(
                    response: 400,
                    description: "Ungueltige Daten oder Host/Check nicht gefunden",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function pushResult(): void
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $result = self::storeResult($db, [
            "host_address" => $data->host_address ?? "",
            "check_type" => $data->check_type ?? "",
            "status" => $data->status ?? "",
            "value" => $data->value ?? null,
            "message" => $data->message ?? "",
        ]);

        Flight::json($result, isset($result["error"]) ? 400 : 200);
    }

    #[
        OA\Post(
            path: "/api/push/bulk",
            summary: "Mehrere Ergebnisse auf einmal einliefern",
            description: "Verarbeitet ein Array von Messergebnissen in einem Request.",
            tags: ["Push: Results"],
            security: [["bearerAuth" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["results"],
                    properties: [
                        new OA\Property(
                            property: "results",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: "host_address",
                                        type: "string",
                                    ),
                                    new OA\Property(
                                        property: "check_type",
                                        type: "string",
                                    ),
                                    new OA\Property(
                                        property: "status",
                                        type: "string",
                                        enum: [
                                            "ok",
                                            "warning",
                                            "critical",
                                            "unknown",
                                        ],
                                    ),
                                    new OA\Property(
                                        property: "value",
                                        type: "number",
                                        format: "float",
                                    ),
                                    new OA\Property(
                                        property: "message",
                                        type: "string",
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Bulk-Ergebnis",
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: "processed",
                                type: "integer",
                            ),
                            new OA\Property(
                                property: "results",
                                type: "array",
                                items: new OA\Items(
                                    ref: "#/components/schemas/PushResultResponse",
                                ),
                            ),
                        ],
                    ),
                ),
                new OA\Response(
                    response: 400,
                    description: "results Array fehlt",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function pushBulk(): void
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $items = $data->results ?? [];

        if (!is_array($items) || count($items) === 0) {
            Flight::halt(
                400,
                json_encode(["error" => "results array is required"]),
            );
            return;
        }

        $results = [];
        foreach ($items as $item) {
            $item = (array) $item;
            $results[] = self::storeResult($db, [
                "host_address" => $item["host_address"] ?? "",
                "check_type" => $item["check_type"] ?? "",
                "status" => $item["status"] ?? "",
                "value" => $item["value"] ?? null,
                "message" => $item["message"] ?? "",
            ]);
        }

        Flight::json(["processed" => count($results), "results" => $results]);
    }

    private static function storeResult($db, array $input): array
    {
        $hostAddress = trim($input["host_address"] ?? "");
        $checkType = trim($input["check_type"] ?? "");
        $status = trim($input["status"] ?? "");
        $value = $input["value"];
        $message = trim($input["message"] ?? "");

        if ($hostAddress === "" || $checkType === "" || $status === "") {
            return [
                "error" => "host_address, check_type, and status are required",
            ];
        }

        $validStatuses = ["ok", "warning", "critical", "unknown"];
        if (!in_array($status, $validStatuses, true)) {
            return [
                "error" =>
                    "Invalid status. Valid: " . implode(", ", $validStatuses),
            ];
        }

        $host = $db->fetchRow("SELECT id FROM hosts WHERE address = ?", [
            $hostAddress,
        ]);
        if (!$host) {
            return ["error" => "Host not found"];
        }

        $check = $db->fetchRow(
            "SELECT id FROM checks WHERE host_id = ? AND type = ?",
            [$host["id"], $checkType],
        );
        if (!$check) {
            return ["error" => "Check not found"];
        }

        // Get previous status for change detection
        $prevResult = $db->fetchRow(
            "SELECT status FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 1",
            [$check["id"]],
        );
        $prevStatus = $prevResult["status"] ?? null;

        $db->runQuery(
            "INSERT INTO check_results (check_id, status, value, message) VALUES (?, ?, ?, ?)",
            [$check["id"], $status, $value, $message],
        );

        return [
            "check_id" => $check["id"],
            "status" => $status,
            "value" => $value,
            "status_changed" => $prevStatus !== null && $prevStatus !== $status,
            "previous_status" => $prevStatus,
        ];
    }
}
