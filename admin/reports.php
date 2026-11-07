<?php
$active = 'reports'; $pageTitle = 'Reports';
require_once dirname(__DIR__) . '/includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$emp  = trim($_GET['emp'] ?? '');

$params = [$from, $to];
$where = "a.work_date BETWEEN ? AND ?";
if ($emp !== '') { $where .= " AND a.emp_no = ?"; $params[] = $emp; }

$rows = Database::q(
    "SELECT a.*, COALESCE(e.name, a.emp_no) AS name, e.department
     FROM attendance a LEFT JOIN employees e ON e.emp_no = a.emp_no
     WHERE $where
     ORDER BY a.work_date DESC, name",
    $params
)->fetchAll();

$totalMin = array_sum(array_column($rows, 'work_minutes'));
$qs = http_build_query(['from' => $from, 'to' => $to, 'emp' => $emp]);
?>
<h1>Attendance report</h1>

<form class="filters" method="get">
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <label>Emp # <input name="emp" value="<?= e($emp) ?>" placeholder="all"></label>
  <button class="btn">Filter</button>
  <a class="btn ghost" href="<?= APP_BASE_URL ?>/admin/export.php?<?= e($qs) ?>">Export CSV</a>
</form>

<?php
$sum = Roster::summary($from, $to, $emp !== '' ? $emp : null);
$c = $sum['counts'];
$presentDays = $c['present'] + $c['late'] + $c['half'];
$totalOt = array_sum(array_column($rows, 'overtime_minutes'));
?>
<section class="cards" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr))">
  <div class="card ok"><div class="n"><?= $presentDays ?></div><div class="l">Present days</div></div>
  <div class="card warn"><div class="n"><?= $c['late'] ?></div><div class="l">Late</div></div>
  <div class="card bad"><div class="n"><?= $c['absent'] ?></div><div class="l">Absent</div></div>
  <div class="card"><div class="n"><?= $c['leave'] ?></div><div class="l">Leave</div></div>
  <div class="card"><div class="n"><?= $c['holiday'] ?></div><div class="l">Holiday</div></div>
  <div class="card"><div class="n"><?= $c['off'] ?></div><div class="l">Weekly off</div></div>
</section>
<p class="muted"><?= count($rows) ?> scan records · worked <?= Attendance::fmtMinutes($totalMin) ?> · overtime <?= Attendance::fmtMinutes((int)$totalOt) ?></p>

<table class="tbl">
  <thead><tr><th>Date</th><th>Emp #</th><th>Name</th><th>Dept</th><th>In</th><th>Out</th><th>Worked</th><th>Late</th><th>OT</th><th>Status</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="10" class="muted">No records in range.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r):
        $pc = $r['status']==='present' ? 'ok' : ($r['status']==='late'||$r['status']==='half' ? 'warn' : 'bad'); ?>
    <tr>
      <td><?= e($r['work_date']) ?></td>
      <td><?= e($r['emp_no']) ?></td>
      <td><?= e($r['name']) ?></td>
      <td><?= e($r['department']) ?></td>
      <td><?= $r['check_in'] ? e(date('H:i', strtotime($r['check_in']))) : '—' ?></td>
      <td><?= $r['check_out'] ? e(date('H:i', strtotime($r['check_out']))) : '—' ?></td>
      <td><?= Attendance::fmtMinutes((int)$r['work_minutes']) ?></td>
      <td><?= (int)$r['late_minutes'] > 0 ? e((int)$r['late_minutes']).'m' : '—' ?></td>
      <td><?= (int)$r['overtime_minutes'] > 0 ? Attendance::fmtMinutes((int)$r['overtime_minutes']) : '—' ?></td>
      <td><span class="pill <?= $pc ?>"><?= e($r['status']) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
