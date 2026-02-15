<?php

ini_set("session.cookie_httponly", "1");
ini_set("session.cookie_samesite", "Strict");
ini_set("session.use_strict_mode", "1");
if (
    !empty($_SERVER["HTTPS"]) ||
    ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https"
) {
    ini_set("session.cookie_secure", "1");
}
ini_set("session.gc_maxlifetime", (string) (30 * 24 * 60 * 60));
ini_set("session.cookie_lifetime", (string) (30 * 24 * 60 * 60));

$config = require __DIR__ . "/config.php";

// Persist sessions in data dir so they survive container restarts
$sessionDir =
    dirname(__DIR__, 2) . "/" . dirname($config["db"]["path"]) . "/sessions";
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
ini_set("session.save_path", $sessionDir);
Flight::set("config", $config);

if (empty($config["admin_password"])) {
    error_log(
        "[SECURITY] ADMIN_PASSWORD ist nicht gesetzt. Backend-Login ist deaktiviert.",
    );
}

if ($config["debug"]) {
    error_log("[SECURITY] APP_DEBUG ist aktiv.");
    ini_set("display_errors", "1");
    ini_set("display_startup_errors", "1");
    error_reporting(E_ALL);
    Flight::set("flight.log_errors", true);
} else {
    ini_set("display_errors", "0");
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

Flight::set("flight.views.path", __DIR__ . "/../views");

require __DIR__ . "/services.php";

require __DIR__ . "/../middlewares/CorsMiddleware.php";
require __DIR__ . "/../middlewares/PushApiAuthMiddleware.php";
require __DIR__ . "/../middlewares/ApiAuthMiddleware.php";

require __DIR__ . "/routes.php";
