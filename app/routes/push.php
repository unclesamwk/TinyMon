<?php

use App\controllers\api\PushController;

// Push API - external systems (K8s Operator, Terraform etc.)
Flight::route("GET /api/push/hosts", [PushController::class, "getHost"]);
Flight::route("POST /api/push/hosts", [PushController::class, "upsertHost"]);
Flight::route("DELETE /api/push/hosts", [PushController::class, "deleteHost"]);
Flight::route("GET /api/push/checks", [PushController::class, "getCheck"]);
Flight::route("POST /api/push/checks", [PushController::class, "upsertCheck"]);
Flight::route("DELETE /api/push/checks", [
    PushController::class,
    "deleteCheck",
]);
Flight::route("POST /api/push/results", [PushController::class, "pushResult"]);
Flight::route("POST /api/push/bulk", [PushController::class, "pushBulk"]);
Flight::route("GET|POST /api/push/@slug:[a-zA-Z0-9_-]+", [
    PushController::class,
    "pushBySlug",
]);
