<?php

namespace App\controllers\api;

use Flight;
use OpenApi\Attributes as OA;

#[
    OA\Info(
        version: "1.0",
        title: "TinyMon API",
        description: "Minimalistisches Server-Monitoring.

## Authentifizierung

| API | Pfad | Methode |
|-----|------|---------|
| **Session API** | `/api/*` | Login via `/backend/login` (Cookie) |
| **Push API** | `/api/push/*` | `Authorization: Bearer <PUSH_API_KEY>` |
",
    ),
]
#[
    OA\SecurityScheme(
        securityScheme: "sessionAuth",
        type: "apiKey",
        in: "cookie",
        name: "PHPSESSID",
        description: "Session-Cookie nach Login",
    ),
]
#[
    OA\SecurityScheme(
        securityScheme: "bearerAuth",
        type: "http",
        scheme: "bearer",
        description: "PUSH_API_KEY aus .env",
    ),
]
#[OA\Tag(name: "Dashboard", description: "Aggregierte Statusuebersicht")]
#[OA\Tag(name: "Hosts", description: "Host-Verwaltung (Session-Auth)")]
#[
    OA\Tag(
        name: "Checks",
        description: "Check-Verwaltung und -Ausfuehrung (Session-Auth)",
    ),
]
#[
    OA\Tag(
        name: "Push: Hosts",
        description: "Host-Verwaltung via Push-API (Bearer-Auth)",
    ),
]
#[
    OA\Tag(
        name: "Push: Checks",
        description: "Check-Verwaltung via Push-API (Bearer-Auth)",
    ),
]
#[
    OA\Tag(
        name: "Push: Results",
        description: "Ergebnisse einliefern via Push-API (Bearer-Auth)",
    ),
]
#[
    OA\Tag(
        name: "Notifications",
        description: "Browser Push-Benachrichtigungen (Session-Auth)",
    ),
]
#[
    OA\Schema(
        schema: "Error",
        properties: [new OA\Property(property: "error", type: "string")],
    ),
]
class DashboardController
{
    #[
        OA\Get(
            path: "/api/dashboard",
            summary: "Aggregierte Statusuebersicht",
            description: "Alle Hosts mit jeweils schlimmstem Check-Status und Summary-Zaehler.",
            tags: ["Dashboard"],
            security: [["sessionAuth" => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Dashboard-Daten",
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: "summary",
                                type: "object",
                                properties: [
                                    new OA\Property(
                                        property: "ok",
                                        type: "integer",
                                    ),
                                    new OA\Property(
                                        property: "warning",
                                        type: "integer",
                                    ),
                                    new OA\Property(
                                        property: "critical",
                                        type: "integer",
                                    ),
                                    new OA\Property(
                                        property: "unknown",
                                        type: "integer",
                                    ),
                                ],
                            ),
                            new OA\Property(
                                property: "hosts",
                                type: "array",
                                items: new OA\Items(
                                    ref: "#/components/schemas/Host",
                                ),
                            ),
                        ],
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
    public static function index(): void
    {
        $db = Flight::db();

        $hosts = $db->fetchAll(
            "SELECT * FROM hosts WHERE enabled = 1 ORDER BY topic ASC, name ASC",
        );

        // Single query: all enabled checks with their latest result
        $rows = $db->fetchAll(
            "SELECT c.*,
                    cr.status AS result_status, cr.value AS result_value,
                    cr.message AS result_message, cr.checked_at AS result_checked_at
             FROM checks c
             LEFT JOIN check_results cr ON cr.id = (
                 SELECT id FROM check_results WHERE check_id = c.id ORDER BY checked_at DESC LIMIT 1
             )
             WHERE c.enabled = 1
             ORDER BY c.host_id, c.id",
        );

        // Sparkline data: last 20 results per check (batch via window function)
        $sparkRows = $db->fetchAll(
            "SELECT check_id, value, status FROM (
                SELECT check_id, value, status,
                       ROW_NUMBER() OVER (PARTITION BY check_id ORDER BY checked_at DESC) AS rn
                FROM check_results
                WHERE check_id IN (SELECT id FROM checks WHERE enabled = 1)
                  AND checked_at >= datetime('now', '-2 hours')
            ) WHERE rn <= 20
            ORDER BY check_id, rn DESC",
        );

        $sparkByCheck = [];
        foreach ($sparkRows as $sr) {
            $sparkByCheck[$sr['check_id']][] = [
                'value' => $sr['value'],
                'status' => $sr['status'],
            ];
        }

        // Group checks by host_id
        $checksByHost = [];
        foreach ($rows as $row) {
            $hostId = $row["host_id"];
            $lastResult = null;
            if ($row["result_status"] !== null) {
                $lastResult = [
                    "status" => $row["result_status"],
                    "value" => $row["result_value"],
                    "message" => $row["result_message"],
                    "checked_at" => $row["result_checked_at"],
                ];
            }
            unset(
                $row["result_status"],
                $row["result_value"],
                $row["result_message"],
                $row["result_checked_at"],
            );
            $row["last_result"] = $lastResult;
            $row["recent_values"] = $sparkByCheck[$row["id"]] ?? [];
            $checksByHost[$hostId][] = $row;
        }

        $summary = ["ok" => 0, "warning" => 0, "critical" => 0, "unknown" => 0];
        $prio = [
            "ok" => 0,
            "unknown" => 1,
            "warning" => 2,
            "critical" => 3,
        ];

        foreach ($hosts as &$host) {
            $hostChecks = $checksByHost[$host["id"]] ?? [];
            $worstPrio = -1;
            $hostStatus = "unknown";

            foreach ($hostChecks as $check) {
                $s = $check["last_result"]["status"] ?? "unknown";
                $summary[$s] = ($summary[$s] ?? 0) + 1;
                $p = $prio[$s] ?? 1;
                if ($p > $worstPrio) {
                    $worstPrio = $p;
                    $hostStatus = $s;
                }
            }

            $host["status"] = $hostStatus;
            $host["checks"] = $hostChecks;
        }

        $runnerLastRun = $db->fetchRow(
            "SELECT value FROM settings WHERE key = 'runner_last_run'",
        );

        Flight::json([
            "summary" => $summary,
            "hosts" => $hosts,
            "runner_last_run" => $runnerLastRun
                ? $runnerLastRun["value"]
                : null,
        ]);
    }
}
