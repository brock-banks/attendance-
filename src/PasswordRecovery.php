<?php
class PasswordRecovery {
 public static function throttle(string $scope,string $username): bool {
  $account=hash('sha256',$scope.':'.strtolower($username));$ip=hash('sha256',$scope.':'.($_SERVER['REMOTE_ADDR']??'cli'));
  $lock='pw:'.substr($ip,0,50);if(!Database::q('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn())return false;
  try{$count=Database::q('SELECT COUNT(*) FROM login_attempts WHERE attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE) AND (account_key=? OR ip_key=?)',[$account,$ip])->fetchColumn();if($count>=5)return false;Database::q('INSERT INTO login_attempts(account_key,ip_key) VALUES(?,?)',[$account,$ip]);return true;}finally{Database::q('SELECT RELEASE_LOCK(?)',[$lock]);}
 }
 public static function request(string $username): void {
  if(!self::throttle('recovery',$username))return;
  $user=Database::q('SELECT id,recovery_email FROM admins WHERE username=? AND active=1',[$username])->fetch();if(!$user || !$user['recovery_email'] || !Mailer::configured())return;
  $code=(string)random_int(10000000,99999999);
  Database::q('INSERT INTO password_resets(admin_id,token_hash,expires_at,attempts) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE),0) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),attempts=0',[$user['id'],password_hash($code,PASSWORD_DEFAULT)]);
  $error=null;Mailer::send($user['recovery_email'],'Attendance password reset','<p>Your password reset code is <strong>'.e($code).'</strong>.</p><p>It expires in 15 minutes. If you did not request it, ignore this email.</p>',$error);
 }
 public static function reset(string $username,string $code,string $password): void {
  if(strlen($password)<12 || strlen($password)>72)throw new InvalidArgumentException('Use a password of 12–72 characters.');
  if(!self::throttle('reset',$username))throw new InvalidArgumentException('Too many attempts. Wait 15 minutes before trying again.');
  $pdo=Database::pdo();$pdo->beginTransaction();
  try{$user=Database::q('SELECT id FROM admins WHERE username=? AND active=1 FOR UPDATE',[$username])->fetch();$reset=$user?Database::q('SELECT *,expires_at>NOW() valid FROM password_resets WHERE admin_id=? FOR UPDATE',[$user['id']])->fetch():null;
   if(!$reset || !$reset['valid'] || $reset['attempts']>=5 || !password_verify($code,$reset['token_hash'])){if($reset)Database::q('UPDATE password_resets SET attempts=attempts+1 WHERE admin_id=?',[$user['id']]);$pdo->commit();throw new InvalidArgumentException('The code is invalid or expired. Request a new code if needed.');}
   Database::q('UPDATE admins SET password_hash=?,session_version=session_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['id']]);Database::q('DELETE FROM password_resets WHERE admin_id=?',[$user['id']]);Database::q('INSERT INTO account_audit(actor_id,target_id,action,details) VALUES(?,?,?,?)',[$user['id'],$user['id'],'password_reset','{}']);$pdo->commit();
  }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();throw $ex;}
 }
}
