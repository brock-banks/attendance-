<?php
/**
 * Leave records (admin-entered; no approval workflow for single-admin use).
 */
class Leave
{
    const TYPES = ['paid' => 'Paid', 'sick' => 'Sick', 'unpaid' => 'Unpaid', 'other' => 'Other'];

    /** Leave covering a given date for an employee, or null. */
    public static function on(string $empNo, string $date): ?array
    {
        $r = Database::q(
            "SELECT * FROM leaves WHERE emp_no=? AND ? BETWEEN start_date AND end_date LIMIT 1",
            [$empNo, $date]
        )->fetch();
        return $r ?: null;
    }

    public static function recent(int $limit = 100): array
    {
        return Database::q(
            "SELECT l.*, COALESCE(e.name, l.emp_no) AS name
             FROM leaves l LEFT JOIN employees e ON e.emp_no = l.emp_no
             ORDER BY l.start_date DESC LIMIT " . (int)$limit
        )->fetchAll();
    }

    public static function add(string $empNo, string $start, string $end, string $type, ?string $note): void
    {
        if (strtotime($end) < strtotime($start)) { $end = $start; }
        $type = array_key_exists($type, self::TYPES) ? $type : 'other';
        Database::q(
            "INSERT INTO leaves (emp_no, start_date, end_date, type, note) VALUES (?,?,?,?,?)",
            [$empNo, $start, $end, $type, $note ?: null]
        );
    }

    public static function delete(int $id): void
    {
        Database::q("DELETE FROM leaves WHERE id=?", [$id]);
    }

    /** Count of leave days today, for the dashboard. */
    public static function onLeaveToday(): int
    {
        $today = date('Y-m-d');
        return (int)Database::q(
            "SELECT COUNT(DISTINCT emp_no) c FROM leaves WHERE ? BETWEEN start_date AND end_date",
            [$today]
        )->fetch()['c'];
    }
}
