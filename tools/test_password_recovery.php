<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/bootstrap.php';require BASE_PATH.'/src/PasswordRecovery.php';
function pwCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$name='qa_pw_'.bin2hex(random_bytes(4));$id=null;
try{
Database::q('INSERT INTO admins(username,password_hash,role) VALUES(?,?,?)',[$name,password_hash('OriginalPassword123!',PASSWORD_DEFAULT),'admin']);$id=(int)Database::pdo()->lastInsertId();
Database::q('INSERT INTO password_resets(admin_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE))',[$id,password_hash('12345678',PASSWORD_DEFAULT)]);
try{PasswordRecovery::reset($name,'87654321','NewPassword12345!');throw new RuntimeException('Wrong code allowed');}catch(InvalidArgumentException $ex){pwCheck((int)Database::q('SELECT attempts FROM password_resets WHERE admin_id=?',[$id])->fetchColumn()===1,'Wrong code rejected and counted');}
PasswordRecovery::reset($name,'12345678','NewPassword12345!');$user=Database::q('SELECT * FROM admins WHERE id=?',[$id])->fetch();pwCheck(password_verify('NewPassword12345!',$user['password_hash']) && (int)$user['session_version']===2,'Reset changes password and revokes sessions');pwCheck(!Database::q('SELECT 1 FROM password_resets WHERE admin_id=?',[$id])->fetchColumn(),'Reset code consumed');
try{PasswordRecovery::reset($name,'12345678','AnotherPassword123!');throw new RuntimeException('Reused code allowed');}catch(InvalidArgumentException $ex){echo "PASS: Used code cannot be reused\n";}
Database::q('INSERT INTO password_resets(admin_id,token_hash,expires_at) VALUES(?,?,DATE_SUB(NOW(),INTERVAL 1 MINUTE))',[$id,password_hash('12345678',PASSWORD_DEFAULT)]);
try{PasswordRecovery::reset($name,'12345678','AnotherPassword123!');throw new RuntimeException('Expired code allowed');}catch(InvalidArgumentException $ex){echo "PASS: Expired code rejected\n";}
}finally{if($id){Database::q('DELETE FROM password_resets WHERE admin_id=?',[$id]);Database::q('DELETE FROM account_audit WHERE actor_id=?',[$id]);Database::q('DELETE FROM admins WHERE id=?',[$id]);}foreach(['reset','recovery','change'] as $scope)Database::q('DELETE FROM login_attempts WHERE account_key=?',[hash('sha256',$scope.':'.strtolower($name))]);echo "Disposable account removed. No email sent.\n";}
