<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();
$_SESSION['correction_csrf'] = $_SESSION['correction_csrf'] ?? bin2hex(random_bytes(32));
$error = ''; $saved = isset($_GET['saved']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 try {
  if (!hash_equals($_SESSION['correction_csrf'], (string)($_POST['csrf'] ?? ''))) throw new InvalidArgumentException('Session expired. Reload and try again.');
  if (isset($_POST['decision'])) {
   Correction::review((int)($_POST['id'] ?? 0), (string)$_POST['decision'], (int)$_SESSION['admin_id'], Auth::user(), trim($_POST['review_note'] ?? ''));
   $_SESSION['correction_notice']=$_POST['decision']==='approved'?'Correction approved; attendance updated.':'Correction rejected; attendance unchanged.';
   header('Location: ' . APP_BASE_URL . '/admin/corrections.php?saved=1'); exit;
  }
  $emp = trim($_POST['emp'] ?? ''); $day = $_POST['day'] ?? ''; $reason = trim($_POST['reason'] ?? '');
  $in = str_replace('T', ' ', $_POST['check_in'] ?? ''); $out = str_replace('T', ' ', $_POST['check_out'] ?? '');
  $valid = static function($value, $format) { $d = DateTimeImmutable::createFromFormat('!' . $format, $value); return $d && $d->format($format) === $value; };
  if (!$valid($day, 'Y-m-d') || !$valid($in, 'Y-m-d H:i') || ($out !== '' && !$valid($out, 'Y-m-d H:i'))) throw new InvalidArgumentException('Enter valid dates and times.');
  if (strlen($reason) < 5 || strlen($reason) > 1000) throw new InvalidArgumentException('Provide a reason between 5 and 1000 characters.');
  if (!Database::q('SELECT id FROM employees WHERE emp_no=?', [$emp])->fetchColumn()) throw new InvalidArgumentException('Employee number was not found.');
  if ($out !== '' && (strtotime($out) <= strtotime($in) || strtotime($out)-strtotime($in)>86400)) throw new InvalidArgumentException('Check-out must follow check-in and be within 24 hours.');
  $shift = Shift::forEmployee($emp,$day);
  if (Shift::businessDate($in, $shift) !== $day) throw new InvalidArgumentException('Check-in does not belong to the selected shift date.');
  if ($out !== '' && $out >= Shift::window($day,$shift)[1]) throw new InvalidArgumentException('Check-out must be before the next shift-day boundary.');
  $sessions=[['in'=>$in,'out'=>$out?:null]];
  foreach($_POST['session_in']??[] as $i=>$extraIn) {
   $extraIn=str_replace('T',' ',trim($extraIn));$extraOut=str_replace('T',' ',trim($_POST['session_out'][$i]??''));
   if($extraIn==='' && $extraOut==='') continue;
   if(!$valid($extraIn,'Y-m-d H:i') || ($extraOut!=='' && !$valid($extraOut,'Y-m-d H:i'))) throw new InvalidArgumentException('Enter valid dates and times for every session.');
   $previous=$sessions[count($sessions)-1];
   if(!$previous['out'] || $extraIn<=$previous['out']) throw new InvalidArgumentException('Sessions must be in time order without overlapping; only the last session can have no check-out.');
   if(Shift::businessDate($extraIn,$shift)!==$day || ($extraOut!=='' && ($extraOut<=$extraIn || $extraOut>=Shift::window($day,$shift)[1]))) throw new InvalidArgumentException('Each session must end after its start and stay within this shift day.');
   $sessions[]=['in'=>$extraIn,'out'=>$extraOut?:null];
  }
  if(count($sessions)>30) throw new InvalidArgumentException('Use no more than 30 sessions per day.');
  $out=$sessions[count($sessions)-1]['out'];
  Database::q("INSERT INTO attendance_corrections (emp_no,work_date,check_in,check_out,reason,admin_id,admin_user,previous_json,approval_status,sessions_json) VALUES (?,?,?,?,?,?,?,?, 'pending',?)", [$emp,$day,$in,$out ?: null,$reason,$_SESSION['admin_id'],Auth::user(),'{}',json_encode($sessions)]);
  $_SESSION['correction_notice']='Correction submitted for review.';
  header('Location: ' . APP_BASE_URL . '/admin/corrections.php?saved=1'); exit;
 } catch (InvalidArgumentException $ex) { $error = $ex->getMessage(); }
 catch (Throwable $ex) { error_log('Correction: '.$ex->getMessage()); $error = 'Correction could not be saved. Please try again.'; }
}
$notice=$_SESSION['correction_notice']??''; unset($_SESSION['correction_notice']);
$values=$_POST;
if($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['emp'],$_GET['day'])) {
 $existing=Database::q('SELECT * FROM attendance WHERE emp_no=? AND work_date=?',[(string)$_GET['emp'],(string)$_GET['day']])->fetch();
 if($existing) $values=['emp'=>$existing['emp_no'],'day'=>$existing['work_date'],'check_in'=>substr(str_replace(' ','T',$existing['check_in']??''),0,16),'check_out'=>substr(str_replace(' ','T',$existing['check_out']??''),0,16)];
}
$extraSessions=[];
if(isset($existing) && $existing) {
 $allSessions=json_decode($existing['sessions_json']??'[]',true)?:[];
 if($allSessions) { $values['check_in']=substr(str_replace(' ','T',$allSessions[0]['in']),0,16);$values['check_out']=substr(str_replace(' ','T',$allSessions[0]['out']??''),0,16);$extraSessions=array_slice($allSessions,1); }
}
if($_SERVER['REQUEST_METHOD']==='POST') { $extraSessions=[];foreach($_POST['session_in']??[] as $i=>$v)$extraSessions[]=['in'=>$v,'out'=>$_POST['session_out'][$i]??'']; }
$active='corrections'; $pageTitle='Attendance corrections';
require BASE_PATH . '/includes/header.php';
?>
<h1>Attendance corrections</h1>
<p class="muted">Enter each visit separately. Time between visits is excluded. The full proposed session list replaces the day only after approval.</p>
<p>Submit a correction for review by another administrator. Attendance changes only after approval. Original scans remain unchanged.</p>
<?php if ($notice): ?><div class="alert ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert err" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (Auth::role() !== 'viewer'): ?><form method="post" class="filters">
<input type="hidden" name="csrf" value="<?= e($_SESSION['correction_csrf']) ?>">
<?php foreach (['emp'=>['Employee (search name or number)','text'], 'day'=>['Shift date','date'], 'check_in'=>['Check-in','datetime-local'], 'check_out'=>['Check-out (optional)','datetime-local']] as $key=>$field): ?>
<label><?= e($field[0]) ?><input name="<?= e($key) ?>" type="<?= e($field[1]) ?>" <?= $key==='emp'?'list="correction-employees"':'' ?> value="<?= e($values[$key] ?? '') ?>" <?= $key!=='check_out' ? 'required' : '' ?>></label>
<?php endforeach; ?>
<fieldset id="extra-sessions"><legend>Additional work sessions</legend>
<?php foreach($extraSessions as $session): ?><div class="filters"><label>Session check-in <input type="datetime-local" name="session_in[]" value="<?= e(substr(str_replace(' ','T',$session['in']),0,16)) ?>"></label><label>Session check-out <input type="datetime-local" name="session_out[]" value="<?= e(substr(str_replace(' ','T',$session['out']??''),0,16)) ?>"></label><button type="button" class="btn ghost" onclick="this.parentElement.remove()">Remove session</button></div><?php endforeach; ?>
</fieldset>
<button type="button" class="btn ghost" id="add-session">Add another session</button>
<template id="session-template"><div class="filters"><label>Session check-in <input type="datetime-local" name="session_in[]"></label><label>Session check-out <input type="datetime-local" name="session_out[]"></label><button type="button" class="btn ghost" onclick="this.parentElement.remove()">Remove session</button></div></template>
<script>document.getElementById('add-session').addEventListener('click',()=>document.getElementById('extra-sessions').append(document.getElementById('session-template').content.cloneNode(true)));</script>
<label>Reason <input name="reason" required minlength="5" maxlength="1000" value="<?= e($_POST['reason'] ?? '') ?>"></label>
<button class="btn">Submit for approval</button>
<?= Auth::field() ?></form><?php endif; ?>
<datalist id="correction-employees"><?php foreach(Database::q('SELECT emp_no,name FROM employees ORDER BY name')->fetchAll() as $person): ?><option value="<?= e($person['emp_no']) ?>"><?= e($person['name']) ?></option><?php endforeach; ?></datalist>
<h2 id="history">Corrections and approval history</h2>
<?php
$historyWhere=[];$historyParams=[];
if(!empty($_GET['emp'])){$historyWhere[]='emp_no=?';$historyParams[]=(string)$_GET['emp'];}
if(!empty($_GET['day'])){$historyWhere[]='work_date=?';$historyParams[]=(string)$_GET['day'];}
$history=Database::q("SELECT * FROM attendance_corrections ".($historyWhere?'WHERE '.implode(' AND ',$historyWhere):'')." ORDER BY (approval_status='pending') DESC, id DESC LIMIT 100",$historyParams)->fetchAll();
?>
<?php if($historyWhere): ?><p>History for the selected employee/date. <a href="corrections.php#history">Show all corrections</a></p><?php endif; ?>
<?php if (!$history): ?><p>No corrections yet.</p><?php endif; ?>
<?php foreach ($history as $row): ?>
<section class="correction-record">
<h3><?= e($row['emp_no']) ?> &middot; <?= e($row['work_date']) ?> &middot; <?= status_label($row['approval_status']) ?></h3>
<?php $original=$row['approval_status']==='pending' ? Database::q('SELECT check_in,check_out,sessions_json FROM attendance WHERE emp_no=? AND work_date=?',[$row['emp_no'],$row['work_date']])->fetch() : json_decode($row['previous_json'],true); ?>
<div class="comparison"><div><strong><?= $row['approval_status']==='pending'?'Current attendance':'Before review' ?></strong><p><?= e($original['check_in']??'No check-in') ?> to <?= e($original['check_out']??'No check-out') ?></p><?php foreach(json_decode($original['sessions_json']??'[]',true)?:[] as $visit): ?><p><?= e($visit['in']) ?> to <?= e($visit['out']??'No check-out') ?></p><?php endforeach; ?></div><div><strong>Proposed attendance</strong><p>Requested: <?= e($row['check_in']) ?> to <?= e($row['check_out'] ?? 'No check-out') ?></p></div></div>
<?php if(!empty($row['sessions_json'])): ?><ol><?php foreach(json_decode($row['sessions_json'],true) as $session): ?><li><?= e($session['in']) ?> to <?= e($session['out']??'No check-out') ?></li><?php endforeach; ?></ol><?php endif; ?>
<p><?= e($row['reason']) ?> &mdash; <?= e($row['admin_user']) ?>, <?= e($row['created_at']) ?></p>
<?php if ($row['approval_status']==='pending'): ?>
<?php if ((int)$row['admin_id'] !== (int)$_SESSION['admin_id']): ?>
<?php if (Auth::role() !== 'viewer'): ?><form method="post" class="filters">
<input type="hidden" name="csrf" value="<?= e($_SESSION['correction_csrf']) ?>">
<input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<label>Review reason <input name="review_note" required minlength="5" maxlength="1000"></label>
<button class="btn" name="decision" value="approved">Approve correction</button>
<button class="btn ghost" name="decision" value="rejected">Reject correction</button>
<?= Auth::field() ?></form><?php endif; ?>
<?php else: ?><p class="muted">Awaiting review by another administrator.</p><?php endif; ?>
<?php else: ?>
<p>Reviewed by <?= e($row['reviewed_by']) ?> at <?= e($row['reviewed_at']) ?>: <?= e($row['review_note'] ?? 'Legacy administrator approval') ?></p>
<?php $before=json_decode($row['previous_json'],true); ?>
<p class="muted">Previous attendance: <?= e($before['check_in'] ?? 'No check-in') ?> to <?= e($before['check_out'] ?? 'No check-out') ?></p>
<?php endif; ?>
</section>
<?php endforeach; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
