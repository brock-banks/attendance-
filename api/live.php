<?php
// JSON feed for the dashboard (present-now + latest scans). Login required.
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json');

$today = date('Y-m-d');

$present = Database::q(
    "SELECT a.work_date,a.shift_id,a.emp_no, COALESCE(e.name, a.emp_no) AS name, a.check_in, a.check_out, a.status
     FROM attendance a LEFT JOIN employees e ON e.emp_no = a.emp_no
     WHERE a.work_date = ? ORDER BY a.check_in DESC",
    [$today]
)->fetchAll();

$feed = Database::q(
    "SELECT ev.event_time, ev.emp_no, COALESCE(e.name, ev.name, '(unknown)') AS name
     FROM events ev LEFT JOIN employees e ON e.emp_no = ev.emp_no
     ORDER BY ev.id DESC LIMIT 15"
)->fetchAll();

foreach ($present as &$p) {
    $p['checkout_state']=Report::checkoutState($p);
    $p['check_in']  = $p['check_in']  ? date('H:i', strtotime($p['check_in']))  : '';
    $p['check_out'] = $p['check_out'] ? date('H:i', strtotime($p['check_out'])) : '';
}
foreach ($feed as &$f) {
    $f['time'] = date('H:i:s', strtotime($f['event_time']));
}

echo json_encode(['present' => $present, 'feed' => $feed]);
