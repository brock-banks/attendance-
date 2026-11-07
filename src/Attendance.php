<?php
/**
 * Turns raw recognition events into daily attendance rows, using each
 * employee's shift to compute late / early-out / overtime / half-day.
 */
class Attendance
{
    /**
     * Record one recognition event and update the matching shift-day.
     * Returns the inserted event id, or null if ignored.
     */
    public static function recordEvent(array $e): ?int
    {
        $empNo = trim((string)($e['emp_no'] ?? ''));
        if ($empNo === '') {
            self::insertEvent($e);
            return null;
        }

        // De-dupe rapid re-scans.
        $recent = Database::q(
            "SELECT event_time FROM events WHERE emp_no = ? ORDER BY event_time DESC LIMIT 1",
            [$empNo]
        )->fetch();
        if ($recent) {
            $gap = strtotime($e['event_time']) - strtotime($recent['event_time']);
            if ($gap >= 0 && $gap < MIN_RESCAN_SECONDS) {
                return null;
            }
        }

        $eventId = self::insertEvent($e);

        $shift = Shift::forEmployee($empNo);
        $day   = Shift::businessDate($e['event_time'], $shift);
        self::updateDay($empNo, $day, $shift);
        return $eventId;
    }

    private static function insertEvent(array $e): int
    {
        Database::q(
            "INSERT INTO events (emp_no, name, event_time, major_event, sub_event, verify_mode, image_path, raw_json)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                $e['emp_no'] ?? null, $e['name'] ?? null, $e['event_time'],
                $e['major_event'] ?? null, $e['sub_event'] ?? null,
                $e['verify_mode'] ?? null, $e['image_path'] ?? null, $e['raw_json'] ?? null,
            ]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    /**
     * Recompute one employee's attendance for one business day.
     * $shift may be null (unknown) — then falls back to the default.
     */
    public static function updateDay(string $empNo, string $day, ?array $shift = null): void
    {
        $shift = $shift ?? Shift::forEmployee($empNo);

        // Build the scan window for this shift-day (with buffers for early/OT scans).
        if ($shift) {
            $startTs = strtotime($day . ' ' . $shift['start_time']);
            $endDay  = Shift::crossesMidnight($shift) ? date('Y-m-d', strtotime($day . ' +1 day')) : $day;
            $endTs   = strtotime($endDay . ' ' . $shift['end_time']);
            $winStart = date('Y-m-d H:i:s', $startTs - 3 * 3600);  // 3h before start
            $winEnd   = date('Y-m-d H:i:s', $endTs   + 8 * 3600);  // 8h after end (overtime)
        } else {
            $winStart = $day . ' 00:00:00';
            $winEnd   = $day . ' 23:59:59';
        }

        $row = Database::q(
            "SELECT MIN(event_time) AS first_t, MAX(event_time) AS last_t
             FROM events WHERE emp_no = ? AND event_time BETWEEN ? AND ?",
            [$empNo, $winStart, $winEnd]
        )->fetch();

        if (!$row || !$row['first_t']) { return; }

        $checkIn  = $row['first_t'];
        $checkOut = ($row['last_t'] !== $row['first_t']) ? $row['last_t'] : null;

        $work = 0; $late = 0; $early = 0; $ot = 0; $status = 'present';

        if ($shift) {
            $startTs = strtotime($day . ' ' . $shift['start_time']);
            $endDay  = Shift::crossesMidnight($shift) ? date('Y-m-d', strtotime($day . ' +1 day')) : $day;
            $endTs   = strtotime($endDay . ' ' . $shift['end_time']);
            $grace   = (int)$shift['grace_minutes'];
            $brk     = (int)$shift['break_minutes'];
            $halfMin = (int)$shift['half_day_minutes'];
            $standard = max(0, (int)round(($endTs - $startTs) / 60) - $brk);

            $late = max(0, (int)round((strtotime($checkIn) - ($startTs + $grace * 60)) / 60));

            if ($checkOut) {
                $gross = (int)round((strtotime($checkOut) - strtotime($checkIn)) / 60);
                $work  = $gross > $brk ? $gross - $brk : $gross;   // deduct unpaid break
                $early = max(0, (int)round(($endTs - strtotime($checkOut)) / 60));
                $ot    = max(0, $work - $standard);

                if ($work < $halfMin)      $status = 'half';
                elseif ($late > 0)         $status = 'late';
                else                       $status = 'present';
            } else {
                $status = $late > 0 ? 'late' : 'present';   // only checked in so far
            }
        } else {
            if ($checkOut) {
                $work = (int)round((strtotime($checkOut) - strtotime($checkIn)) / 60);
            }
        }

        Database::q(
            "INSERT INTO attendance
                (emp_no, work_date, check_in, check_out, work_minutes, late_minutes, early_minutes, overtime_minutes, shift_id, status)
             VALUES (?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                check_in=VALUES(check_in), check_out=VALUES(check_out),
                work_minutes=VALUES(work_minutes), late_minutes=VALUES(late_minutes),
                early_minutes=VALUES(early_minutes), overtime_minutes=VALUES(overtime_minutes),
                shift_id=VALUES(shift_id), status=VALUES(status)",
            [$empNo, $day, $checkIn, $checkOut, $work, $late, $early, $ot,
             $shift['id'] ?? null, $status]
        );
    }

    /**
     * Rebuild all attendance rows from the raw events (e.g. after changing shifts).
     * Groups each employee's events into shift business-days.
     */
    public static function recomputeAll(): int
    {
        Database::q("DELETE FROM attendance");
        $rows = Database::q(
            "SELECT DISTINCT emp_no, event_time FROM events WHERE emp_no IS NOT NULL AND emp_no <> ''"
        )->fetchAll();

        $seen = [];
        foreach ($rows as $r) {
            $shift = Shift::forEmployee($r['emp_no']);
            $day   = Shift::businessDate($r['event_time'], $shift);
            $key   = $r['emp_no'] . '|' . $day;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            self::updateDay($r['emp_no'], $day, $shift);
        }
        return count($seen);
    }

    public static function fmtMinutes(int $m): string
    {
        if ($m <= 0) return '0h 00m';
        return sprintf('%dh %02dm', intdiv($m, 60), $m % 60);
    }
}
