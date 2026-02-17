<?php

namespace App\services;

class AlertHelper
{
    /**
     * Determine whether an alert should be sent for this check result.
     *
     * Recovery (status = "ok") alerts immediately when the previous result was non-ok.
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
            $prev = $db->fetchRow(
                "SELECT status FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 1 OFFSET 1",
                [$checkId],
            );
            if ($prev && $prev["status"] !== "ok") {
                return $prev["status"];
            }
            return null;
        }

        $lastN = $db->fetchAll(
            "SELECT status FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT ?",
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
            "SELECT status FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 1 OFFSET ?",
            [$checkId, $threshold],
        );

        if (!$before || $before["status"] !== $status) {
            return $before["status"] ?? "unknown";
        }

        return null;
    }
}
