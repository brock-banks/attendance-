<?php
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/../includes/bootstrap.php';
function ok($v,$label){if(!$v) throw new RuntimeException($label);echo "PASS: $label\n";}
function request($path,$post=null){global $curl;$options=[CURLOPT_URL=>'http://localhost'.APP_BASE_URL.$path,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_POST=>$post!==null]; if($post!==null)$options[CURLOPT_POSTFIELDS]=http_build_query($post);curl_setopt_array($curl,$options);$body=curl_exec($curl);return [curl_getinfo($curl,CURLINFO_HTTP_CODE),$body];}
function token($html){preg_match('/name="_csrf" value="([^"]+)"/',$html,$m);return $m[1]??'';}
[$forgotCode,$forgotHtml]=[0,''];
$prefix='qa_security_'.bin2hex(random_bytes(4));$ids=[];$password=bin2hex(random_bytes(16));
Database::q("INSERT INTO employees(emp_no,name,active) VALUES(?,'QA edit fixture',0)",[$prefix]);
try{
 foreach(['viewer','hr','admin'] as $role){
  $name=$prefix.'_'.$role;Database::q('INSERT INTO admins(username,password_hash,role) VALUES(?,?,?)',[$name,password_hash($password,PASSWORD_DEFAULT),$role]);$ids[]=(int)Database::pdo()->lastInsertId();
  $curl=curl_init();curl_setopt($curl,CURLOPT_COOKIEFILE,'');
  [$forgotCode,$forgotHtml]=request('/admin/forgot-password.php');ok($forgotCode===200 && str_contains($forgotHtml,'Send reset code'),'Recovery page accessible before login');
  [$code,$html]=request('/admin/login.php');$csrf=token($html);ok($csrf!=='','Login form has CSRF token');
  [$code]=request('/admin/login.php',['username'=>$name,'password'=>$password]);ok($code===403,'Login rejects missing CSRF');
  [$code]=request('/admin/login.php',['username'=>$name,'password'=>$password,'_csrf'=>$csrf]);ok($code===302,$role.' login succeeds');
  [$code,$html]=request('/admin/reports.php');ok($code===200,$role.' reads reports');
  foreach(['/admin/index.php','/admin/review-inbox.php','/admin/attendance-calendar.php?emp='.urlencode($prefix).'&month=2026-11','/admin/employees.php?archived=1','/admin/employees.php?department=QA&shift=default&state=0','/admin/corrections.php','/admin/reports.php?view=timeline','/admin/exceptions.php','/admin/overtime.php','/admin/employee.php?emp='.urlencode($prefix),'/admin/employee.php?emp='.urlencode($prefix).'&period=year&view=list&status=review&order=asc','/admin/employee.php?emp='.urlencode($prefix).'&period=all&view=cards&page=999','/admin/reports.php?status=Missing%20check-out&department=QA','/admin/export.php?status=Missing%20check-out&department=QA'] as $page) {
   [$pageCode,$pageBody]=request($page);ok($pageCode===200 && !preg_match('/(Fatal error|Warning:|Parse error)/',$pageBody),$role.' renders '.$page);
  }
  foreach(['2026-11'=>['November 2026',30,'2026-10','2026-12'],'2024-02'=>['February 2024',29,'2024-01','2024-03'],'2026-01'=>['January 2026',31,'2025-12','2026-02']] as $month=>$expected) {
   [$calendarCode,$calendarHtml]=request('/admin/attendance-calendar.php?emp='.urlencode($prefix).'&month='.$month);
   ok($calendarCode===200 && str_contains($calendarHtml,'<h2>'.$expected[0].'</h2>') && substr_count($calendarHtml,'<article class="calendar-day ')===$expected[1] && str_contains($calendarHtml,'month='.$expected[2]) && str_contains($calendarHtml,'month='.$expected[3]),$role.' calendar heading, day count and navigation for '.$month);
  }
  [$deleteCode]=request('/admin/employee-delete.php?emp='.urlencode($prefix));ok($deleteCode===($role==='admin'?200:403),$role.' permanent deletion access enforced');
  [$passwordCode]=request('/admin/password.php');ok($passwordCode===200,$role.' can open own password settings');
  [$editCode,$editHtml]=request('/admin/employee-edit.php?emp='.urlencode($prefix));
  ok($editCode===($role==='viewer'?403:200),$role.' employee edit access');
  if($role!=='viewer') {
   [$saveCode]=request('/admin/employee-edit.php?emp='.urlencode($prefix),['_csrf'=>token($editHtml),'name'=>'QA updated employee','department'=>'QA department','card_no'=>'QA123','shift_id'=>'']);
   ok($saveCode===302 && Database::q('SELECT name FROM employees WHERE emp_no=?',[$prefix])->fetchColumn()==='QA updated employee',$role.' saves employee changes');
  }
  [$deviceCode,$deviceHtml]=request('/admin/device-setup.php');ok($deviceCode===($role==='admin'?200:403),$role.' troubleshooting access enforced');
  if($role==='admin'){ok(str_contains($deviceHtml,'Device troubleshooting') && str_contains($deviceHtml,'Repair receiving address'),'Troubleshooting controls render');[$invalidCode,$invalidHtml]=request('/admin/device-setup.php',['_csrf'=>token($deviceHtml),'action'=>'test','device_ip'=>'invalid']);ok(str_contains($invalidHtml,'Enter the local IPv4 address'),'Invalid terminal address rejected');[$saveCode,$saveHtml]=request('/admin/device-setup.php',['_csrf'=>token($deviceHtml),'action'=>'save','verified_token'=>'invalid']);ok(str_contains($saveHtml,'test the address again'),'Untested address cannot be saved');}
  [$importCode]=request('/admin/employee-import.php');ok($importCode===($role==='viewer'?403:200),$role.' import access enforced');
  [$code]=request('/admin/accounts.php');ok($code===($role==='admin'?200:403),$role.' account access enforced');
  [$code]=request('/admin/employees.php',['action'=>'delete','emp_no'=>'nonexistent']);ok($code===403,$role.' mutation without CSRF rejected');
  Database::q('UPDATE admins SET active=0 WHERE id=?',[end($ids)]);[$code]=request('/admin/reports.php');ok($code===302,'Disabled account session revoked');curl_close($curl);
 }
 $curl=curl_init();curl_setopt($curl,CURLOPT_COOKIEFILE,'');[$code,$html]=request('/admin/login.php');$csrf=token($html);
 for($i=0;$i<6;$i++) request('/admin/login.php',['username'=>$prefix.'_bad','password'=>'wrong','_csrf'=>$csrf]);
 ok(Database::q('SELECT COUNT(*) FROM login_attempts WHERE account_key=?',[hash('sha256',$prefix.'_bad')])->fetchColumn()==5,'Throttle stops recording attempts after five');
 curl_close($curl);
}finally{
 Database::q('DELETE FROM employee_profiles WHERE emp_no=?',[$prefix]);Database::q('DELETE FROM employees WHERE emp_no=?',[$prefix]);
 foreach($ids as $id)Database::q('DELETE FROM admins WHERE id=?',[$id]);
 foreach(['viewer','hr','admin','bad'] as $role)Database::q('DELETE FROM login_attempts WHERE account_key=?',[hash('sha256',$prefix.'_'.$role)]);
 echo "Disposable security accounts and attempts removed.\n";
}
