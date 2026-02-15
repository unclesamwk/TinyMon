#!/usr/bin/env php
<?php
require __DIR__ . "/../vendor/autoload.php";

use App\services\Database;
use App\services\CheckRunner;
use App\services\AlertService;
use App\services\WebPushService;

// Ensure we run from project root
chdir(__DIR__ . "/../");

// Load config
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->safeLoad();

$config = require __DIR__ . "/../app/config/config.php";

// Initialize database
$dbPath = $config["db"]["path"];
$dsn = "sqlite:" . $dbPath;

if (!file_exists($dbPath)) {
    fwrite(
        STDERR,
        "Database not found: $dbPath\nStart the web application first to initialize the database.\n",
    );
    exit(1);
}

$db = new Database($dsn);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("PRAGMA journal_mode=WAL");
$db->exec("PRAGMA foreign_keys=ON");

// Run checks
$runner = new CheckRunner($db);
$results = $runner->runDue();

// Send alerts for status changes
$alertService = new AlertService($config["smtp"], $config["alert_recipients"]);
$pushService = new WebPushService($db, $config);

$alertCount = 0;
foreach ($results as $result) {
    if (!empty($result["status_changed"])) {
        $alertService->sendAlert($result);
        $pushService->sendAll($result);
        $alertCount++;
    }
}

$total = count($results);
$timestamp = gmdate("Y-m-d H:i:s");

// Store last run timestamp in settings
$db->runQuery(
    "INSERT INTO settings (key, value) VALUES ('runner_last_run', ?)
     ON CONFLICT(key) DO UPDATE SET value = excluded.value",
    [$timestamp],
);

echo "[{$timestamp}] Ran {$total} checks, {$alertCount} alerts sent.\n";

