<?php
require dirname(__DIR__).'/includes/bootstrap.php';Auth::requireRole(['admin','hr']);require_once BASE_PATH.'/src/EmployeeImport.php';
if(isset($_GET['template'])){header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="employee-import-template.csv"');$out=fopen('php://output','w');fputcsv($out,EmployeeImport::COLUMNS,',','"','');fclose($out);exit;}
$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST')try{
 if(($_POST['action']??'')==='preview'){
  unset($_SESSION['employee_import']);$file=$_FILES['csv']??[];
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>2*1024*1024 || !is_uploaded_file($file['tmp_name']??''))throw new InvalidArgumentException('Choose a CSV file up to 2 MB.');
  $rows=EmployeeImport::preview($file['tmp_name']);$_SESSION['employee_import']=['rows'=>$rows,'token'=>bin2hex(random_bytes(16)),'at'=>time()];
 }elseif(($_POST['action']??'')==='confirm'){
  $preview=$_SESSION['employee_import']??null;if(!$preview || time()-$preview['at']>1800 || !hash_equals($preview['token'],(string)($_POST['preview_token']??'')))throw new InvalidArgumentException('The preview expired or was already imported. Upload the file again.');
  $count=EmployeeImport::commit($preview['rows']);unset($_SESSION['employee_import']);$notice="Added $count employees. They are saved in the app; device sync is separate.";
 }
}catch(InvalidArgumentException $ex){$error=$ex->getMessage();}catch(Throwable $ex){error_log('Employee import: '.$ex->getMessage());$error='Import could not complete. No employees were added. Refresh the preview and try again.';}
$active='import';$pageTitle='Import employees';require BASE_PATH.'/includes/header.php';$preview=$_SESSION['employee_import']??null;
?>
<h1>Import employees</h1><p>Upload a CSV UTF-8 spreadsheet exported from Excel. Only employee number and name are required. Up to 500 employees per import; new employees use the default shift.</p>
<?php if($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?><?php if($notice): ?><div class="alert ok"><?= e($notice) ?> <a href="employees.php">View employees</a></div><?php endif; ?>
<p><a class="btn ghost" href="?template=1">Download CSV template</a></p>
<form method="post" enctype="multipart/form-data" class="panel form"><?= Auth::field() ?><input type="hidden" name="action" value="preview"><label>Employee spreadsheet<input type="file" name="csv" accept=".csv,text/csv" required></label><button class="btn">Preview import</button></form>
<?php if($preview): $errors=count(array_filter($preview['rows'],static fn($r)=>$r['error']!=='')); ?>
<h2>Preview</h2><p><?= count($preview['rows']) ?> employees · <?= $errors ?> errors. Existing employees will never be overwritten.</p>
<div class="table-scroll"><table class="tbl"><thead><tr><th>Row</th><th>Employee number</th><th>Name</th><th>Details</th><th>Validation</th></tr></thead><tbody><?php foreach($preview['rows'] as $row): ?><tr><td><?= $row['line'] ?></td><td><?= e($row['data']['emp_no']??'') ?></td><td><?= e($row['data']['name']??'') ?></td><td><details><summary>View imported details</summary><?php foreach($row['data'] as $key=>$value): ?><p><?= e($key) ?>: <?= e($value) ?></p><?php endforeach; ?></details></td><td><?= $row['error']?e($row['error']):status_label('Ready') ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if(!$errors): ?><form method="post"><?= Auth::field() ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="preview_token" value="<?= e($preview['token']) ?>"><button class="btn">Confirm import of <?= count($preview['rows']) ?> employees</button></form><?php else: ?><p>Correct the flagged rows in your spreadsheet, then upload it again. Nothing has been imported.</p><?php endif; endif; ?>
<?php require BASE_PATH.'/includes/footer.php'; ?>
