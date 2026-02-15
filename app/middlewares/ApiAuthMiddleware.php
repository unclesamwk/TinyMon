<?php

use App\services\CsrfService;

// Ensure all API responses have Content-Type: application/json
Flight::before("start", function () {
    $request = Flight::request();
    if (str_starts_with($request->url, "/api/")) {
        Flight::response()->header("Content-Type", "application/json");
    }
});

Flight::before("start", function () {
    $request = Flight::request();

    if (!str_starts_with($request->url, "/api/")) {
        return;
    }

    // Push API has its own auth middleware
    if (str_starts_with($request->url, "/api/push/")) {
        return;
    }

    // API docs are public
    if (str_starts_with($request->url, "/api/docs")) {
        return;
    }

    if ($request->method === "OPTIONS") {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION["backend_auth"])) {
        Flight::response()->header("Content-Type", "application/json");
        Flight::halt(401, json_encode(["error" => "Unauthorized"]));
    }

    // CSRF validation for mutating requests
    if (in_array($request->method, ["POST", "PUT", "DELETE", "PATCH"], true)) {
        if (!CsrfService::validateRequest()) {
            Flight::response()->header("Content-Type", "application/json");
            Flight::halt(403, json_encode(["error" => "CSRF token invalid"]));
        }
    }
});
