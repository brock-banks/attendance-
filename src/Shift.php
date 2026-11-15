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
    public static function forEmployee(string $empNo, ?string $day=null): ?array
    {
        $day=$day??date('Y-m-d');
        $historical=Database::q('SELECT snapshot FROM shift_assignments WHERE emp_no=? AND effective_date<=? ORDER BY effective_date DESC,id DESC LIMIT 1',[$empNo,$day])->fetchColumn();
        if($historical!==false) return json_decode($historical,true)?:null;
        $r = Database::q(
            "SELECT s.* FROM employees e JOIN shifts s ON s.id = e.shift_id WHERE e.emp_no=?",
            [$empNo]
        )->fetch();
        return $r ?: self::getDefault();
    }

    public static function forEvent(string $empNo,string $time): array {
        $date=substr($time,0,10);$matches=[];
        foreach([-1,0,1] as $offset){$day=date('Y-m-d',strtotime($date.' '.($offset>=0?'+':'').$offset.' day'));
            $stored=Database::q('SELECT shift_snapshot FROM attendance WHERE emp_no=? AND work_date=?',[$empNo,$day])->fetchColumn();
            $shift=$stored?json_decode($stored,true):self::forEmployee($empNo,$day);
            [$start,$end]=self::window($day,$shift);if($time>=$start && $time<$end)$matches[]=['day'=>$day,'shift'=>$shift,'start'=>$start];
        }
        if($matches){usort($matches,static fn($a,$b)=>strcmp($b['start'],$a['start']));return [$matches[0]['day'],$matches[0]['shift']];}
        $shift=self::forEmployee($empNo,$date);return [self::businessDate($time,$shift),$shift];
    }

    public static function assign(string $empNo, ?int $shiftId, string $effective): void {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$effective) || date('Y-m-d',strtotime($effective))!==$effective || $effective<=date('Y-m-d')) throw new InvalidArgumentException('Shift changes must start tomorrow or later.');
        $snapshot=$shiftId?self::get($shiftId):self::getDefault();
        if(!$snapshot) throw new InvalidArgumentException('Select an available shift.');
        if(!Database::q('SELECT id FROM shift_assignments WHERE emp_no=? LIMIT 1',[$empNo])->fetchColumn()) Database::q('INSERT INTO shift_assignments(emp_no,effective_date,snapshot) VALUES(?,?,?)',[$empNo,'1000-01-01',json_encode(self::forEmployee($empNo))]);
        Database::q('INSERT INTO shift_assignments(emp_no,effective_date,snapshot,actor_id) VALUES(?,?,?,?)',[$empNo,$effective,json_encode($snapshot),$_SESSION['admin_id']??null]);
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
        if (!$shift) return date('Y-m-d', $ts);
        $day = date('Y-m-d', $ts);
        $boundary = strtotime($day . ' ' . $shift['start_time'] . ' -3 hours');
        if ($ts < $boundary) return date('Y-m-d', strtotime($day . ' -1 day'));
        $next = strtotime($day . ' +1 day ' . $shift['start_time'] . ' -3 hours');
        return $ts >= $next ? date('Y-m-d', strtotime($day . ' +1 day')) : $day;
    }

    /** Half-open interval; one scan can belong to only one shift day. */
    public static function window(string $day, ?array $shift): array
    {
        $start = new DateTimeImmutable($day . ' ' . ($shift['start_time'] ?? '00:00:00'));
        if ($shift) $start = $start->modify('-3 hours');
        $next = new DateTimeImmutable(date('Y-m-d', strtotime($day . ' +1 day')) . ' ' . ($shift['start_time'] ?? '00:00:00'));
        if ($shift) $next = $next->modify('-3 hours');
        return [$start->format('Y-m-d H:i:s'), $next->format('Y-m-d H:i:s')];
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
