<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$emp  = trim($_GET['emp'] ?? '');

$params = [$from, $to];
$where = "a.work_date BETWEEN ? AND ?";
if ($emp !== '') { $where .= " AND a.emp_no = ?"; $params[] = $emp; }

$rows = Database::q(
    "SELECT a.work_date, a.emp_no, COALESCE(e.name,a.emp_no) name, e.department,
            s.name AS shift, a.check_in, a.check_out,
            a.work_minutes, a.late_minutes, a.early_minutes, a.overtime_minutes, a.status
     FROM attendance a
     LEFT JOIN employees e ON e.emp_no=a.emp_no
     LEFT JOIN shifts s ON s.id=a.shift_id
     WHERE $where ORDER BY a.work_date, name",
    $params
)->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance_' . $from . '_to_' . $to . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Date','Emp #','Name','Department','Shift','Check in','Check out','Worked (min)','Late (min)','Early-out (min)','Overtime (min)','Status']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['work_date'], $r['emp_no'], $r['name'], $r['department'], $r['shift'],
        $r['check_in'], $r['check_out'], $r['work_minutes'], $r['late_minutes'],
        $r['early_minutes'], $r['overtime_minutes'], $r['status'],
    ]);
}
fclose($out);
