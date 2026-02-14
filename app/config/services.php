<?php

use App\services\Database;

$config = Flight::get("config");

$dbPath = $config["db"]["path"];
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

$isNew = !file_exists($dbPath);

$dsn = "sqlite:" . $dbPath;
$pdo = new Database($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec("PRAGMA journal_mode=WAL");
$pdo->exec("PRAGMA foreign_keys=ON");

if ($isNew) {
    $pdo->exec("
        CREATE TABLE hosts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            address TEXT NOT NULL,
            description TEXT DEFAULT '',
            enabled INTEGER DEFAULT 1,
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
    ");
}

// Ensure push_subscriptions table exists (migration)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        endpoint TEXT NOT NULL UNIQUE,
        p256dh_key TEXT NOT NULL,
        auth_key TEXT NOT NULL,
        user_agent TEXT DEFAULT '',
        created_at TEXT DEFAULT (datetime('now'))
    );
");

// Cleanup old login attempts
$pdo->exec(
    "DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-1 hour')",
);

Flight::map("db", function () use ($pdo) {
    return $pdo;
});
