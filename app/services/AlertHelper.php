<?php

namespace App\services;

class AlertHelper
{
    /**
     * Determine whether an alert should be sent for this check result.
     *
     * Recovery (status = "ok") alerts only when the previous consecutive non-ok
     * streak was at least $threshold long (i.e. a non-ok alert was actually sent).
     * Non-ok alerts only fire after $threshold consecutive results with the same status,
     * and only once (the result before those N must have a different status).
     *
     * Returns null if no alert should be sent, or the previous_status string
     * (the status before the threshold window) if an alert should fire.
     */
    public static function shouldAlert(
        Database $db,
        int $checkId,
        string $status,
        int $threshold,
    ): ?string {
        if ($status === "ok") {
            // Count how many consecutive non-ok results precede this ok
            // (offset 1 to skip the current result which is already inserted)
            $recentResults = $db->fetchAll(
                "SELECT status FROM check_results WHERE check_id = ? ORDER BY id DESC LIMIT ? OFFSET 1",
                [$checkId, $threshold],
            );

            if (empty($recentResults) || $recentResults[0]["status"] === "ok") {
                return null;
            }

            // Count consecutive non-ok from the top
            $nonOkStreak = 0;
            foreach ($recentResults as $row) {
                if ($row["status"] === "ok") {
                    break;
                }
                $nonOkStreak++;
            }

            if ($nonOkStreak >= $threshold) {
                return $recentResults[0]["status"];
            }

            return null;
        }

        $lastN = $db->fetchAll(
            "SELECT status FROM check_results WHERE check_id = ? ORDER BY id DESC LIMIT ?",
            [$checkId, $threshold],
        );

        if (count($lastN) < $threshold) {
            return null;
        }

        $allSame = count(array_unique(array_column($lastN, "status"))) === 1;
        if (!$allSame) {
            return null;
        }

        $before = $db->fetchRow(
            "SELECT status FROM check_results WHERE check_id = ? ORDER BY id DESC LIMIT 1 OFFSET ?",
            [$checkId, $threshold],
        );

        if (!$before || $before["status"] !== $status) {
            return $before["status"] ?? "unknown";
        }

        return null;
    }
}
