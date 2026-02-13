<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../../");
$dotenv->safeLoad();

$projectRoot = realpath(__DIR__ . "/../../") . "/";
$dbPath = $_ENV["DB_PATH"] ?? $projectRoot . "data/minimon.sqlite";
// Resolve relative paths against project root
if ($dbPath !== "" && $dbPath[0] !== "/") {
    $dbPath = $projectRoot . $dbPath;
}

return [
    "debug" => filter_var($_ENV["APP_DEBUG"] ?? false, FILTER_VALIDATE_BOOLEAN),
    "db" => [
        "path" => $dbPath,
    ],
    "admin_password" => $_ENV["ADMIN_PASSWORD"] ?? "",
    "smtp" => [
        "host" => $_ENV["SMTP_HOST"] ?? "",
        "port" => (int) ($_ENV["SMTP_PORT"] ?? 587),
        "username" => $_ENV["SMTP_USER"] ?? "",
        "password" => $_ENV["SMTP_PASSWORD"] ?? "",
        "from_email" => $_ENV["SMTP_FROM_EMAIL"] ?? "",
        "from_name" => $_ENV["SMTP_FROM_NAME"] ?? "MiniMon",
        "encryption" => $_ENV["SMTP_ENCRYPTION"] ?? "tls",
        "debug_email" => $_ENV["DEBUG_EMAIL"] ?? "",
    ],
    "alert_recipients" => $_ENV["ALERT_RECIPIENTS"] ?? "",
    "push_api_key" => $_ENV["PUSH_API_KEY"] ?? "",
];
