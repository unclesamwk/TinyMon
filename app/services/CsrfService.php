<?php

namespace App\services;

class CsrfService
{
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function validateRequest(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = $_POST['_csrf_token'] ?? '';

        return !empty($token) && hash_equals(self::token(), $token);
    }
}
