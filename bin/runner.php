#!/usr/bin/env php
<?php
require __DIR__ . "/../vendor/autoload.php";

use App\services\Database;
use App\services\CheckRunner;
use App\services\AlertService;
use App\services\AlertHelper;
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
$db->exec("PRAGMA busy_timeout=5000");
$db->exec("PRAGMA foreign_keys=ON");

// Run checks
$runner = new CheckRunner($db);
$results = $runner->runDue();

// Send alerts with threshold support
$alertService = new AlertService($config["smtp"], $config["alert_recipients"]);
$pushService = new WebPushService($db, $config);

$thresholdRow = $db->fetchRow(
    "SELECT value FROM settings WHERE key = 'alert_threshold'",
);
$alertThreshold = max(1, (int) ($thresholdRow["value"] ?? 1));

$alertCount = 0;
foreach ($results as $result) {
    $prevStatus = AlertHelper::shouldAlert(
        $db,
        $result["check_id"],
        $result["status"],
        $alertThreshold,
    );
    if ($prevStatus !== null) {
        $result["previous_status"] = $prevStatus;
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

