<?php
require dirname(__DIR__).'/includes/bootstrap.php';Auth::requireRole(['admin']);require BASE_PATH.'/src/EmployeeDeletion.php';
$emp=(string)($_GET['emp']??'');$person=Database::q('SELECT * FROM employees WHERE emp_no=?',[$emp])->fetch();$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try{if(!$person)throw new InvalidArgumentException('Employee not found.');if(trim((string)($_POST['confirm_emp']??''))!==$emp)throw new InvalidArgumentException('Type the employee number to confirm deletion.');EmployeeDeletion::remove($emp);$_SESSION['employee_notice']='Employee and related attendance data deleted. Terminal access was not changed.';header('Location: employees.php');exit;}catch(Throwable $ex){$error=$ex instanceof InvalidArgumentException?$ex->getMessage():'Deletion failed. No employee data was removed.';}
if(!$person)http_response_code(404);$active='employees';$pageTitle='Delete employee';require BASE_PATH.'/includes/header.php';
?>
<h1>Delete employee</h1><?php if($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<?php if(!$person): ?><p>Employee not found.</p><?php else: ?>
<div class="panel"><h2><?= e($person['name']) ?> · <?= e($emp) ?></h2><p>This permanently removes their profile, attendance, scans, leave, corrections, overtime decisions and shift history from this app. Archive instead if you need to retain history.</p><p>Terminal access is separate. Remove device access from the directory before deleting if needed; future terminal scans may otherwise appear as unmatched.</p><form method="post" class="form"><?= Auth::field() ?><label>Type employee number <?= e($emp) ?> to confirm<input name="confirm_emp" required autocomplete="off"></label><div><button class="btn danger">Permanently delete employee</button> <a class="btn ghost" href="employees.php">Cancel</a></div></form></div><?php endif; ?>
<?php require BASE_PATH.'/includes/footer.php'; ?>
