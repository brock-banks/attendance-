<?php
require_once dirname(__DIR__).'/includes/bootstrap.php';
$msg=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  $id=(int)($_POST['id']??0); $role=$_POST['role']??'viewer'; $active=isset($_POST['active'])?1:0;
  if(!in_array($role,['admin','hr','viewer'],true)) throw new InvalidArgumentException('Choose a valid role.');
  $password=$_POST['password']??'';
  if(($id===0 || $password!=='') && strlen($password)<12) throw new InvalidArgumentException('Use a password of at least 12 characters.');
  if($id===(int)$_SESSION['admin_id']) throw new InvalidArgumentException('Ask another administrator to change your account.');
  Database::pdo()->beginTransaction();
  $admins=Database::q("SELECT id FROM admins WHERE active=1 AND role='admin' FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
  if($id && in_array($id,array_map('intval',$admins),true) && count($admins)<=1 && (!$active || $role!=='admin')) throw new InvalidArgumentException('Keep at least one active administrator.');
  if($id) {
   $old=Database::q('SELECT username,role,active FROM admins WHERE id=?',[$id])->fetch();
   if(!$old) throw new InvalidArgumentException('Account not found.');
   Database::q('UPDATE admins SET role=?,active=?,session_version=session_version+1 WHERE id=?',[$role,$active,$id]);
   if($password!=='') Database::q('UPDATE admins SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
  } else {
   $name=trim($_POST['username']??'');
   if(!preg_match('/^[a-zA-Z0-9_.-]{3,64}$/',$name)) throw new InvalidArgumentException('Use 3–64 letters, numbers, dots, underscores or hyphens for the username.');
   $old=null;
   Database::q('INSERT INTO admins(username,password_hash,role,active) VALUES(?,?,?,?)',[$name,password_hash($password,PASSWORD_DEFAULT),$role,$active]); $id=(int)Database::pdo()->lastInsertId();
  }
  Database::q('INSERT INTO account_audit(actor_id,target_id,action,details) VALUES(?,?,?,?)',[$_SESSION['admin_id'],$id,'account_saved',json_encode(['before'=>$old,'role'=>$role,'active'=>$active,'password_changed'=>$password!==''])]);
  Database::pdo()->commit(); $msg='Account saved.';
 } catch(Throwable $ex) { if(Database::pdo()->inTransaction()) Database::pdo()->rollBack(); $error=$ex instanceof InvalidArgumentException?$ex->getMessage():'Account could not be saved. Check for a duplicate username.'; }
}
$active='accounts';$pageTitle='Accounts'; require BASE_PATH.'/includes/header.php';
?>
<h1>Accounts</h1>
<p>Administrators manage the system. HR manages attendance and corrections. Viewers can read reports. A different administrator or HR user must review corrections.</p>
<?php if($msg): ?><div class="alert ok" role="status"><?= e($msg) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert err" role="alert"><?= e($error) ?></div><?php endif; ?>
<h2>Create account</h2>
<form method="post" class="filters"><?= Auth::field() ?>
<label>Username <input name="username" required minlength="3" maxlength="64" autocomplete="off"></label>
<label>Password <input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
<label>Role <select name="role"><option value="viewer">Viewer</option><option value="hr">HR</option><option value="admin">Administrator</option></select></label>
<label><input type="checkbox" name="active" checked> Active</label><button class="btn">Create account</button></form>
<h2>Manage accounts</h2>
<?php foreach(Database::q('SELECT id,username,role,active FROM admins ORDER BY username')->fetchAll() as $account): ?>
<h3><?= e($account['username']) ?> · <?= status_label($account['active']?'Active':'Inactive') ?></h3>
<?php if((int)$account['id']===(int)$_SESSION['admin_id']): ?><p>Your account: <?= e($account['role']) ?>. <a href="password.php">Change my password and recovery email</a>.</p><?php continue; endif; ?>
<form method="post" class="filters"><?= Auth::field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
<label>Role <select name="role"><?php foreach(['admin'=>'Administrator','hr'=>'HR','viewer'=>'Viewer'] as $v=>$label): ?><option value="<?= $v ?>" <?= $v===$account['role']?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label><input type="checkbox" name="active" <?= $account['active']?'checked':'' ?>> Active</label>
<label>New password (optional) <input name="password" type="password" minlength="12" autocomplete="new-password"></label>
<button class="btn">Save account</button></form>
<?php endforeach; ?>
<h2>Recent account changes</h2><ul>
<?php foreach(Database::q('SELECT a.*,u.username FROM account_audit a LEFT JOIN admins u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 30')->fetchAll() as $entry): ?><li><?= e($entry['created_at']) ?> — <?= e($entry['username']) ?> updated account #<?= (int)$entry['target_id'] ?></li><?php endforeach; ?>
</ul>
<?php require BASE_PATH.'/includes/footer.php'; ?>
