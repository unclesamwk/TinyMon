<?php

namespace App\services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;

class WebPushService
{
    private Database $db;
    private array $config;

    public function __construct(Database $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function getVapidPublicKey(): string
    {
        $keys = $this->getOrCreateVapidKeys();
        return $keys["publicKey"];
    }

    public function subscribe(
        string $endpoint,
        string $p256dh,
        string $auth,
        string $userAgent = "",
    ): void {
        $existing = $this->db->fetchRow(
            "SELECT id FROM push_subscriptions WHERE endpoint = ?",
            [$endpoint],
        );

        if ($existing) {
            $this->db->runQuery(
                "UPDATE push_subscriptions SET p256dh_key = ?, auth_key = ?, user_agent = ? WHERE id = ?",
                [$p256dh, $auth, $userAgent, $existing["id"]],
            );
        } else {
            $this->db->runQuery(
                "INSERT INTO push_subscriptions (endpoint, p256dh_key, auth_key, user_agent) VALUES (?, ?, ?, ?)",
                [$endpoint, $p256dh, $auth, $userAgent],
            );
        }
    }

    public function unsubscribe(string $endpoint): void
    {
        $this->db->runQuery(
            "DELETE FROM push_subscriptions WHERE endpoint = ?",
            [$endpoint],
        );
    }

    public function isSubscribed(string $endpoint): bool
    {
        $row = $this->db->fetchRow(
            "SELECT id FROM push_subscriptions WHERE endpoint = ?",
            [$endpoint],
        );
        return $row !== false && $row !== null;
    }

    public function sendAll(array $result): void
    {
        $subscriptions = $this->db->fetchAll(
            "SELECT * FROM push_subscriptions",
        );
        if (empty($subscriptions)) {
            return;
        }

        $keys = $this->getOrCreateVapidKeys();

        $auth = [
            "VAPID" => [
                "subject" => $this->getVapidSubject(),
                "publicKey" => $keys["publicKey"],
                "privateKey" => $keys["privateKey"],
            ],
        ];

        $webPush = new WebPush($auth);
        $webPush->setReuseVAPIDHeaders(true);

        $statusLabel = strtoupper($result["status"]);
        $hostName = $result["host_name"] ?? "Unknown";
        $checkType = $result["type"] ?? "unknown";
        $prevLabel = strtoupper($result["previous_status"] ?? "unknown");

        $payload = json_encode([
            "title" => sprintf("MiniMon: %s", $statusLabel),
            "body" => sprintf(
                "%s / %s: %s → %s",
                $hostName,
                $checkType,
                $prevLabel,
                $statusLabel,
            ),
            "icon" => "/assets/images/logo.svg",
            "tag" => "minimon-" . ($result["check_id"] ?? "alert"),
            "url" => "/backend",
        ]);

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                "endpoint" => $sub["endpoint"],
                "keys" => [
                    "p256dh" => $sub["p256dh_key"],
                    "auth" => $sub["auth_key"],
                ],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                $statusCode = $report->getResponse()?->getStatusCode();
                // Remove expired/invalid subscriptions
                if ($statusCode === 410 || $statusCode === 404) {
                    $this->db->runQuery(
                        "DELETE FROM push_subscriptions WHERE endpoint = ?",
                        [$endpoint],
                    );
                }
                error_log(
                    "[MiniMon] Push failed for " .
                        $endpoint .
                        ": " .
                        $report->getReason(),
                );
            }
        }
    }

    public function sendTest(): array
    {
        $subscriptions = $this->db->fetchAll(
            "SELECT * FROM push_subscriptions",
        );
        if (empty($subscriptions)) {
            return ["sent" => 0, "message" => "No subscriptions"];
        }

        $keys = $this->getOrCreateVapidKeys();

        $auth = [
            "VAPID" => [
                "subject" => $this->getVapidSubject(),
                "publicKey" => $keys["publicKey"],
                "privateKey" => $keys["privateKey"],
            ],
        ];

        $webPush = new WebPush($auth);
        $webPush->setReuseVAPIDHeaders(true);

        $payload = json_encode([
            "title" => "MiniMon Test",
            "body" => "Push Notifications funktionieren!",
            "icon" => "/assets/images/logo.svg",
            "tag" => "minimon-test",
            "url" => "/backend",
        ]);

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                "endpoint" => $sub["endpoint"],
                "keys" => [
                    "p256dh" => $sub["p256dh_key"],
                    "auth" => $sub["auth_key"],
                ],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        $sent = 0;
        $failed = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                $endpoint = $report->getRequest()->getUri()->__toString();
                $statusCode = $report->getResponse()?->getStatusCode();
                if ($statusCode === 410 || $statusCode === 404) {
                    $this->db->runQuery(
                        "DELETE FROM push_subscriptions WHERE endpoint = ?",
                        [$endpoint],
                    );
                }
            }
        }

        return ["sent" => $sent, "failed" => $failed];
    }

    private function getVapidSubject(): string
    {
        $subject = $this->config["vapid_subject"] ?? "";
        if ($subject !== "") {
            return $subject;
        }
        $email = $this->config["smtp"]["from_email"] ?? "admin@localhost";
        return "mailto:" . $email;
    }

    private function getOrCreateVapidKeys(): array
    {
        // Check .env config first
        $publicKey = $this->config["vapid_public_key"] ?? "";
        $privateKey = $this->config["vapid_private_key"] ?? "";

        if ($publicKey !== "" && $privateKey !== "") {
            return ["publicKey" => $publicKey, "privateKey" => $privateKey];
        }

        // Check settings table
        $pubRow = $this->db->fetchRow(
            "SELECT value FROM settings WHERE key = 'vapid_public_key'",
        );
        $privRow = $this->db->fetchRow(
            "SELECT value FROM settings WHERE key = 'vapid_private_key'",
        );

        if (
            $pubRow &&
            $privRow &&
            $pubRow["value"] !== "" &&
            $privRow["value"] !== ""
        ) {
            return [
                "publicKey" => $pubRow["value"],
                "privateKey" => $privRow["value"],
            ];
        }

        // Auto-generate
        $keys = VAPID::createVapidKeys();

        $this->db->runQuery(
            "INSERT OR REPLACE INTO settings (key, value) VALUES ('vapid_public_key', ?)",
            [$keys["publicKey"]],
        );
        $this->db->runQuery(
            "INSERT OR REPLACE INTO settings (key, value) VALUES ('vapid_private_key', ?)",
            [$keys["privateKey"]],
        );

        return $keys;
    }
}
