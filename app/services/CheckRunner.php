<?php

namespace App\services;

class CheckRunner
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function runDue(): array
    {
        $checks = $this->db->fetchAll("
            SELECT c.*, h.address, h.name as host_name
            FROM checks c
            JOIN hosts h ON h.id = c.host_id
            WHERE c.enabled = 1 AND h.enabled = 1
            AND (
                NOT EXISTS (SELECT 1 FROM check_results WHERE check_id = c.id)
                OR (
                    SELECT strftime('%s', 'now') - strftime('%s', checked_at)
                    FROM check_results WHERE check_id = c.id ORDER BY checked_at DESC LIMIT 1
                ) >= c.interval_seconds
            )
        ");

        $results = [];
        foreach ($checks as $check) {
            $results[] = $this->executeCheck($check);
        }

        // Cleanup old results (>30 days)
        $this->db->runQuery(
            "DELETE FROM check_results WHERE checked_at < datetime('now', '-30 days')",
        );

        return $results;
    }

    public function executeCheck(array $check): array
    {
        $config = json_decode($check["config"] ?? "{}", true) ?: [];
        $address = $check["address"];

        // Get previous status for change detection
        $prevResult = $this->db->fetchRow(
            "SELECT status FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 1",
            [$check["id"]],
        );
        $prevStatus = $prevResult["status"] ?? null;

        $result = match ($check["type"]) {
            "ping" => $this->checkPing($address, $config),
            "http" => $this->checkHttp($address, $config),
            "port" => $this->checkPort($address, $config),
            "certificate" => $this->checkCertificate($address, $config),
            "content" => $this->checkContent($address, $config),
            "content_hash" => $this->checkContentHash(
                $address,
                $config,
                $check["id"],
            ),
            "disk" => $this->checkDisk($config),
            "load" => $this->checkLoad($config),
            "memory" => $this->checkMemory($config),
            "icecast_listeners" => $this->checkIcecastListeners(
                $address,
                $config,
            ),
            default => [
                "status" => "unknown",
                "value" => null,
                "message" => "Unknown check type: " . $check["type"],
            ],
        };

        // Store result
        $this->db->runQuery(
            "INSERT INTO check_results (check_id, status, value, message) VALUES (?, ?, ?, ?)",
            [
                $check["id"],
                $result["status"],
                $result["value"],
                $result["message"],
            ],
        );

        $result["check_id"] = $check["id"];
        $result["type"] = $check["type"];
        $result["host_name"] = $check["host_name"] ?? ($check["name"] ?? "");
        $result["address"] = $address;
        $result["status_changed"] =
            $prevStatus !== null && $prevStatus !== $result["status"];
        $result["previous_status"] = $prevStatus;

        return $result;
    }

    private function checkPing(string $address, array $config): array
    {
        $warningMs = $config["warning_ms"] ?? 100;
        $criticalMs = $config["critical_ms"] ?? 500;

        // Try native ping first, fallback to TCP connect on port 80
        $pingBin = trim((string) shell_exec("which ping 2>/dev/null"));
        if ($pingBin !== "") {
            return $this->checkPingIcmp($address, $warningMs, $criticalMs);
        }
        return $this->checkPingTcp($address, $warningMs, $criticalMs);
    }

    private function checkPingIcmp(
        string $address,
        float $warningMs,
        float $criticalMs,
    ): array {
        $count = 3;
        $isLinux = PHP_OS_FAMILY === "Linux";
        $flag = $isLinux ? "-W 5" : "-t 5";
        $cmd = sprintf(
            "ping -c %d %s %s 2>&1",
            $count,
            $flag,
            escapeshellarg($address),
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        $outputStr = implode("\n", $output);

        if ($exitCode !== 0) {
            return [
                "status" => "critical",
                "value" => null,
                "message" => "Ping failed: host unreachable",
            ];
        }

        if (
            preg_match("/(\d+\.\d+)\/(\d+\.\d+)\/(\d+\.\d+)/", $outputStr, $m)
        ) {
            $avg = (float) $m[2];
            $status = "ok";
            if ($avg >= $criticalMs) {
                $status = "critical";
            } elseif ($avg >= $warningMs) {
                $status = "warning";
            }
            return [
                "status" => $status,
                "value" => round($avg, 2),
                "message" => sprintf("%.1fms avg", $avg),
            ];
        }

        return [
            "status" => "unknown",
            "value" => null,
            "message" => "Could not parse ping output",
        ];
    }

    private function checkPingTcp(
        string $address,
        float $warningMs,
        float $criticalMs,
    ): array {
        $port = 80;
        $attempts = 3;
        $times = [];

        for ($i = 0; $i < $attempts; $i++) {
            $start = hrtime(true);
            $sock = @fsockopen($address, $port, $errno, $errstr, 5);
            $elapsed = (hrtime(true) - $start) / 1e6;

            if ($sock) {
                fclose($sock);
                $times[] = $elapsed;
            }
        }

        if (empty($times)) {
            return [
                "status" => "critical",
                "value" => null,
                "message" => "TCP connect failed (ping fallback, port $port)",
            ];
        }

        $avg = round(array_sum($times) / count($times), 2);
        $status = "ok";
        if ($avg >= $criticalMs) {
            $status = "critical";
        } elseif ($avg >= $warningMs) {
            $status = "warning";
        }

        return [
            "status" => $status,
            "value" => $avg,
            "message" => sprintf("%.1fms avg (TCP fallback)", $avg),
        ];
    }

    private function checkHttp(string $address, array $config): array
    {
        $port = $config["port"] ?? 443;
        $path = $config["url"] ?? "/";
        $expectedStatus = $config["expected_status"] ?? 200;
        $warningMs = $config["warning_ms"] ?? 1000;
        $criticalMs = $config["critical_ms"] ?? 5000;
        $verifySsl = $config["verify_ssl"] ?? true;

        $scheme = $port === 443 ? "https" : "http";
        $portSuffix =
            ($scheme === "https" && $port === 443) ||
            ($scheme === "http" && $port === 80)
                ? ""
                : ":" . $port;
        $url = $scheme . "://" . $address . $portSuffix . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_NOBODY => false,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);

        $start = microtime(true);
        curl_exec($ch);
        $elapsed = (microtime(true) - $start) * 1000;

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 0) {
            return [
                "status" => "critical",
                "value" => null,
                "message" => "Connection failed: " . $error,
            ];
        }

        if ($httpCode !== $expectedStatus) {
            return [
                "status" => "critical",
                "value" => round($elapsed, 2),
                "message" => sprintf(
                    "HTTP %d (expected %d), %.0fms",
                    $httpCode,
                    $expectedStatus,
                    $elapsed,
                ),
            ];
        }

        $status = "ok";
        if ($elapsed >= $criticalMs) {
            $status = "critical";
        } elseif ($elapsed >= $warningMs) {
            $status = "warning";
        }

        return [
            "status" => $status,
            "value" => round($elapsed, 2),
            "message" => sprintf("HTTP %d, %.0fms", $httpCode, $elapsed),
        ];
    }

    private function checkPort(string $address, array $config): array
    {
        $port = $config["port"] ?? 22;
        $warningMs = $config["warning_ms"] ?? 100;
        $criticalMs = $config["critical_ms"] ?? 500;
        $timeout = 5;

        $start = microtime(true);
        $sock = @fsockopen($address, $port, $errno, $errstr, $timeout);
        $elapsed = (microtime(true) - $start) * 1000;

        if (!$sock) {
            return [
                "status" => "critical",
                "value" => null,
                "message" => sprintf("Port %d closed: %s", $port, $errstr),
            ];
        }

        fclose($sock);

        $status = "ok";
        if ($elapsed >= $criticalMs) {
            $status = "critical";
        } elseif ($elapsed >= $warningMs) {
            $status = "warning";
        }

        return [
            "status" => $status,
            "value" => round($elapsed, 2),
            "message" => sprintf("Port %d open, %.0fms", $port, $elapsed),
        ];
    }

    private function checkCertificate(string $address, array $config): array
    {
        $port = $config["port"] ?? 443;
        $warningDays = $config["warning_days"] ?? 30;
        $criticalDays = $config["critical_days"] ?? 7;

        $context = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => true,
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://" . $address . ":" . $port,
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (!$client) {
            return [
                "status" => "critical",
                "value" => null,
                "message" => "SSL connection failed: " . $errstr,
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = openssl_x509_parse(
            $params["options"]["ssl"]["peer_certificate"] ?? "",
        );
        if (!$cert || !isset($cert["validTo_time_t"])) {
            return [
                "status" => "unknown",
                "value" => null,
                "message" => "Could not parse certificate",
            ];
        }

        $expiresAt = $cert["validTo_time_t"];
        $daysLeft = (int) (($expiresAt - time()) / 86400);

        $status = "ok";
        if ($daysLeft <= $criticalDays) {
            $status = "critical";
        } elseif ($daysLeft <= $warningDays) {
            $status = "warning";
        }

        $expiryDate = date("Y-m-d", $expiresAt);
        return [
            "status" => $status,
            "value" => $daysLeft,
            "message" => sprintf(
                "%d days until expiry (%s)",
                $daysLeft,
                $expiryDate,
            ),
        ];
    }

    private function fetchUrl(string $address, array $config): array
    {
        $port = $config["port"] ?? 443;
        $path = $config["url"] ?? "/";
        $verifySsl = $config["verify_ssl"] ?? true;

        $scheme = $port === 443 ? "https" : "http";
        $portSuffix =
            ($scheme === "https" && $port === 443) ||
            ($scheme === "http" && $port === 80)
                ? ""
                : ":" . $port;
        $url = $scheme . "://" . $address . $portSuffix . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => "MiniMon/1.0",
        ]);

        $start = microtime(true);
        $body = curl_exec($ch);
        $elapsed = (microtime(true) - $start) * 1000;

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            "body" => $body,
            "http_code" => $httpCode,
            "elapsed_ms" => round($elapsed, 2),
            "error" => $error,
            "url" => $url,
        ];
    }

    private function checkContent(string $address, array $config): array
    {
        $expectedStatus = $config["expected_status"] ?? 200;
        $expectedContent = $config["expected_content"] ?? "";
        $unexpectedContent = $config["unexpected_content"] ?? "";

        $resp = $this->fetchUrl($address, $config);

        if ($resp["http_code"] === 0) {
            return [
                "status" => "critical",
                "value" => null,
                "message" => "Connection failed: " . $resp["error"],
            ];
        }

        if ($expectedStatus && $resp["http_code"] !== $expectedStatus) {
            return [
                "status" => "critical",
                "value" => $resp["http_code"],
                "message" => sprintf(
                    "HTTP %d (expected %d)",
                    $resp["http_code"],
                    $expectedStatus,
                ),
            ];
        }

        $body = $resp["body"] ?: "";

        if (
            $expectedContent !== "" &&
            stripos($body, $expectedContent) === false
        ) {
            return [
                "status" => "critical",
                "value" => $resp["elapsed_ms"],
                "message" => sprintf(
                    'Expected content "%s" not found',
                    $expectedContent,
                ),
            ];
        }

        if (
            $unexpectedContent !== "" &&
            stripos($body, $unexpectedContent) !== false
        ) {
            return [
                "status" => "critical",
                "value" => $resp["elapsed_ms"],
                "message" => sprintf(
                    'Unexpected content "%s" found',
                    $unexpectedContent,
                ),
            ];
        }

        return [
            "status" => "ok",
            "value" => $resp["elapsed_ms"],
            "message" => sprintf(
                "HTTP %d, content OK, %.0fms",
                $resp["http_code"],
                $resp["elapsed_ms"],
            ),
        ];
    }

    private function checkContentHash(
        string $address,
        array $config,
        int $checkId,
    ): array {
        $expectedStatus = $config["expected_status"] ?? 200;
        $selector = $config["selector"] ?? ""; // optional: only hash part of body

        $resp = $this->fetchUrl($address, $config);

        if ($resp["http_code"] === 0) {
            return [
                "status" => "critical",
                "value" => null,
                "message" => "Connection failed: " . $resp["error"],
            ];
        }

        if ($expectedStatus && $resp["http_code"] !== $expectedStatus) {
            return [
                "status" => "critical",
                "value" => $resp["http_code"],
                "message" => sprintf(
                    "HTTP %d (expected %d)",
                    $resp["http_code"],
                    $expectedStatus,
                ),
            ];
        }

        $body = $resp["body"] ?: "";

        // If selector is set, try to extract that portion via regex
        if ($selector !== "") {
            if (preg_match($selector, $body, $m)) {
                $body = $m[0];
            }
        }

        $hash = hash("sha256", $body);

        // Get previous hash from last result message
        $prevResult = $this->db->fetchRow(
            "SELECT message FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 1",
            [$checkId],
        );

        $prevHash = "";
        if (
            $prevResult &&
            preg_match("/hash:([a-f0-9]{64})/", $prevResult["message"], $m)
        ) {
            $prevHash = $m[1];
        }

        // First run or no previous hash
        if ($prevHash === "") {
            return [
                "status" => "ok",
                "value" => $resp["elapsed_ms"],
                "message" => sprintf("Initial hash recorded, hash:%s", $hash),
            ];
        }

        if ($hash !== $prevHash) {
            return [
                "status" => "warning",
                "value" => $resp["elapsed_ms"],
                "message" => sprintf("Content changed! hash:%s", $hash),
            ];
        }

        return [
            "status" => "ok",
            "value" => $resp["elapsed_ms"],
            "message" => sprintf("Content unchanged, hash:%s", $hash),
        ];
    }

    private function checkDisk(array $config): array
    {
        $path = $config["path"] ?? "/";
        $warningPct = $config["warning_pct"] ?? 80;
        $criticalPct = $config["critical_pct"] ?? 95;

        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false) {
            return [
                "status" => "unknown",
                "value" => null,
                "message" => "Could not read disk space for " . $path,
            ];
        }

        $usedPct = round((($total - $free) / $total) * 100, 1);

        $status = "ok";
        if ($usedPct >= $criticalPct) {
            $status = "critical";
        } elseif ($usedPct >= $warningPct) {
            $status = "warning";
        }

        $freeGb = round($free / 1073741824, 1);
        return [
            "status" => $status,
            "value" => $usedPct,
            "message" => sprintf(
                "%.1f%% used, %.1f GB free",
                $usedPct,
                $freeGb,
            ),
        ];
    }

    private function checkLoad(array $config): array
    {
        $warningLoad = $config["warning"] ?? 2.0;
        $criticalLoad = $config["critical"] ?? 5.0;

        $load = sys_getloadavg();
        if ($load === false) {
            return [
                "status" => "unknown",
                "value" => null,
                "message" => "Could not read load average",
            ];
        }

        $load5 = $load[1];

        $status = "ok";
        if ($load5 >= $criticalLoad) {
            $status = "critical";
        } elseif ($load5 >= $warningLoad) {
            $status = "warning";
        }

        return [
            "status" => $status,
            "value" => round($load5, 2),
            "message" => sprintf(
                "Load: %.2f / %.2f / %.2f",
                $load[0],
                $load[1],
                $load[2],
            ),
        ];
    }

    private function checkMemory(array $config): array
    {
        $warningPct = $config["warning_pct"] ?? 80;
        $criticalPct = $config["critical_pct"] ?? 95;

        if (PHP_OS_FAMILY === "Linux" && is_readable("/proc/meminfo")) {
            $meminfo = file_get_contents("/proc/meminfo");
            preg_match("/MemTotal:\s+(\d+)/", $meminfo, $total);
            preg_match("/MemAvailable:\s+(\d+)/", $meminfo, $available);

            if (empty($total[1]) || empty($available[1])) {
                return [
                    "status" => "unknown",
                    "value" => null,
                    "message" => "Could not parse /proc/meminfo",
                ];
            }

            $totalKb = (int) $total[1];
            $availKb = (int) $available[1];
            $usedPct = round((($totalKb - $availKb) / $totalKb) * 100, 1);
            $availMb = round($availKb / 1024);
        } elseif (PHP_OS_FAMILY === "Darwin") {
            // macOS: use vm_stat
            $output = [];
            exec("vm_stat 2>&1", $output);
            $outputStr = implode("\n", $output);

            $pageSize = 4096;
            preg_match("/Pages free:\s+(\d+)/", $outputStr, $free);
            preg_match("/Pages inactive:\s+(\d+)/", $outputStr, $inactive);
            preg_match(
                "/Pages speculative:\s+(\d+)/",
                $outputStr,
                $speculative,
            );

            $totalOutput = [];
            exec("sysctl -n hw.memsize 2>&1", $totalOutput);
            $totalBytes = (int) ($totalOutput[0] ?? 0);

            if ($totalBytes === 0) {
                return [
                    "status" => "unknown",
                    "value" => null,
                    "message" => "Could not read memory info",
                ];
            }

            $freeBytes =
                (((int) ($free[1] ?? 0)) +
                    ((int) ($inactive[1] ?? 0)) +
                    ((int) ($speculative[1] ?? 0))) *
                $pageSize;
            $usedPct = round(
                (($totalBytes - $freeBytes) / $totalBytes) * 100,
                1,
            );
            $availMb = round($freeBytes / 1048576);
        } else {
            return [
                "status" => "unknown",
                "value" => null,
                "message" => "Memory check not supported on " . PHP_OS_FAMILY,
            ];
        }

        $status = "ok";
        if ($usedPct >= $criticalPct) {
            $status = "critical";
        } elseif ($usedPct >= $warningPct) {
            $status = "warning";
        }

        return [
            "status" => $status,
            "value" => $usedPct,
            "message" => sprintf(
                "%.1f%% used, %d MB available",
                $usedPct,
                $availMb,
            ),
        ];
    }

    private function checkIcecastListeners(
        string $address,
        array $config,
    ): array {
        $port = $config["port"] ?? 443;
        $mount = $config["mount"] ?? "/stream";
        $warningListeners = $config["warning_listeners"] ?? 0;
        $criticalListeners = $config["critical_listeners"] ?? 0;

        $scheme = $port === 443 ? "https" : "http";
        $portSuffix =
            ($scheme === "https" && $port === 443) ||
            ($scheme === "http" && $port === 80)
                ? ""
                : ":" . $port;
        $url = $scheme . "://" . $address . $portSuffix . "/status-json.xsl";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => "MiniMon/1.0",
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 0) {
            return [
                "status" => "critical",
                "value" => null,
                "message" => "Connection failed: " . $error,
            ];
        }

        if ($httpCode !== 200) {
            return [
                "status" => "critical",
                "value" => null,
                "message" => sprintf("HTTP %d from Icecast", $httpCode),
            ];
        }

        $data = json_decode($body, true);
        if (!$data || !isset($data["icestats"])) {
            return [
                "status" => "unknown",
                "value" => null,
                "message" => "Invalid Icecast JSON response",
            ];
        }

        $sources = $data["icestats"]["source"] ?? null;
        $mountNormalized = "/" . ltrim($mount, "/");

        // No active sources at all
        if ($sources === null) {
            $status = "ok";
            if ($criticalListeners > 0 && 0 < $criticalListeners) {
                $status = "critical";
            } elseif ($warningListeners > 0 && 0 < $warningListeners) {
                $status = "warning";
            }
            return [
                "status" => $status,
                "value" => 0,
                "message" => sprintf(
                    "Mountpoint %s not active, 0 listeners",
                    $mountNormalized,
                ),
            ];
        }

        // Normalize: single source is an object, multiple sources is an array
        if (isset($sources["listenurl"])) {
            $sources = [$sources];
        }

        // Find matching mountpoint
        $listeners = null;

        foreach ($sources as $source) {
            $listenUrl = $source["listenurl"] ?? "";
            $sourcePath = parse_url($listenUrl, PHP_URL_PATH) ?? "";
            if (str_ends_with($sourcePath, $mountNormalized)) {
                $listeners = (int) ($source["listeners"] ?? 0);
                break;
            }
        }

        if ($listeners === null) {
            $status = "ok";
            if ($criticalListeners > 0 && 0 < $criticalListeners) {
                $status = "critical";
            } elseif ($warningListeners > 0 && 0 < $warningListeners) {
                $status = "warning";
            }
            return [
                "status" => $status,
                "value" => 0,
                "message" => sprintf(
                    "Mountpoint %s not active, 0 listeners",
                    $mountNormalized,
                ),
            ];
        }

        $status = "ok";
        if ($criticalListeners > 0 && $listeners < $criticalListeners) {
            $status = "critical";
        } elseif ($warningListeners > 0 && $listeners < $warningListeners) {
            $status = "warning";
        }

        return [
            "status" => $status,
            "value" => $listeners,
            "message" => sprintf(
                "%d listeners on %s",
                $listeners,
                $mountNormalized,
            ),
        ];
    }
}
