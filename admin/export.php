<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$filters=Report::filters($_GET); extract($filters);
$rows=Report::rows($filters);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance_' . $from . '_to_' . $to . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Date','Emp #','Name','Department','Shift','Check in','Check out','Worked (min)','Late (min)','Early-out (min)','Overtime (min)','Status','Approved overtime (min)','Punch review','Work sessions']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['work_date'], $r['emp_no'], $r['name'], $r['department'], $r['shift'],
        $r['check_in'], $r['check_out'], $r['work_minutes'], $r['late_minutes'],
        $r['early_minutes'], $r['overtime_minutes'], $r['status'], $r['approved_overtime_minutes'],
        !empty($r['review_required']) ? 'Review punches' : '', $r['sessions_json'] ?? '',
    ]);
}
fclose($out);
