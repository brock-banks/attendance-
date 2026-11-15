<?php
require dirname(__DIR__).'/includes/bootstrap.php';Auth::requireRole(['admin']);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $path=null;try{require BASE_PATH.'/src/SystemExport.php';set_time_limit(300);$path=SystemExport::build();header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="attendance-transfer-'.date('Ymd-His').'.zip"');header('Content-Length: '.filesize($path));header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');readfile($path);unlink($path);exit;}catch(Throwable $ex){if($path && is_file($path))unlink($path);error_log('System export: '.$ex->getMessage());$error='The export could not complete. Try again or contact your administrator.';}
}
$active='employees';$pageTitle='Download full system';require BASE_PATH.'/includes/header.php';
?>
<h1>Download full system</h1><?php if($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<div class="panel"><h2>Move Attendance to another PC</h2><p>Download a ZIP containing the entire app, current database, all employee records, photos, scan images and restore instructions. This exports everyone’s data, not just the selected employee.</p><p>The download includes saved device and email configuration. Keep it private and transfer it securely. Existing usernames and passwords remain valid on the restored system.</p><p>Make the final download just before switching devices. Later scans and edits will not be included. Scheduled Windows tasks must be recreated on the new PC.</p><form method="post" data-download-form><?= Auth::field() ?><button class="btn">Download full system ZIP</button> <a class="btn ghost" href="employees.php">Back to employees</a></form></div>
<?php require BASE_PATH.'/includes/footer.php'; ?>
