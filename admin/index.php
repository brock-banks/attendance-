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

<?php
$pending=(int)Database::q("SELECT COUNT(*) FROM attendance_corrections WHERE approval_status='pending'")->fetchColumn();
$open=Database::q("SELECT a.*,COALESCE(e.name,a.emp_no) name FROM attendance a LEFT JOIN employees e ON e.emp_no=a.emp_no WHERE a.check_in IS NOT NULL AND a.check_out IS NULL ORDER BY a.work_date DESC")->fetchAll();
$missing=array_values(array_filter($open,static fn($r)=>Report::checkoutState($r)==='Missing check-out'));
$lastPush=Shift::setting('last_push_at','');$stale=!$lastPush || time()-strtotime($lastPush)>300;
?>


<section class="cards dashboard-summary" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
  <div class="card"><div class="n"><?= $totalEmp ?></div><div class="l">Employees</div></div>
  <div class="card ok"><div class="n"><?= $present ?></div><div class="l">Present today</div></div>
  <div class="card warn"><div class="n"><?= $late ?></div><div class="l">Late</div></div>
  <div class="card bad"><div class="n"><?= $absent ?></div><div class="l">Absent</div></div>
  <div class="card"><div class="n"><?= $onLeave ?></div><div class="l">On leave</div></div>
</section>

<div class="dashboard-review-strip" aria-label="Items needing attention">
<?php if($missing): ?><a href="reports.php?<?= e(http_build_query(['from'=>min(array_column($missing,'work_date')),'to'=>$today,'status'=>'Missing check-out'])) ?>"><strong><?= count($missing) ?></strong> missing check-outs <span aria-hidden="true">→</span></a><?php endif; ?>
<?php if($pending): ?><a href="corrections.php#history"><strong><?= $pending ?></strong> corrections to review <span aria-hidden="true">→</span></a><?php endif; ?>
<?php if(!$missing && !$pending): ?><span>No attendance issues to review.</span><?php endif; ?>
<?php if($stale): ?><?php if(Auth::role()==='admin'): ?><a class="device-warning" href="device-setup.php">Device updates delayed →</a><?php else: ?><span class="device-warning">Device updates delayed · Contact an administrator</span><?php endif; ?><?php endif; ?>
</div>
<section class="dashboard-focus">
  <div class="panel">
    <div class="dashboard-table-heading"><h2>Today’s attendance</h2><a href="reports.php?<?= e(http_build_query(['from'=>$today,'to'=>$today])) ?>">View report →</a></div>
    <table class="tbl" id="present-tbl">
      <thead><tr><th>Emp #</th><th>Name</th><th>In</th><th>Out</th><th>Status</th></tr></thead>
      <tbody><tr><td colspan="5" class="muted">Loading…</td></tr></tbody>
    </table>
  </div>

  <details class="panel dashboard-secondary">
    <summary>Recent machine activity</summary>
    <table class="tbl" id="feed-tbl">
      <thead><tr><th>Time</th><th>Emp #</th><th>Name</th></tr></thead>
      <tbody><tr><td colspan="3" class="muted">Loading…</td></tr></tbody>
    </table>
  </details>
</section>
<?php if(Auth::role()==='admin'): $backup=Shift::setting('backup_verified_at','');$overdue=!$backup || time()-strtotime($backup)>36*3600; ?>
<details class="dashboard-backup <?= $overdue?'backup-warning':'' ?>"><summary><?= $overdue?'Backup needs attention':'Backups are up to date' ?></summary><p>Last verified backup: <?= $backup?e($backup):'Not recorded' ?>.</p><p class="muted">Daily backups run while your Windows account is signed in. If overdue, check Attendance Daily Backup in Windows Task Scheduler.</p></details>
<?php endif; ?>

<script>window.ATT_BASE = <?= json_encode(APP_BASE_URL) ?>;</script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
