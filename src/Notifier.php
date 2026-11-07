<?php
/**
 * Builds and sends late / absent / daily-summary alert emails.
 * Deduped via the notifications table (each alert sent once per day).
 */
class Notifier
{
    public static function recipient(): string
    {
        $r = (string)Shift::setting('alert_recipient', '');
        if ($r === '') { $cfg = Mailer::config(); $r = $cfg['from'] !== '' ? $cfg['from'] : $cfg['user']; }
        return $r;
    }

    private static function wasSent(string $type, string $emp, string $date): bool
    {
        return (bool)Database::q(
            "SELECT 1 FROM notifications WHERE type=? AND emp_no=? AND ref_date=?",
            [$type, $emp, $date]
        )->fetch();
    }

    private static function logSent(string $type, string $emp, string $date): void
    {
        Database::q("INSERT IGNORE INTO notifications (type, emp_no, ref_date) VALUES (?,?,?)",
            [$type, $emp, $date]);
    }

    private static function wrap(string $inner): string
    {
        return '<div style="font-family:Segoe UI,Arial,sans-serif;color:#242424;max-width:560px">'
             . '<div style="background:#107c41;color:#fff;padding:12px 16px;border-radius:8px 8px 0 0;font-weight:600">Attendance</div>'
             . '<div style="border:1px solid #e0e0e0;border-top:0;border-radius:0 0 8px 8px;padding:16px">'
             . $inner
             . '<p style="color:#9aa0b4;font-size:12px;margin-top:20px">Automated message from your attendance system.</p>'
             . '</div></div>';
    }

    /** Entry point — run all enabled alert passes. */
    public static function run(?int $now = null): array
    {
        $now = $now ?? time();
        $res = ['late' => 0, 'absent' => 0, 'summary' => 0, 'errors' => []];

        if (Shift::setting('alerts_enabled', '0') !== '1') { $res['skipped'] = 'alerts disabled'; return $res; }
        if (!Mailer::configured())                          { $res['skipped'] = 'SMTP not configured'; return $res; }
        $to = self::recipient();
        if ($to === '')                                     { $res['skipped'] = 'no recipient'; return $res; }

        if (Shift::setting('alert_late', '1') === '1')    $res['late']    = self::processLate($now, $to, $res);
        if (Shift::setting('alert_absent', '1') === '1')  $res['absent']  = self::processAbsent($now, $to, $res);
        if (Shift::setting('alert_summary', '1') === '1') $res['summary'] = self::processSummary($now, $to, $res);

        return $res;
    }

    private static function processLate(int $now, string $to, array &$res): int
    {
        $today = date('Y-m-d', $now);
        $rows = Database::q(
            "SELECT a.emp_no, COALESCE(e.name,a.emp_no) AS name, a.check_in, a.late_minutes
             FROM attendance a LEFT JOIN employees e ON e.emp_no=a.emp_no
             WHERE a.work_date=? AND a.late_minutes>0 AND a.check_in IS NOT NULL",
            [$today]
        )->fetchAll();

        $n = 0;
        foreach ($rows as $r) {
            if (self::wasSent('late', $r['emp_no'], $today)) continue;
            $html = self::wrap('<h2 style="margin:0 0 8px">Late arrival</h2><p><b>' . e($r['name']) . '</b> checked in at '
                 . date('H:i', strtotime($r['check_in'])) . ' on ' . $today . ' — <b>' . (int)$r['late_minutes'] . ' min late</b>.</p>');
            $err = null;
            if (Mailer::send($to, 'Late arrival: ' . $r['name'], $html, $err)) { self::logSent('late', $r['emp_no'], $today); $n++; }
            else { $res['errors'][] = 'late: ' . $err; }
        }
        return $n;
    }

    private static function processAbsent(int $now, string $to, array &$res): int
    {
        $today = date('Y-m-d', $now);
        if (Calendar::isHoliday($today)) return 0;
        $cutoff = (int)Shift::setting('absent_after_minutes', '60');

        $emps = Database::q("SELECT emp_no, name FROM employees WHERE active=1")->fetchAll();
        $n = 0;
        foreach ($emps as $e) {
            $emp = $e['emp_no'];
            $sh = Shift::forEmployee($emp);
            if (!Shift::isWorkday($sh, $today)) continue;
            if (Leave::on($emp, $today)) continue;
            $startTs = strtotime($today . ' ' . ($sh['start_time'] ?? '09:00:00'));
            if ($now < $startTs + $cutoff * 60) continue;        // cutoff not reached yet
            $has = Database::q("SELECT 1 FROM attendance WHERE emp_no=? AND work_date=? AND check_in IS NOT NULL", [$emp, $today])->fetch();
            if ($has) continue;
            if (self::wasSent('absent', $emp, $today)) continue;

            $name = $e['name'] ?: $emp;
            $html = self::wrap('<h2 style="margin:0 0 8px">No check-in</h2><p><b>' . e($name) . '</b> has not checked in today (' . $today
                 . '). Shift started at ' . substr((string)($sh['start_time'] ?? ''), 0, 5) . '.</p>');
            $err = null;
            if (Mailer::send($to, 'Absent: ' . $name, $html, $err)) { self::logSent('absent', $emp, $today); $n++; }
            else { $res['errors'][] = 'absent: ' . $err; }
        }
        return $n;
    }

    private static function processSummary(int $now, string $to, array &$res): int
    {
        $today = date('Y-m-d', $now);
        if (date('H:i', $now) < (string)Shift::setting('summary_time', '18:00')) return 0;
        if (self::wasSent('summary', '', $today)) return 0;

        $sum = Roster::summary($today, $today);
        $c = $sum['counts'];

        // Per-employee status line.
        $emps = Database::q("SELECT emp_no, name FROM employees WHERE active=1 ORDER BY name")->fetchAll();
        $rowsHtml = '';
        foreach ($emps as $e) {
            $sh = Shift::forEmployee($e['emp_no']);
            $att = Database::q("SELECT * FROM attendance WHERE emp_no=? AND work_date=?", [$e['emp_no'], $today])->fetch() ?: null;
            $st = Roster::resolve($e['emp_no'], $today, $sh, $att);
            $in = $att && $att['check_in'] ? date('H:i', strtotime($att['check_in'])) : '—';
            $rowsHtml .= '<tr><td style="padding:4px 8px;border-bottom:1px solid #eee">' . e($e['name'] ?: $e['emp_no'])
                      . '</td><td style="padding:4px 8px;border-bottom:1px solid #eee">' . e($st)
                      . '</td><td style="padding:4px 8px;border-bottom:1px solid #eee">' . $in . '</td></tr>';
        }

        $html = self::wrap(
            '<h2 style="margin:0 0 8px">Daily summary · ' . $today . '</h2>'
            . '<p>Present <b>' . ($c['present'] + $c['late'] + $c['half']) . '</b> · Late <b>' . $c['late']
            . '</b> · Absent <b>' . $c['absent'] . '</b> · Leave <b>' . $c['leave'] . '</b> · Off <b>' . $c['off'] . '</b></p>'
            . '<table style="border-collapse:collapse;width:100%;font-size:14px"><tr><th style="text-align:left;padding:4px 8px">Name</th><th style="text-align:left;padding:4px 8px">Status</th><th style="text-align:left;padding:4px 8px">In</th></tr>'
            . $rowsHtml . '</table>'
        );
        $err = null;
        if (Mailer::send($to, 'Attendance summary · ' . $today, $html, $err)) { self::logSent('summary', '', $today); return 1; }
        $res['errors'][] = 'summary: ' . $err;
        return 0;
    }

    /** Send a one-off test email; returns [ok, error]. */
    public static function test(string $to, ?string &$err = null): bool
    {
        $html = self::wrap('<h2 style="margin:0 0 8px">Test email</h2><p>Your attendance alerts are configured correctly. 🎉</p>');
        return Mailer::send($to, 'Attendance test email', $html, $err);
    }
}
