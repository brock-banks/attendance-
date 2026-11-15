<?php
$active = 'reports'; $pageTitle = 'Reports';
require_once dirname(__DIR__) . '/includes/header.php';

$input=$_GET;
if(isset($_GET['load_saved'])) {$saved=Database::q('SELECT filters_json FROM report_filters WHERE admin_id=?',[$_SESSION['admin_id']])->fetchColumn();if($saved)$input=json_decode($saved,true);}
$filters=Report::filters($input);
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_filters') Database::q('INSERT INTO report_filters(admin_id,filters_json) VALUES(?,?) ON DUPLICATE KEY UPDATE filters_json=VALUES(filters_json)',[$_SESSION['admin_id'],json_encode($filters)]);
extract($filters);$totalRows=Report::count($filters);$pages=max(1,(int)ceil($totalRows/50));$page=max(1,min($pages,(int)($_GET['page']??1)));
$rows=Report::rows($filters,$page);
$totalMin=array_sum(array_column($rows,'work_minutes'));
$qs=http_build_query($filters);

$view=($_GET['view']??'table')==='timeline'?'timeline':'table';
$link=static fn($changes=[])=>'?'.http_build_query(array_merge($filters,['view'=>$view],$changes));
?>
<h1>Attendance report</h1>
<?php if($_SERVER['REQUEST_METHOD']==='POST'): ?><div class="alert ok" role="status">Your filters were saved.</div><?php endif; ?>
<div class="report-toolbar">
<nav class="view-tabs" aria-label="Report view"><a href="<?= e($link(['view'=>'table'])) ?>" <?= $view==='table'?'aria-current="page"':'' ?>>Table</a><a href="<?= e($link(['view'=>'timeline'])) ?>" <?= $view==='timeline'?'aria-current="page"':'' ?>>Timeline</a></nav>
<span class="report-range"><?= e(date('d M Y',strtotime($from))) ?> – <?= e(date('d M Y',strtotime($to))) ?></span>
<button class="btn ghost" id="toggle-report-filters" aria-expanded="false" aria-controls="report-filters"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 4h14l-5.5 6v6l-3-1v-5z"/></svg>Filters<?= ($emp!==''||$department!==''||$status!=='')?' •':'' ?></button>
<a class="btn ghost" href="<?= APP_BASE_URL ?>/admin/export.php?<?= e($qs) ?>">Export</a>
</div>
<section id="report-filters" class="report-filter-panel" hidden aria-label="Report filters">
<nav class="quick-filters" aria-label="Quick date ranges"><?php foreach(['Today'=>[date('Y-m-d'),date('Y-m-d')],'Yesterday'=>[date('Y-m-d',strtotime('-1 day')),date('Y-m-d',strtotime('-1 day'))],'This week'=>[date('Y-m-d',strtotime('monday this week')),date('Y-m-d')],'This month'=>[date('Y-m-01'),date('Y-m-d')]] as $label=>$range): ?><a class="btn ghost sm" href="<?= e($link(['from'=>$range[0],'to'=>$range[1],'page'=>1])) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
<form class="filters" method="get"><input type="hidden" name="view" value="<?= e($view) ?>">
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <label>Employee <input name="emp" value="<?= e($emp) ?>" placeholder="Search name or number" list="report-employees"></label>
<datalist id="report-employees"><?php foreach(Database::q('SELECT emp_no,name FROM employees ORDER BY name')->fetchAll() as $person): ?><option value="<?= e($person['emp_no']) ?>"><?= e($person['name']) ?></option><?php endforeach; ?></datalist>
<label>Department <select name="department"><option value="">All departments</option><?php foreach(Database::q("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department<>'' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN) as $dept): ?><option <?= $department===$dept?'selected':'' ?>><?= e($dept) ?></option><?php endforeach; ?></select></label>
<label>Status <select name="status"><option value="">All statuses</option><?php foreach(['present','late','half','Still working','Missing check-out'] as $st): ?><option <?= $status===$st?'selected':'' ?>><?= e($st) ?></option><?php endforeach; ?></select></label>
<label>Sort by <select name="sort"><?php foreach(['date'=>'Date','employee'=>'Employee','worked'=>'Worked time','overtime'=>'Overtime'] as $value=>$label): ?><option value="<?= $value ?>" <?= $sort===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label>Order <select name="direction"><option value="desc" <?= $direction==='desc'?'selected':'' ?>>Descending</option><option value="asc" <?= $direction==='asc'?'selected':'' ?>>Ascending</option></select></label>
  <button class="btn">Apply filters</button>

<a class="btn ghost" href="reports.php">Reset filters</a></form>
<div class="saved-filter-actions"><a href="?load_saved=1&amp;view=<?= e($view) ?>">Load saved filters</a><form method="post" action="<?= e($link()) ?>"><?= Auth::field() ?><input type="hidden" name="action" value="save_filters"><button class="btn ghost sm">Save current filters</button></form></div>
</section>
<p class="muted report-count"><?= $totalRows ?> records · Page <?= $page ?> of <?= $pages ?><?php if($emp!=='' || $department!=='' || $status!==''): ?> · Filtered results<?php endif; ?></p>
<?php if($view==='table'): ?>
<div class="table-scroll"><table class="tbl compact-report"><thead><tr><th>Date</th><th>Name</th><th>In</th><th>Out</th><th>Worked</th><th>OT</th><th>Status</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="7">No attendance matches this view. Open Filters to change the dates or employee.</td></tr><?php endif; ?>
<?php foreach($rows as $r): $state=!$r['check_out']?$r['checkout_state']:(!empty($r['review_required'])?'Review punches':ucfirst($r['status'])); ?>
<tr data-attendance-row><td><a data-attendance-panel href="attendance-detail.php?<?= e(http_build_query(['emp'=>$r['emp_no'],'day'=>$r['work_date']])) ?>"><?= e(date('d M Y',strtotime($r['work_date']))) ?></a></td><td><a href="employee.php?emp=<?= e(rawurlencode($r['emp_no'])) ?>"><?= e($r['name']) ?></a></td><td><?= $r['check_in']?e(date('H:i',strtotime($r['check_in']))):'—' ?></td><td><?= $r['check_out']?e(date('H:i',strtotime($r['check_out']))):'—' ?></td><td><?= Attendance::fmtMinutes((int)$r['work_minutes']) ?></td><td title="Calculated overtime; approved <?= e(Attendance::fmtMinutes($r['approved_overtime_minutes'])) ?>"><?= $r['overtime_minutes']?Attendance::fmtMinutes((int)$r['overtime_minutes']):'—' ?></td><td><?= status_label($state) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php else: ?>
<p class="muted timeline-legend"><span class="legend-work"></span> Recorded work sessions <span class="legend-gap"></span> Time away · Times follow each shift day; overnight dates are shown in details.</p>
<div class="attendance-timeline">
<?php if(!$rows): ?><div class="panel">No attendance matches this view. Open Filters to change the dates or employee.</div><?php endif; ?>
<?php foreach($rows as $r):
$shift=!empty($r['shift_snapshot'])?json_decode($r['shift_snapshot'],true):Shift::forEmployee($r['emp_no'],$r['work_date']);
[$start,$end]=Shift::window($r['work_date'],$shift);$base=strtotime($start);$span=max(1,strtotime($end)-$base);
$sessions=json_decode($r['sessions_json']??'[]',true)?:[];
?>
<article class="timeline-record">
<div class="timeline-date"><strong><?= e(date('d',strtotime($r['work_date']))) ?></strong><span><?= e(date('M · D',strtotime($r['work_date']))) ?></span></div>
<div class="timeline-content"><div class="timeline-heading"><a href="employee.php?emp=<?= e(rawurlencode($r['emp_no'])) ?>"><?= e($r['name']) ?></a><span class="muted"><?= status_label(!$r['check_out']?$r['checkout_state']:$r['status']) ?><?= !empty($r['review_required'])?' · '.status_label('Review punches'):'' ?></span></div>
<div class="timeline-session-row"><span><?= $r['check_in']?e(date('H:i',strtotime($r['check_in']))):'—' ?></span><div class="session-track" aria-label="Recorded sessions for <?= e($r['name'].' on '.$r['work_date']) ?>">
<?php foreach($sessions as $visit): $left=max(0,min(100,100*(strtotime($visit['in'])-$base)/$span));$right=max($left,min(100,100*(strtotime($visit['out'])-$base)/$span)); ?><span class="session-bar" style="left:<?= $left ?>%;width:<?= max(.3,$right-$left) ?>%" title="<?= e($visit['in'].' to '.$visit['out']) ?>"></span><?php endforeach; ?>
<?php if(!$sessions): ?><span class="track-note">No completed session</span><?php endif; ?></div><span><?= $r['check_out']?e(date('H:i',strtotime($r['check_out']))):'No check-out' ?></span><div class="timeline-total"><strong><?= Attendance::fmtMinutes((int)$r['work_minutes']) ?></strong><small>Worked<?= $r['overtime_minutes']?' · OT '.Attendance::fmtMinutes((int)$r['overtime_minutes']):'' ?></small></div></div>
<p><a data-attendance-panel href="attendance-detail.php?<?= e(http_build_query(['emp'=>$r['emp_no'],'day'=>$r['work_date']])) ?>">Attendance details</a></p><details class="timeline-details"><summary>View sessions<?= Auth::role()!=='viewer'?' or correct times':'' ?></summary><p class="muted">Timeline range: <?= e($start) ?> – <?= e($end) ?>. Worked totals include the configured unpaid-break deduction.</p><?php foreach($sessions as $visit): ?><p><?= e($visit['in']) ?> → <?= e($visit['out']) ?></p><?php endforeach; ?><?php if(Auth::role()!=='viewer'): ?><a href="corrections.php?<?= e(http_build_query(['emp'=>$r['emp_no'],'day'=>$r['work_date']])) ?>">Request correction</a><?php endif; ?></details>
</div></article>
<?php endforeach; ?></div>
<?php endif; ?>
<nav class="quick-filters" aria-label="Report pages"><?php if($page>1): ?><a class="btn ghost" href="<?= e($link(['page'=>$page-1])) ?>">Previous 50</a><?php endif; ?><?php if($page<$pages): ?><a class="btn ghost" href="<?= e($link(['page'=>$page+1])) ?>">Next 50</a><?php endif; ?></nav>
<script>document.getElementById('toggle-report-filters').addEventListener('click',function(){const panel=document.getElementById('report-filters');panel.hidden=!panel.hidden;this.setAttribute('aria-expanded',String(!panel.hidden));});</script>
<?php require_once BASE_PATH.'/includes/footer.php'; ?>
