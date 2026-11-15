<?php
require_once dirname(__DIR__).'/includes/bootstrap.php';
$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST')try{Overtime::review((string)$_POST['emp'],(string)$_POST['day'],(int)$_POST['minutes'],(string)$_POST['decision'],(string)$_POST['reason'],(string)$_POST['version']);$notice=$_POST['decision']==='approved'?'Overtime approved.':'Overtime rejected.';}catch(InvalidArgumentException $e){$error=$e->getMessage();}catch(Throwable $e){error_log('Overtime review: '.$e->getMessage());$error='Could not save review. Try again.';}
$active='overtime';$pageTitle='Overtime approval';require BASE_PATH.'/includes/header.php';
$views=['pending'=>'Needs review','approved'=>'Approved','rejected'=>'Rejected'];
$view=is_string($_GET['view']??null) && isset($views[$_GET['view']]) ? $_GET['view'] : 'pending';
$rows=[];$counts=array_fill_keys(array_keys($views),0);
$records=Database::q('SELECT a.*,COALESCE(e.name,a.emp_no) name,r.attendance_hash review_hash,r.decision review_decision,r.approved_minutes review_minutes FROM attendance a LEFT JOIN employees e ON e.emp_no=a.emp_no LEFT JOIN overtime_reviews r ON r.id=(SELECT MAX(h.id) FROM overtime_reviews h WHERE h.emp_no=a.emp_no AND h.work_date=a.work_date) WHERE a.overtime_minutes>0 ORDER BY a.work_date DESC,a.emp_no');
$employeeFilter=trim((string)($_GET['emp']??''));
while($record=$records->fetch()) {
    if($employeeFilter!=='' && $record['emp_no']!==$employeeFilter)continue;
    $valid=$record['review_hash'] && hash_equals($record['review_hash'],Overtime::fingerprint($record));
    $bucket=$valid && isset($views[$record['review_decision']]) ? $record['review_decision'] : 'pending';
    $counts[$bucket]++;
    if($bucket===$view && count($rows)<100) $rows[]=$record;
}
?>
<h1>Overtime approval</h1><?php if($employeeFilter!==''): ?><p>Showing employee <?= e($employeeFilter) ?> · <a href="overtime.php">Show all employees</a></p><?php endif; ?><p>Approve or reject pending overtime. Completed decisions stay in their own tabs; changed attendance returns to Needs review.</p>
<?php if($notice): ?><div class="alert ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert err" role="alert"><?= e($error) ?></div><?php endif; ?>
<nav class="schedule-nav" aria-label="Overtime status">
<?php foreach($views as $key=>$label): ?><a href="?<?= e(http_build_query(['view'=>$key,'emp'=>$employeeFilter])) ?>" <?= $view===$key?'aria-current="page"':'' ?>><?= e($label) ?> (<?= $counts[$key] ?>)</a><?php endforeach; ?>
</nav>
<?php if($counts[$view]>100): ?><p class="muted">Showing the latest 100 of <?= $counts[$view] ?> records.</p><?php endif; ?>
<?php if(!$rows): ?><p><?= $view==='pending'?'All overtime reviewed. New or changed overtime will appear here.':'No '.e($view).' overtime records.' ?></p><?php endif; ?>
<?php foreach($rows as $r): $valid=$view!=='pending';$review=['decision'=>$r['review_decision'],'approved_minutes'=>$r['review_minutes']]; ?>
<section class="correction-record"><h2><?= e($r['name']) ?> · <?= e($r['work_date']) ?></h2><p>Calculated: <?= Attendance::fmtMinutes((int)$r['overtime_minutes']) ?> · <?= $valid ? status_label($review['decision']) . ($review['decision']==='approved' ? ' · '.Attendance::fmtMinutes((int)$review['approved_minutes']) : '') : status_label('Needs review') ?></p>
<?php if(Auth::role()!=='viewer'): ?><?php if($valid): ?><details><summary>Change decision</summary><p class="muted">Enter a reason for the change. The previous decision remains in review history.</p><?php endif; ?><form method="post" class="filters" data-overtime-review><?= Auth::field() ?><input type="hidden" name="emp" value="<?= e($r['emp_no']) ?>"><input type="hidden" name="day" value="<?= e($r['work_date']) ?>"><input type="hidden" name="version" value="<?= Overtime::fingerprint($r) ?>"><label>Minutes to approve <input type="number" name="minutes" min="0" max="<?= (int)$r['overtime_minutes'] ?>" value="<?= (int)$r['overtime_minutes'] ?>" required></label><label>Review reason (required)<input name="reason" minlength="5" maxlength="1000" placeholder="At least 5 characters" value="<?= $error && ($_POST['emp']??'')===$r['emp_no'] && ($_POST['day']??'')===$r['work_date'] ? e((string)($_POST['reason']??'')) : '' ?>" required></label><button class="btn" name="decision" value="approved">Approve</button><button class="btn ghost" name="decision" value="rejected" formnovalidate>Reject</button><p class="overtime-feedback" role="alert" hidden></p></form><?php if($valid): ?></details><?php endif; ?><?php endif; ?>
<details><summary>Review history</summary><?php foreach(Database::q('SELECT * FROM overtime_reviews WHERE emp_no=? AND work_date=? ORDER BY id DESC',[$r['emp_no'],$r['work_date']])->fetchAll() as $h): ?><p><?= e($h['created_at']) ?> · <?= e($h['reviewer']) ?> · <?= status_label($h['decision']) ?> · <?= (int)$h['approved_minutes'] ?> min: <?= e($h['reason']) ?></p><?php endforeach; ?></details></section>
<?php endforeach; require BASE_PATH.'/includes/footer.php'; ?>
