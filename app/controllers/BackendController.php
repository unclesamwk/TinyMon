<?php

namespace App\controllers;

use App\services\CsrfService;
use Flight;

class BackendController
{
    private static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION["backend_auth"]);
    }

    public static function index(): void
    {
        if (!self::isLoggedIn()) {
            Flight::redirect("/backend/login");
            return;
        }
        $config = Flight::get("config");
        Flight::render("backend/index", ["debug" => $config["debug"]]);
    }

    public static function showLogin(): void
    {
        if (self::isLoggedIn()) {
            Flight::redirect("/backend");
            return;
        }
        Flight::render("backend/login", ["error" => null]);
    }

    public static function submitLogin(): void
    {
        if (!CsrfService::validateRequest()) {
            Flight::render("backend/login", [
                "error" => "Ungültige Anfrage. Bitte erneut versuchen.",
            ]);
            return;
        }

        $password = Flight::request()->data->password ?? "";
        $config = Flight::get("config");

        if (empty($config["admin_password"])) {
            Flight::render("backend/login", [
                "error" =>
                    "Login ist nicht konfiguriert. Bitte ADMIN_PASSWORD setzen.",
            ]);
            return;
        }

        $db = Flight::db();
        $ip = Flight::request()->ip;
        $attempts = $db->fetchRow(
            "SELECT COUNT(*) as cnt FROM login_attempts WHERE ip = ? AND attempted_at > datetime('now', '-15 minutes')",
            [$ip],
        );
        if ($attempts && (int) $attempts["cnt"] >= 5) {
            Flight::render("backend/login", [
                "error" =>
                    "Zu viele Fehlversuche. Bitte später erneut versuchen.",
            ]);
            return;
        }

        $storedPassword = $config["admin_password"];
        $passwordValid =
            str_starts_with($storedPassword, '$2y$') ||
            str_starts_with($storedPassword, '$argon2')
                ? password_verify($password, $storedPassword)
                : hash_equals($storedPassword, $password);

        if ($passwordValid) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION["backend_auth"] = true;
            $db->runQuery("DELETE FROM login_attempts WHERE ip = ?", [$ip]);
            Flight::redirect("/backend");
            return;
        }

        $db->runQuery(
            "INSERT INTO login_attempts (ip, attempted_at) VALUES (?, datetime('now'))",
            [$ip],
        );
        Flight::render("backend/login", ["error" => "Falsches Passwort."]);
    }

    public static function manifest(): void
    {
        $version = file_exists(__DIR__ . "/../../VERSION")
            ? trim(file_get_contents(__DIR__ . "/../../VERSION"))
            : "dev";
        $v = "?v=" . $version;
        $manifest = [
            "name" => "TinyMon",
            "short_name" => "TinyMon",
            "start_url" => "/backend",
            "scope" => "/",
            "display" => "standalone",
            "orientation" => "any",
            "background_color" => "#ffffff",
            "theme_color" => "#007aff",
            "icons" => [
                [
                    "src" => "/assets/images/logo.svg",
                    "sizes" => "any",
                    "type" => "image/svg+xml",
                    "purpose" => "any",
                ],
                [
                    "src" => "/assets/images/icon-192.png" . $v,
                    "sizes" => "192x192",
                    "type" => "image/png",
                    "purpose" => "any maskable",
                ],
                [
                    "src" => "/assets/images/icon-512.png" . $v,
                    "sizes" => "512x512",
                    "type" => "image/png",
                    "purpose" => "any maskable",
                ],
            ],
        ];

        header("Content-Type: application/manifest+json");
        echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function logout(): void
    {
        if (!CsrfService::validateRequest()) {
            Flight::redirect("/backend");
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        session_destroy();
        Flight::redirect("/backend/login");
    }
}
