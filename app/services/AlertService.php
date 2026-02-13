<?php

namespace App\services;

use PHPMailer\PHPMailer\PHPMailer;

class AlertService
{
    private array $smtpConfig;
    private string $recipients;

    public function __construct(array $smtpConfig, string $recipients)
    {
        $this->smtpConfig = $smtpConfig;
        $this->recipients = $recipients;
    }

    public function sendAlert(array $result): bool
    {
        if (empty($this->recipients) || empty($this->smtpConfig['host'])) {
            return false;
        }

        $statusLabel = strtoupper($result['status']);
        $prevLabel = strtoupper($result['previous_status'] ?? 'unknown');
        $hostName = $result['host_name'] ?? 'Unknown';
        $checkType = $result['type'] ?? 'unknown';
        $address = $result['address'] ?? '';

        $subject = sprintf('[MiniMon] %s: %s / %s', $statusLabel, $hostName, $checkType);

        $body = sprintf(
            "Status changed: %s -> %s\n\nHost: %s (%s)\nCheck: %s\nValue: %s\nMessage: %s\nTime: %s",
            $prevLabel,
            $statusLabel,
            $hostName,
            $address,
            $checkType,
            $result['value'] ?? 'N/A',
            $result['message'] ?? '',
            date('Y-m-d H:i:s')
        );

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->smtpConfig['host'];
            $mail->Port = $this->smtpConfig['port'];
            $mail->CharSet = 'UTF-8';

            if (!empty($this->smtpConfig['username'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->smtpConfig['username'];
                $mail->Password = $this->smtpConfig['password'];
            }

            $enc = $this->smtpConfig['encryption'] ?? 'tls';
            if ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $fromEmail = $this->smtpConfig['from_email'] ?: 'minimon@localhost';
            $fromName = $this->smtpConfig['from_name'] ?: 'MiniMon';
            $mail->setFrom($fromEmail, $fromName);

            $debugEmail = $this->smtpConfig['debug_email'] ?? '';
            $recipientList = $debugEmail ?: $this->recipients;

            foreach (explode(',', $recipientList) as $addr) {
                $addr = trim($addr);
                if ($addr !== '') {
                    $mail->addAddress($addr);
                }
            }

            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('[MiniMon] Alert mail failed: ' . $e->getMessage());
            return false;
        }
    }
}
