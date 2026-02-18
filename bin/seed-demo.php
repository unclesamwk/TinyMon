#!/usr/bin/env php
<?php
/**
 * Seed script for the TinyMon demo instance.
 * Creates demo hosts, checks, and 24h of historical results.
 */
require __DIR__ . "/../vendor/autoload.php";

use App\services\Database;

chdir(__DIR__ . "/../");

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->safeLoad();

$config = require __DIR__ . "/../app/config/config.php";

$dbPath = $config["db"]["path"];
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}
$isNew = !file_exists($dbPath);
$dsn = "sqlite:" . $dbPath;

$db = new Database($dsn);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("PRAGMA journal_mode=WAL");
$db->exec("PRAGMA busy_timeout=5000");
$db->exec("PRAGMA foreign_keys=ON");

if ($isNew) {
    echo "Creating database schema...\n";
    $db->exec("
        CREATE TABLE hosts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            address TEXT NOT NULL,
            description TEXT DEFAULT '',
            enabled INTEGER DEFAULT 1,
            topic TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE checks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            host_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            config TEXT DEFAULT '{}',
            interval_seconds INTEGER DEFAULT 300,
            enabled INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE
        );
        CREATE TABLE check_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            check_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            value REAL,
            message TEXT DEFAULT '',
            checked_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (check_id) REFERENCES checks(id) ON DELETE CASCADE
        );
        CREATE INDEX idx_check_results_check_id ON check_results(check_id, checked_at DESC);
        CREATE INDEX idx_check_results_checked_at ON check_results(checked_at);
        CREATE TABLE settings (
            key TEXT NOT NULL PRIMARY KEY,
            value TEXT
        );
        CREATE TABLE login_attempts (
            ip TEXT NOT NULL,
            attempted_at TEXT NOT NULL
        );
        CREATE TABLE push_subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            endpoint TEXT NOT NULL UNIQUE,
            p256dh_key TEXT NOT NULL,
            auth_key TEXT NOT NULL,
            user_agent TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now'))
        );
    ");
}

// Demo hosts with their checks
$hosts = [
    [
        "name" => "TinyMon Demo",
        "address" => "demo.p-q8g5ns.project.space",
        "description" => "This demo instance monitoring itself",
        "topic" => "demo",
        "checks" => [
            [
                "type" => "http",
                "config" => '{"port":443,"expected_status":200}',
                "base_value" => 250,
                "jitter" => 150,
                "message" => "HTTP 200, %sms",
            ],
            [
                "type" => "certificate",
                "config" => "{}",
                "base_value" => 180,
                "jitter" => 0,
                "message" => "%d days until expiry",
            ],
        ],
    ],
    [
        "name" => "Google",
        "address" => "google.com",
        "description" => "Google Search",
        "topic" => "external",
        "checks" => [
            [
                "type" => "ping",
                "config" => "{}",
                "base_value" => 15,
                "jitter" => 10,
                "message" => "%.1fms avg",
            ],
            [
                "type" => "http",
                "config" => '{"port":443,"expected_status":200}',
                "base_value" => 120,
                "jitter" => 80,
                "message" => "HTTP 200, %sms",
            ],
            [
                "type" => "certificate",
                "config" => "{}",
                "base_value" => 60,
                "jitter" => 0,
                "message" => "%d days until expiry",
            ],
        ],
    ],
    [
        "name" => "GitHub",
        "address" => "github.com",
        "description" => "GitHub",
        "topic" => "external",
        "checks" => [
            [
                "type" => "ping",
                "config" => "{}",
                "base_value" => 25,
                "jitter" => 15,
                "message" => "%.1fms avg",
            ],
            [
                "type" => "http",
                "config" => '{"port":443,"expected_status":200}',
                "base_value" => 200,
                "jitter" => 120,
                "message" => "HTTP 200, %sms",
            ],
            [
                "type" => "certificate",
                "config" => "{}",
                "base_value" => 90,
                "jitter" => 0,
                "message" => "%d days until expiry",
            ],
        ],
    ],
];

$db->exec("BEGIN TRANSACTION");

$resultCount = 0;

foreach ($hosts as $host) {
    $db->runQuery(
        "INSERT INTO hosts (name, address, description, topic) VALUES (?, ?, ?, ?)",
        [$host["name"], $host["address"], $host["description"], $host["topic"]],
    );
    $hostId = $db->lastInsertId();

    foreach ($host["checks"] as $check) {
        $db->runQuery(
            "INSERT INTO checks (host_id, type, config, interval_seconds, enabled) VALUES (?, ?, ?, 300, 1)",
            [$hostId, $check["type"], $check["config"]],
        );
        $checkId = $db->lastInsertId();

        // Generate 24h of results (every 5 minutes = 288 entries)
        $now = time();
        for ($i = 288; $i >= 0; $i--) {
            $timestamp = gmdate("Y-m-d H:i:s", $now - $i * 300);
            $value =
                $check["base_value"] +
                (mt_rand(-100, 100) / 100.0) * $check["jitter"];
            $value = max(1, round($value, 1));
            $message = sprintf($check["message"], $value);
            $status = "ok";

            // Occasional warning for non-certificate checks
            if ($check["type"] !== "certificate" && mt_rand(1, 50) === 1) {
                $status = "warning";
                $value = $check["base_value"] + $check["jitter"] * 3;
                $message = sprintf($check["message"], round($value, 1));
            }

            $db->runQuery(
                "INSERT INTO check_results (check_id, status, value, message, checked_at) VALUES (?, ?, ?, ?, ?)",
                [$checkId, $status, $value, $message, $timestamp],
            );
            $resultCount++;
        }
    }

    echo "Created host '{$host["name"]}' with " .
        count($host["checks"]) .
        " checks\n";
}

$db->exec("COMMIT");

echo "Seeded $resultCount check results.\n";

