<?php
require_once dirname(__DIR__).'/includes/bootstrap.php';
Auth::requireRole(['admin','hr']);
$emp=trim((string)($_GET['emp']??''));
$employee=Database::q('SELECT * FROM employees WHERE emp_no=?',[$emp])->fetch();
if(!$employee) { http_response_code(404); $active='employees';$pageTitle='Employee not found'; require BASE_PATH.'/includes/header.php'; echo '<h1>Employee not found</h1><p><a href="employees.php">Back to employees</a></p>';require BASE_PATH.'/includes/footer.php';exit; }
$profile=EmployeeProfile::get($emp);
$error='';$values=$employee;
if($_SERVER['REQUEST_METHOD']==='POST') {
 $values=array_merge($employee,['name'=>trim((string)($_POST['name']??'')),'department'=>trim((string)($_POST['department']??'')),'card_no'=>trim((string)($_POST['card_no']??'')),'shift_id'=>$_POST['shift_id']??'','active'=>!empty($profile['archived_at'])?0:(isset($_POST['active'])?1:0)]);
 try {
  $profile=EmployeeProfile::validate($_POST);
  if($values['name']==='' || mb_strlen($values['name'])>128) throw new InvalidArgumentException('Enter a name of 1 to 128 characters.');
  if(mb_strlen($values['department'])>128 || mb_strlen($values['card_no'])>64) throw new InvalidArgumentException('Department must be at most 128 characters and card number at most 64.');
  $shift=$values['shift_id']===''?null:filter_var($values['shift_id'],FILTER_VALIDATE_INT);
  if($values['shift_id']!=='' && (!$shift || !Shift::get($shift))) throw new InvalidArgumentException('Choose a valid shift.');
  $photoPath=EmployeePhoto::upload($_FILES['photo']??[]) ?? (isset($_POST['remove_photo'])?null:$employee['photo_path']);
  if((string)$shift!==(string)$employee['shift_id']) Shift::assign($emp,$shift,(string)($_POST['effective_date']??date('Y-m-d',strtotime('+1 day'))));
  Database::q('UPDATE employees SET name=?,department=?,card_no=?,shift_id=?,active=?,photo_path=? WHERE emp_no=?',[$values['name'],$values['department']?:null,$values['card_no']?:null,$shift,$values['active'],$photoPath,$emp]);
  EmployeeProfile::save($emp,$profile);
  $_SESSION['employee_notice']='Employee details saved. Existing attendance totals are unchanged. Device details have not been synced.';
  if(isset($_GET['panel'])) { echo '<!doctype html><title>Employee saved</title><p>Employee saved.</p><script>parent.postMessage({type:"employee-saved"},location.origin);</script>';exit; }
  header('Location: '.APP_BASE_URL.'/admin/employees.php');exit;
 } catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
 catch(Throwable $ex) { error_log('Employee edit: '.$ex->getMessage());$error='Could not save employee details. Please try again.'; }
}
$active='employees';$pageTitle='Edit employee';require BASE_PATH.'/includes/header.php';
?>
<p><a href="employees.php">Back to employees</a></p>
<h1>Edit employee</h1>
<p>Employee number: <strong><?= e($emp) ?></strong>. This identifier stays fixed to preserve attendance links.</p>
<?php if($error): ?><div class="alert err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="panel form employee-form" enctype="multipart/form-data">
<?= Auth::field() ?>
<div class="employee-identity"><?= EmployeePhoto::avatar($employee) ?><span><?= e($employee['name']) ?></span></div>
<label>Photo <span class="muted">Optional</span><input type="file" name="photo" accept="image/jpeg,image/png" data-photo-input aria-describedby="photo-help"></label>
<p id="photo-help" class="muted">JPG or PNG, up to 5 MB. Leave empty to keep the current photo.</p>
<img class="employee-photo-preview" data-photo-preview alt="Selected employee photo" hidden>
<?php if($employee['photo_path']): ?><label class="employee-check"><input type="checkbox" name="remove_photo"> Remove current photo</label><?php endif; ?>
<?= EmployeeProfile::fields($_SERVER['REQUEST_METHOD']==='POST'?$_POST:$profile) ?>
<label>Full name <input name="name" required maxlength="128" value="<?= e($values['name']) ?>"></label>
<details class="schedule-disclosure" <?= $error ? 'open' : '' ?>><summary>Department and shift</summary><label>Department <input name="department" maxlength="128" value="<?= e($values['department']) ?>"></label>
<label>Shift <select name="shift_id"><option value="">Default shift</option>
<?php foreach(Shift::all() as $shift): ?><option value="<?= (int)$shift['id'] ?>" <?= (string)$values['shift_id']===(string)$shift['id']?'selected':'' ?>><?= e($shift['name']) ?> (<?= e(substr($shift['start_time'],0,5)) ?>–<?= e(substr($shift['end_time'],0,5)) ?>)<?= !$shift['active']?' — inactive':'' ?></option><?php endforeach; ?></select></label>
<label>Shift effective date <input type="date" name="effective_date" min="<?= date('Y-m-d',strtotime('+1 day')) ?>" value="<?= e($_POST['effective_date']??date('Y-m-d',strtotime('+1 day'))) ?>"></label>
</details><details class="schedule-disclosure" <?= $error ? 'open' : '' ?>><summary>Card and employee status</summary><label>Card number <input name="card_no" maxlength="64" value="<?= e($values['card_no']) ?>"></label>
<label><input type="checkbox" name="active" <?= $values['active']?'checked':'' ?>> Active employee</label>
<p class="muted">Inactive employees are excluded from active headcounts and scheduling. This does not revoke terminal access. Saving updates this system only; terminal name and card details require a separate device sync. Shift changes begin on the effective date. Previously recorded shift rules remain unchanged.</p>
</details><div><button class="btn">Save changes</button> <a class="btn ghost" href="employees.php" <?= isset($_GET['panel'])?'onclick="parent.postMessage({type: \'employee-cancel\'},location.origin);return false;"':'' ?>>Cancel</a></div>
</form>
<?php require BASE_PATH.'/includes/footer.php'; ?>
