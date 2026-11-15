<?php
class Auth
{
    public static function start(): void
    {
        if (session_status()===PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode','1');
            session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>APP_BASE_URL.'/']);
            session_start();
        }
    }
    public static function csrf(): string { self::start(); return $_SESSION['csrf'] ?? ($_SESSION['csrf']=bin2hex(random_bytes(32))); }
    public static function field(): string { return '<input type="hidden" name="_csrf" value="'.self::csrf().'">'; }
    public static function verifyCsrf(): void {
        if (!is_string($_POST['_csrf'] ?? null) || !hash_equals(self::csrf(),$_POST['_csrf'])) { http_response_code(403); exit('Your form has expired. Reload the page and try again.'); }
    }
    public static function attempt(string $username,string $password): bool
    {
        self::start();
        $account=hash('sha256',strtolower(trim($username))); $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'cli');
        $lock='login:'.substr($ip,0,58);
        if (!Database::q('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()) return false;
        try {
            $count=Database::q('SELECT COUNT(*) FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (account_key=? OR ip_key=?)',[$account,$ip])->fetchColumn();
            if ($count>=5) return false;
            Database::q('INSERT INTO login_attempts(account_key,ip_key) VALUES(?,?)',[$account,$ip]);
            $row=Database::q('SELECT * FROM admins WHERE username=? AND active=1',[$username])->fetch();
            if (!$row || !password_verify($password,$row['password_hash'])) return false;
            Database::q('DELETE FROM login_attempts WHERE account_key=? AND ip_key=?',[$account,$ip]);
            session_regenerate_id(true); $_SESSION=[];
            $_SESSION=['admin_id'=>(int)$row['id'],'admin_user'=>$row['username'],'role'=>$row['role'],'version'=>(int)$row['session_version'],'created'=>time(),'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
            return true;
        } finally { Database::q('SELECT RELEASE_LOCK(?)',[$lock]); }
    }
    public static function check(): bool {
        self::start(); if(empty($_SESSION['admin_id'])) return false;
        $now=time();
        if($now-($_SESSION['last_seen']??0)>1800 || $now-($_SESSION['created']??0)>28800) { self::logout(); return false; }
        $row=Database::q('SELECT username,role,active,session_version FROM admins WHERE id=?',[$_SESSION['admin_id']])->fetch();
        if(!$row || !$row['active'] || (int)$row['session_version']!==($_SESSION['version']??0)) { self::logout(); return false; }
        $_SESSION['role']=$row['role']; $_SESSION['admin_user']=$row['username']; $_SESSION['last_seen']=$now; return true;
    }
    public static function role(): string { return $_SESSION['role']??'viewer'; }
    public static function requireLogin(): void { if(!self::check()) { header('Location: '.APP_BASE_URL.'/admin/login.php'); exit; } }
    public static function requireRole(array $roles): void { if(!in_array(self::role(),$roles,true)) { http_response_code(403); exit('You do not have permission to perform this action.'); } }
    public static function logout(): void { self::start(); $_SESSION=[]; session_regenerate_id(true); }
    public static function user(): ?string { self::start(); return $_SESSION['admin_user']??null; }
}
