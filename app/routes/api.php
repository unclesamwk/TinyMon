<?php

use App\controllers\api\HostController;
use App\controllers\api\CheckController;
use App\controllers\api\DashboardController;
use App\controllers\api\NotificationController;

// API Documentation
Flight::route("GET /api/docs", function () {
    header("Content-Type: text/html; charset=UTF-8");
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TinyMon API Docs</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
    SwaggerUIBundle({
        url: "/api/docs/openapi.yaml",
        dom_id: "#swagger-ui",
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
        layout: "BaseLayout"
    });
    </script>
</body>
</html>';
});

Flight::route("GET /api/docs/openapi.yaml", function () {
    $generator = new \OpenApi\Generator();
    $openapi = $generator->generate([__DIR__ . "/../controllers/"]);
    header("Content-Type: application/yaml");
    header("Cache-Control: no-cache");
    echo $openapi->toYaml();
});

// Version
Flight::route("GET /api/version", function () {
    $version = file_exists(__DIR__ . "/../../VERSION")
        ? trim(file_get_contents(__DIR__ . "/../../VERSION"))
        : "dev";
    Flight::json(["version" => $version]);
});

// Dashboard
Flight::route("GET /api/dashboard", [DashboardController::class, "index"]);

// Hosts
Flight::route("GET /api/hosts", [HostController::class, "list"]);
Flight::route("POST /api/hosts", [HostController::class, "create"]);
Flight::route("GET /api/hosts/@id", [HostController::class, "get"]);
Flight::route("PUT /api/hosts/@id", [HostController::class, "update"]);
Flight::route("DELETE /api/hosts/@id", [HostController::class, "delete"]);

// Checks
Flight::route("GET /api/hosts/@id/checks", [
    CheckController::class,
    "listForHost",
]);
Flight::route("POST /api/hosts/@id/checks", [CheckController::class, "create"]);
Flight::route("PUT /api/checks/@id", [CheckController::class, "update"]);
Flight::route("DELETE /api/checks/@id", [CheckController::class, "delete"]);
Flight::route("POST /api/checks/@id/run", [CheckController::class, "run"]);
Flight::route("POST /api/checks/@id/accept-hash", [
    CheckController::class,
    "acceptHash",
]);
Flight::route("GET /api/checks/@id/results", [
    CheckController::class,
    "results",
]);

// Notifications (Web Push)
Flight::route("GET /api/notifications/vapid-key", [
    NotificationController::class,
    "vapidKey",
]);
Flight::route("POST /api/notifications/subscribe", [
    NotificationController::class,
    "subscribe",
]);
Flight::route("POST /api/notifications/unsubscribe", [
    NotificationController::class,
    "unsubscribe",
]);
Flight::route("GET /api/notifications/status", [
    NotificationController::class,
    "status",
]);
Flight::route("POST /api/notifications/test", [
    NotificationController::class,
    "test",
]);
