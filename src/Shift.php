<?php
/**
 * Shift templates + scheduling helpers.
 */
class Shift
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM shifts" . ($activeOnly ? " WHERE active=1" : "") . " ORDER BY is_default DESC, name";
        return Database::q($sql)->fetchAll();
    }

    public static function get(?int $id): ?array
    {
        if (!$id) return null;
        $r = Database::q("SELECT * FROM shifts WHERE id=?", [$id])->fetch();
        return $r ?: null;
    }

    public static function getDefault(): ?array
    {
        $r = Database::q("SELECT * FROM shifts WHERE is_default=1 LIMIT 1")->fetch();
        if (!$r) { $r = Database::q("SELECT * FROM shifts ORDER BY id LIMIT 1")->fetch(); }
        return $r ?: null;
    }

    /** Shift assigned to an employee, falling back to the default. */
    public static function forEmployee(string $empNo): ?array
    {
        $r = Database::q(
            "SELECT s.* FROM employees e JOIN shifts s ON s.id = e.shift_id WHERE e.emp_no=?",
            [$empNo]
        )->fetch();
        return $r ?: self::getDefault();
    }

    public static function crossesMidnight(array $shift): bool
    {
        return strtotime($shift['end_time']) <= strtotime($shift['start_time']);
    }

    /**
     * The business day (shift date) an event belongs to.
     * For overnight shifts, early-morning scans roll back to the prior day.
     */
    public static function businessDate(string $eventTime, ?array $shift): string
    {
        $ts = strtotime($eventTime);
        if (!$shift || !self::crossesMidnight($shift)) {
            return date('Y-m-d', $ts);
        }
        $tod   = date('H:i:s', $ts);
        $start = date('H:i:s', strtotime($shift['start_time']));
        // At/after the start hour → today's shift; before → yesterday's overnight shift.
        return ($tod >= $start) ? date('Y-m-d', $ts) : date('Y-m-d', $ts - 86400);
    }

    public static function isWorkday(?array $shift, string $date): bool
    {
        if (!$shift) return true;
        $dow = (int)date('w', strtotime($date)); // 0=Sun..6=Sat
        $days = array_filter(array_map('trim', explode(',', $shift['workdays'])), 'strlen');
        return in_array((string)$dow, $days, true);
    }

    public static function setting(string $key, $default = null)
    {
        $r = Database::q("SELECT v FROM settings WHERE k=?", [$key])->fetch();
        return $r ? $r['v'] : $default;
    }

    public static function putSetting(string $key, $value): void
    {
        Database::q("INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)",
            [$key, (string)$value]);
    }
}
