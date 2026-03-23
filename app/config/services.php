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
$pdo->exec("PRAGMA busy_timeout=5000");
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
            slug TEXT DEFAULT NULL,
            interval_seconds INTEGER DEFAULT 300,
            enabled INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE
        );

        CREATE UNIQUE INDEX idx_checks_slug ON checks(slug) WHERE slug IS NOT NULL;

        CREATE TABLE check_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            check_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            value REAL,
            message TEXT DEFAULT '',
            metrics TEXT DEFAULT NULL,
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

        CREATE TABLE labels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entity_type TEXT NOT NULL,
            entity_id INTEGER NOT NULL,
            key TEXT NOT NULL,
            value TEXT NOT NULL,
            UNIQUE(entity_type, entity_id, key)
        );

        CREATE INDEX idx_labels_entity ON labels(entity_type, entity_id);
        CREATE INDEX idx_labels_key_value ON labels(key, value);
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

// Add topic column to hosts (migration)
$cols = $pdo->fetchAll("PRAGMA table_info(hosts)");
$colNames = array_column($cols, "name");
if (!in_array("topic", $colNames, true)) {
    $pdo->exec("ALTER TABLE hosts ADD COLUMN topic TEXT DEFAULT ''");
}

// Ensure alert_log table exists (migration)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS alert_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        check_id INTEGER NOT NULL,
        host_name TEXT NOT NULL,
        host_address TEXT NOT NULL,
        check_type TEXT NOT NULL,
        previous_status TEXT NOT NULL,
        new_status TEXT NOT NULL,
        alert_sent INTEGER NOT NULL DEFAULT 0,
        suppression_reason TEXT DEFAULT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (check_id) REFERENCES checks(id) ON DELETE CASCADE
    )
");
$pdo->exec(
    "CREATE INDEX IF NOT EXISTS idx_alert_log_created_at ON alert_log(created_at DESC)",
);
$pdo->exec(
    "CREATE INDEX IF NOT EXISTS idx_alert_log_check_id ON alert_log(check_id)",
);

// Add slug column to checks (migration)
$checkCols = $pdo->fetchAll("PRAGMA table_info(checks)");
$checkColNames = array_column($checkCols, "name");
if (!in_array("slug", $checkColNames, true)) {
    $pdo->exec(
        "ALTER TABLE checks ADD COLUMN slug TEXT DEFAULT NULL",
    );
    $pdo->exec(
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_checks_slug ON checks(slug) WHERE slug IS NOT NULL",
    );
}

// Add metrics column to check_results (migration)
$resultCols = $pdo->fetchAll("PRAGMA table_info(check_results)");
$resultColNames = array_column($resultCols, "name");
if (!in_array("metrics", $resultColNames, true)) {
    $pdo->exec(
        "ALTER TABLE check_results ADD COLUMN metrics TEXT DEFAULT NULL",
    );
}

// Ensure labels table exists (migration)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS labels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        entity_type TEXT NOT NULL,
        entity_id INTEGER NOT NULL,
        key TEXT NOT NULL,
        value TEXT NOT NULL,
        UNIQUE(entity_type, entity_id, key)
    )
");
$pdo->exec(
    "CREATE INDEX IF NOT EXISTS idx_labels_entity ON labels(entity_type, entity_id)",
);
$pdo->exec(
    "CREATE INDEX IF NOT EXISTS idx_labels_key_value ON labels(key, value)",
);

// Cleanup old login attempts
$pdo->exec(
    "DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-1 hour')",
);

Flight::map("db", function () use ($pdo) {
    return $pdo;
});
