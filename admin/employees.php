<?php
$active = 'employees'; $pageTitle = 'Employees';
require_once dirname(__DIR__) . '/includes/header.php';

$msg = '';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'',['archive','restore','remove_device'],true))try {
 $empNo=(string)($_POST['emp_no']??'');
 if($_POST['action']==='remove_device'){
  if(!Database::q('SELECT 1 FROM employees WHERE emp_no=?',[$empNo])->fetchColumn())throw new InvalidArgumentException('Employee not found.');
  if(!EmployeeSync::accepted((new IsapiClient())->deleteUser($empNo)))throw new RuntimeException('Device removal failed. Check the connection and retry.');
  Database::q('UPDATE employees SET on_device=0 WHERE emp_no=?',[$empNo]);Database::q('DELETE FROM settings WHERE k=?',[EmployeeSync::key($empNo)]);$msg='Device access removed. Attendance history is preserved.';
 }else{EmployeeProfile::archive($empNo,$_POST['action']==='archive');$msg=$_POST['action']==='archive'?'Employee archived. History is preserved; device access is unchanged.':'Employee restored as active.';}
}catch(Throwable $ex){$error=$ex->getMessage();}
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='sync') {
 $person=Database::q('SELECT * FROM employees WHERE emp_no=?',[(string)($_POST['emp_no']??'')])->fetch();
 $msg=$person?(EmployeeSync::run($person)?'Employee synced successfully.':'Sync failed. Employee details remain saved in the app. Check the device connection and retry.'):'Employee not found.';
}
$department=(string)($_GET['department']??'');$shiftFilter=(string)($_GET['shift']??'');$state=(string)($_GET['state']??'');
$archived=($_GET['archived']??'')==='1';
$where=['(e.name LIKE ? OR e.emp_no LIKE ?)'];
$search=trim((string)($_GET['q']??''));
$params=['%'.$search.'%','%'.$search.'%'];
$where[]=$archived?'p.archived_at IS NOT NULL':'p.archived_at IS NULL';
if($department!==''){$where[]='e.department=?';$params[]=$department;}
if($shiftFilter==='default')$where[]='e.shift_id IS NULL';
elseif(ctype_digit($shiftFilter)){$where[]='e.shift_id=?';$params[]=(int)$shiftFilter;}
if(in_array($state,['0','1'],true)){$where[]='e.active=?';$params[]=(int)$state;}
$rows = Database::q(
    "SELECT e.*, p.archived_at, s.name AS shift_name,
            (SELECT COUNT(*) FROM attendance a WHERE a.emp_no=e.emp_no) AS days
     FROM employees e LEFT JOIN employee_profiles p ON p.emp_no=e.emp_no LEFT JOIN shifts s ON s.id=e.shift_id
     WHERE ".implode(" AND ",$where)." ORDER BY e.name",$params
)->fetchAll();
?>
<h1>Employees</h1>
<?php if($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<nav class="view-tabs"><a href="employees.php" <?= !$archived?'aria-current="page"':'' ?>>Current employees</a><a href="?archived=1" <?= $archived?'aria-current="page"':'' ?>>Archived</a><?php if(Auth::role()!=='viewer'): ?><a href="employee-import.php">Import employees</a><?php endif; ?></nav><p class="page-description">Manage employee details, shift assignments and attendance history.</p>
<?php if(isset($_SESSION['employee_notice'])): ?><div class="alert ok" role="status"><?= e($_SESSION['employee_notice']) ?></div><?php unset($_SESSION['employee_notice']); endif; ?>
<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
<?php if (Auth::role()!=='viewer'): ?><p><a class="btn" href="<?= APP_BASE_URL ?>/admin/enroll.php">+ Add employee</a></p><?php endif; ?>

<form class="command-search" method="get"><input type="hidden" name="archived" value="<?= $archived?1:0 ?>"><label for="employee-search">Find an employee</label><input id="employee-search" name="q" value="<?= e($search) ?>" placeholder="Name or employee number"><button class="btn ghost">Search</button><a href="employees.php">Clear</a>
<details class="people-filters" <?= $department!==''||$shiftFilter!==''||$state!==''?'open':'' ?>><summary>Filters</summary><div class="filters">
<label>Department<select name="department"><option value="">All departments</option><?php foreach(Database::q("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department<>'' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN) as $dept): ?><option value="<?= e($dept) ?>" <?= $department===$dept?'selected':'' ?>><?= e($dept) ?></option><?php endforeach; ?></select></label>
<label>Shift<select name="shift"><option value="">All shifts</option><option value="default" <?= $shiftFilter==='default'?'selected':'' ?>>Default</option><?php foreach(Shift::all() as $sh): ?><option value="<?= (int)$sh['id'] ?>" <?= $shiftFilter===(string)$sh['id']?'selected':'' ?>><?= e($sh['name']) ?></option><?php endforeach; ?></select></label>
<label>Status<select name="state"><option value="">All employees</option><option value="1" <?= $state==='1'?'selected':'' ?>>Active</option><option value="0" <?= $state==='0'?'selected':'' ?>>Inactive</option></select></label><button class="btn">Apply filters</button></div></details></form>
<div class="table-scroll"><table class="tbl">
  <thead><tr><th>Employee</th><th>Department</th><th>Shift</th><th>Device sync</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="5" class="muted">No employees match this view. Clear your search or add an employee.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a class="employee-identity" href="employee.php?emp=<?= e(rawurlencode($r['emp_no'])) ?>"><?= EmployeePhoto::avatar($r) ?><span><?= e($r['name']) ?><small><?= e($r['emp_no']) ?></small></span></a></td>
      <td><?= e($r['department']) ?></td>
      <td><?= $r['shift_name'] ? e($r['shift_name']) : '<span class="muted">Default</span>' ?></td>
      <td><?= status_label(EmployeeSync::state($r)) ?></td>
      <td>
        <?php if(Auth::role()!=='viewer'): ?><a data-edit-panel class="btn sm ghost" href="employee-edit.php?emp=<?= e(rawurlencode($r['emp_no'])) ?>">Edit</a><?php endif; ?>
        <?php if (Auth::role() !== 'viewer'): ?><details class="row-menu"><summary aria-label="More actions for <?= e($r['name']) ?>">More</summary><?php if(Auth::role()==='admin'): ?><a class="btn sm danger" href="employee-delete.php?emp=<?= e(rawurlencode($r['emp_no'])) ?>">Delete employee</a><?php endif; ?><form method="post"><?= Auth::field() ?><input type="hidden" name="action" value="sync"><input type="hidden" name="emp_no" value="<?= e($r['emp_no']) ?>"><button class="btn sm ghost"><?= EmployeeSync::state($r)==='Sync failed'?'Retry sync':'Sync to device' ?></button></form><form method="post" onsubmit="return confirm('<?= $archived?'Restore this employee?':'Archive this employee? History and device access will be preserved.' ?>')">
          <input type="hidden" name="action" value="<?= $archived?'restore':'archive' ?>">
          <input type="hidden" name="emp_no" value="<?= e($r['emp_no']) ?>">
          <button class="btn sm ghost"><?= $archived?'Restore employee':'Archive employee' ?></button>
        <?= Auth::field() ?></form><form method="post" onsubmit="return confirm('Remove this employee from the attendance device? This is separate from archiving.')"><?= Auth::field() ?><input type="hidden" name="action" value="remove_device"><input type="hidden" name="emp_no" value="<?= e($r['emp_no']) ?>"><button class="btn sm danger">Remove device access</button></form></details><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
