<?php
require_once dirname(__DIR__).'/includes/bootstrap.php';
$emp=(string)($_GET['emp']??'');$day=(string)($_GET['day']??'');
$row=Database::q('SELECT a.*,COALESCE(e.name,a.emp_no) name FROM attendance a LEFT JOIN employees e ON e.emp_no=a.emp_no WHERE a.emp_no=? AND a.work_date=?',[$emp,$day])->fetch();
if(!$row)http_response_code(404);
$active='reports';$pageTitle='Attendance details';require BASE_PATH.'/includes/header.php';
if(!$row){echo '<h1>Attendance not found</h1>';require BASE_PATH.'/includes/footer.php';exit;}
$sessions=json_decode($row['sessions_json']??'[]',true)?:[];$sessionMinutes=0;$gapMinutes=0;$last=null;
foreach($sessions as $visit){if(empty($visit['out']))continue;$in=strtotime($visit['in']);$out=strtotime($visit['out']);$sessionMinutes+=max(0,($out-$in)/60);if($last!==null)$gapMinutes+=max(0,($in-$last)/60);$last=$out;}
$review=Overtime::current($row);$valid=$review && hash_equals($review['attendance_hash'],Overtime::fingerprint($row));
?>
<h1><?= e($row['name']) ?></h1><p><?= e($day) ?> · <?= status_label($row['status']) ?></p>
<p><strong>Worked: <?= Attendance::fmtMinutes((int)$row['work_minutes']) ?></strong> · Calculated overtime: <?= Attendance::fmtMinutes((int)$row['overtime_minutes']) ?></p>
<p>Overtime decision: <?= $valid?status_label($review['decision']):status_label((int)$row['overtime_minutes']>0?'Needs review':'No overtime') ?><?= $valid && $review['decision']==='approved'?' · '.Attendance::fmtMinutes((int)$review['approved_minutes']):'' ?></p>
<h2>Work sessions</h2>
<?php if(!$sessions): ?><p>No completed work sessions. Review the punches before calculating worked time.</p><?php endif; ?>
<?php foreach($sessions as $visit): ?><p><?= e($visit['in']) ?> → <?= e($visit['out']??'No check-out') ?></p><?php endforeach; ?>
<p>Time between visits: <?= Attendance::fmtMinutes((int)$gapMinutes) ?> · Additional unpaid break deducted: <?= Attendance::fmtMinutes(max(0,(int)$sessionMinutes-(int)$row['work_minutes'])) ?></p>
<?php if(Auth::role()!=='viewer'): ?><p><a class="btn" target="_top" href="corrections.php?<?= e(http_build_query(['emp'=>$emp,'day'=>$day])) ?>">Request correction</a></p><?php endif; ?>
<h2>Correction history</h2>
<?php $history=Database::q('SELECT * FROM attendance_corrections WHERE emp_no=? AND work_date=? ORDER BY id DESC',[$emp,$day])->fetchAll();if(!$history): ?><p>No corrections for this day.</p><?php endif; foreach($history as $item): ?><p><?= status_label($item['approval_status']) ?> · <?= e($item['reason']) ?> · <?= e($item['reviewed_by']??'Awaiting review') ?></p><?php endforeach; ?>
<h2>Overtime history</h2>
<?php $history=Database::q('SELECT * FROM overtime_reviews WHERE emp_no=? AND work_date=? ORDER BY id DESC',[$emp,$day])->fetchAll();if(!$history): ?><p>No overtime decisions for this day.</p><?php endif; foreach($history as $item): ?><p><?= status_label($item['decision']) ?> · <?= e($item['reviewer']) ?> · <?= e($item['created_at']) ?> · <?= e($item['reason']) ?></p><?php endforeach; ?>
<?php require BASE_PATH.'/includes/footer.php'; ?>
