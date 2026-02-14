<?php

namespace App\controllers\api;

use Flight;
use OpenApi\Attributes as OA;

#[
    OA\Info(
        version: "1.0",
        title: "MiniMon API",
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
            "SELECT * FROM hosts WHERE enabled = 1 ORDER BY name ASC",
        );

        $summary = ["ok" => 0, "warning" => 0, "critical" => 0, "unknown" => 0];

        foreach ($hosts as &$host) {
            $checks = $db->fetchAll(
                "SELECT * FROM checks WHERE host_id = ? AND enabled = 1",
                [$host["id"]],
            );

            $hostStatus = "unknown";
            $hostChecks = [];

            foreach ($checks as $check) {
                $lastResult = $db->fetchRow(
                    "SELECT * FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 1",
                    [$check["id"]],
                );
                $check["last_result"] = $lastResult;
                $hostChecks[] = $check;

                $s = $lastResult["status"] ?? "unknown";
                if (
                    $s === "critical" ||
                    ($hostStatus !== "critical" && $s === "warning") ||
                    ($hostStatus === "unknown" && $s === "ok")
                ) {
                    $hostStatus = $s;
                }
            }

            if (empty($checks)) {
                $hostStatus = "unknown";
            }

            $host["status"] = $hostStatus;
            $host["checks"] = $hostChecks;
            $summary[$hostStatus]++;
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
