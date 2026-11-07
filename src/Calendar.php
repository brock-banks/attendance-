<?php
/**
 * Public holiday calendar.
 */
class Calendar
{
    public static function all(): array
    {
        return Database::q("SELECT * FROM holidays ORDER BY holiday_date")->fetchAll();
    }

    /** Return the holiday on a date (exact, or recurring month-day), or null. */
    public static function on(string $date): ?array
    {
        $md = date('m-d', strtotime($date));
        $r = Database::q(
            "SELECT * FROM holidays
             WHERE holiday_date = ?
                OR (recurring = 1 AND DATE_FORMAT(holiday_date, '%m-%d') = ?)
             LIMIT 1",
            [$date, $md]
        )->fetch();
        return $r ?: null;
    }

    public static function isHoliday(string $date): bool
    {
        return self::on($date) !== null;
    }

    public static function add(string $date, string $name, bool $recurring): void
    {
        Database::q(
            "INSERT INTO holidays (holiday_date, name, recurring) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE name=VALUES(name), recurring=VALUES(recurring)",
            [$date, $name, $recurring ? 1 : 0]
        );
    }

    public static function delete(int $id): void
    {
        Database::q("DELETE FROM holidays WHERE id=?", [$id]);
    }
}
