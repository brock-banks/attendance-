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
        $pdo = Database::pdo();
        $empNo = trim((string)($e['emp_no'] ?? ''));
        $lock = self::lockName($empNo);
        if (!Database::q("SELECT GET_LOCK(?, 10)", [$lock])->fetchColumn()) {
            throw new RuntimeException('Attendance is busy; retry the event.');
        }
        try {
            $pdo->beginTransaction();
            $e['emp_no'] = $empNo;
            $key = self::eventKey($e);
            $existing = Database::q("SELECT id FROM events WHERE event_key=?", [$key])->fetchColumn();
            if ($existing) { $pdo->commit(); return (int)$existing; }
            $e['event_key'] = $key;
            $e['eligible'] = self::isEligible($e) ? 1 : 0;
            $eventId = self::insertEvent($e);
            if ($e['eligible']) {
                [$day,$shift]=Shift::forEvent($empNo,$e['event_time']);
                self::updateDay($empNo,$day,$shift);
            }
            $pdo->commit();
            return $eventId;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        } finally {
            Database::q("SELECT RELEASE_LOCK(?)", [$lock]);
        }
    }

    public static function lockName(string $empNo): string
    {
        return 'att:' . substr(hash('sha256', $empNo), 0, 60);
    }

    public static function eventKey(array $e): string
    {
        $raw = json_decode($e['raw_json'] ?? '{}', true) ?: [];
        $ace = $raw['AccessControllerEvent'] ?? [];
        // Identity excludes delivery counters, JSON ordering, names and snapshots.
        return hash('sha256', json_encode([
            $raw['macAddress'] ?? $raw['ipAddress'] ?? 'default',
            $ace['serialNo'] ?? $raw['serialNo'] ?? null,
            trim((string)($e['emp_no'] ?? '')), $e['event_time'],
            (int)($e['major_event'] ?? 0), (int)($e['sub_event'] ?? 0)
        ]));
    }

    public static function isEligible(array $e): bool
    {
        $payload = json_decode($e['raw_json'] ?? '{}', true) ?: [];
        $ace = $payload['AccessControllerEvent'] ?? [];
        // Successful verification only; unknown event types remain available for review.
        return !empty($e['emp_no']) && (int)($e['major_event'] ?? 0) === 5
            && in_array((int)($e['sub_event'] ?? 0), [75], true)
            && !in_array(strtolower((string)($ace['currentVerifyMode'] ?? '')), ['invalid', 'failed'], true);
    }

    private static function insertEvent(array $e): int
    {
        Database::q(
            "INSERT INTO events (emp_no, name, event_time, major_event, sub_event, verify_mode, image_path, raw_json, event_key, eligible)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $e['emp_no'] ?? null, $e['name'] ?? null, $e['event_time'],
                $e['major_event'] ?? null, $e['sub_event'] ?? null,
                $e['verify_mode'] ?? null, $e['image_path'] ?? null, $e['raw_json'] ?? null, $e['event_key'] ?? null, $e['eligible'] ?? 0,
            ]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    /**
     * Recompute one employee's attendance for one business day.
     * $shift may be null (unknown) â€” then falls back to the default.
     */
    public static function updateDay(string $empNo, string $day, ?array $shift = null): void
    {
        if (!Database::pdo()->inTransaction()) {
            $lock=self::lockName($empNo);
            if (!Database::q('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()) throw new RuntimeException('Attendance is busy.');
            try {
                Database::pdo()->beginTransaction();
                self::updateDay($empNo,$day,$shift);
                Database::pdo()->commit();
            } catch (Throwable $ex) {
                if (Database::pdo()->inTransaction()) Database::pdo()->rollBack();
                throw $ex;
            } finally { Database::q('SELECT RELEASE_LOCK(?)',[$lock]); }
            return;
        }
        $stored=Database::q('SELECT shift_snapshot FROM attendance WHERE emp_no=? AND work_date=?',[$empNo,$day])->fetchColumn();
        $shift=$stored?json_decode($stored,true):($shift??Shift::forEmployee($empNo,$day));

        [$winStart, $winEnd] = Shift::window($day, $shift);
        $events=Database::q("SELECT event_time,raw_json FROM events WHERE emp_no=? AND eligible=1 AND event_time>=? AND event_time<? ORDER BY event_time,id",[$empNo,$winStart,$winEnd])->fetchAll();
        $override=Database::q("SELECT * FROM attendance_corrections WHERE emp_no=? AND work_date=? AND approval_status='approved' ORDER BY reviewed_at DESC,id DESC LIMIT 1",[$empNo,$day])->fetch();
        if(!$events && !$override) { Database::q('DELETE FROM attendance WHERE emp_no=? AND work_date=?',[$empNo,$day]);return; }
        if($override) {
            // An approved correction explicitly replaces the day's interval.
            $events=[['event_time'=>$override['check_in'],'raw_json'=>'{"AccessControllerEvent":{"attendanceStatus":"checkIn"}}']];
            if($override['check_out']) $events[]=['event_time'=>$override['check_out'],'raw_json'=>'{"AccessControllerEvent":{"attendanceStatus":"checkOut"}}'];
        }
        if($override && !empty($override['sessions_json'])) {
            $events=[];
            foreach(json_decode($override['sessions_json'],true) as $session) foreach(['in'=>'checkIn','out'=>'checkOut'] as $key=>$direction) if(!empty($session[$key])) $events[]=['event_time'=>$session[$key],'raw_json'=>json_encode(['AccessControllerEvent'=>['attendanceStatus'=>$direction]])];
        }
        $paired=WorkSessions::calculate($events,MIN_RESCAN_SECONDS);
        $checkIn=$paired['check_in']; $checkOut=$paired['check_out'];
        $gross=$paired['minutes'];
        // Time already clocked out satisfies the unpaid break; do not deduct it twice.
        $breakRemaining=max(0,(int)($shift['break_minutes']??0)-$paired['away_minutes']);

        $work = max(0,$gross-$breakRemaining); $late = 0; $early = 0; $ot = 0; $status = 'present';

        if ($shift) {
            $startTs = strtotime($day . ' ' . $shift['start_time']);
            $endDay  = Shift::crossesMidnight($shift) ? date('Y-m-d', strtotime($day . ' +1 day')) : $day;
            $endTs   = strtotime($endDay . ' ' . $shift['end_time']);
            $grace   = (int)$shift['grace_minutes'];
            $brk     = (int)$shift['break_minutes'];
            $halfMin = (int)$shift['half_day_minutes'];
            $standard = max(0, (int)round(($endTs - $startTs) / 60) - $brk);

            $late = max(0, (int)round((($checkIn ? strtotime($checkIn) : $startTs) - ($startTs + $grace * 60)) / 60));

            if ($checkOut) {
                $work = max(0,$gross-$breakRemaining);   // deduct unpaid break
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
                $work = $gross;
            }
        }

        Database::q(
            "INSERT INTO attendance
                (emp_no, work_date, check_in, check_out, work_minutes, late_minutes, early_minutes, overtime_minutes, shift_id, status, sessions_json, review_required, shift_snapshot)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                check_in=VALUES(check_in), check_out=VALUES(check_out),
                work_minutes=VALUES(work_minutes), late_minutes=VALUES(late_minutes),
                early_minutes=VALUES(early_minutes), overtime_minutes=VALUES(overtime_minutes),
                shift_id=VALUES(shift_id), status=VALUES(status), sessions_json=VALUES(sessions_json), review_required=VALUES(review_required), shift_snapshot=COALESCE(shift_snapshot,VALUES(shift_snapshot))",
            [$empNo, $day, $checkIn, $checkOut, $work, $late, $early, $ot,
             $shift['id'] ?? null, $status, json_encode($paired['sessions']), (int)$paired['review_required'],json_encode($shift)]
        );
    }

    /**
     * Rebuild all attendance rows from the raw events (e.g. after changing shifts).
     * Groups each employee's events into shift business-days.
     */
    public static function recomputeAll(): int
    {
        // Rebuild in place so failures never erase the complete report.
        $rows = Database::q(
            "SELECT DISTINCT emp_no, event_time FROM events WHERE eligible=1 AND emp_no IS NOT NULL AND emp_no <> ''"
        )->fetchAll();

        $seen = [];
        foreach ($rows as $r) {
            [$day,$shift]=Shift::forEvent($r['emp_no'],$r['event_time']);
            $key   = $r['emp_no'] . '|' . $day;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            self::updateDay($r['emp_no'], $day);
        }
        $extra=Database::q("SELECT emp_no,work_date FROM attendance UNION SELECT emp_no,work_date FROM attendance_corrections WHERE approval_status='approved'")->fetchAll();
        foreach ($extra as $r) {
            $key=$r['emp_no'].'|'.$r['work_date'];
            if (!isset($seen[$key])) { self::updateDay($r['emp_no'],$r['work_date']); $seen[$key]=true; }
        }
        return count($seen);
    }

    public static function fmtMinutes(int $m): string
    {
        if ($m <= 0) return '0h 00m';
        return sprintf('%dh %02dm', intdiv($m, 60), $m % 60);
    }
}
