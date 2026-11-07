<?php
$active = 'index'; $pageTitle = 'Dashboard';
require_once dirname(__DIR__) . '/includes/header.php';

$today = date('Y-m-d');
$totalEmp  = (int)Database::q("SELECT COUNT(*) c FROM employees WHERE active=1")->fetch()['c'];
$present   = (int)Database::q("SELECT COUNT(*) c FROM attendance WHERE work_date=? AND check_in IS NOT NULL", [$today])->fetch()['c'];
$late      = (int)Database::q("SELECT COUNT(*) c FROM attendance WHERE work_date=? AND status='late'", [$today])->fetch()['c'];
$onLeave   = Leave::onLeaveToday();
$isHoliday = Calendar::isHoliday($today);

// Scheduled to work today = active, working day for their shift, not holiday, not on leave.
$scheduled = 0;
foreach (Database::q("SELECT emp_no FROM employees WHERE active=1")->fetchAll() as $emp) {
    $sh = Shift::forEmployee($emp['emp_no']);
    if (!$isHoliday && Shift::isWorkday($sh, $today) && !Leave::on($emp['emp_no'], $today)) {
        $scheduled++;
    }
}
$absent = max(0, $scheduled - $present);
?>
<h1>Dashboard <small><?= e(date('l, d M Y')) ?><?= $isHoliday ? ' · Holiday' : '' ?></small></h1>

<section class="cards" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
  <div class="card"><div class="n"><?= $totalEmp ?></div><div class="l">Employees</div></div>
  <div class="card ok"><div class="n"><?= $present ?></div><div class="l">Present today</div></div>
  <div class="card warn"><div class="n"><?= $late ?></div><div class="l">Late</div></div>
  <div class="card bad"><div class="n"><?= $absent ?></div><div class="l">Absent</div></div>
  <div class="card"><div class="n"><?= $onLeave ?></div><div class="l">On leave</div></div>
</section>

<section class="grid2">
  <div class="panel">
    <h2>Present now</h2>
    <table class="tbl" id="present-tbl">
      <thead><tr><th>Emp #</th><th>Name</th><th>In</th><th>Out</th><th>Status</th></tr></thead>
      <tbody><tr><td colspan="5" class="muted">Loading…</td></tr></tbody>
    </table>
  </div>

  <div class="panel">
    <h2>Live scans <span class="dot" title="auto-refresh"></span></h2>
    <table class="tbl" id="feed-tbl">
      <thead><tr><th>Time</th><th>Emp #</th><th>Name</th></tr></thead>
      <tbody><tr><td colspan="3" class="muted">Loading…</td></tr></tbody>
    </table>
  </div>
</section>

<script>window.ATT_BASE = <?= json_encode(APP_BASE_URL) ?>;</script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
