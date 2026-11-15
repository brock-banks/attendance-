<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/bootstrap.php';
function otCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$emp='qa_ot_'.bin2hex(random_bytes(4));$pass=bin2hex(random_bytes(16));$id=null;$day=date('Y-m-d');
$curl=curl_init();curl_setopt_array($curl,[CURLOPT_COOKIEFILE=>'',CURLOPT_RETURNTRANSFER=>true]);
function otRequest($post=null,$path='/admin/overtime.php'){global $curl;curl_setopt_array($curl,[CURLOPT_URL=>'http://localhost'.APP_BASE_URL.$path,CURLOPT_POST=>$post!==null]);if($post!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));$html=curl_exec($curl);return [curl_getinfo($curl,CURLINFO_HTTP_CODE),$html];}
try {
Database::q('INSERT INTO admins(username,password_hash,role) VALUES(?,?,?)',[$emp,password_hash($pass,PASSWORD_DEFAULT),'admin']);$id=Database::pdo()->lastInsertId();
Database::q("INSERT INTO employees(emp_no,name,active) VALUES(?,'Overtime QA',0)",[$emp]);
Database::q("INSERT INTO attendance(emp_no,work_date,check_in,check_out,work_minutes,overtime_minutes,status,sessions_json) VALUES(?,?,?, ?,600,120,'present','[]')",[$emp,$day,$day.' 09:00:00',$day.' 19:00:00']);
[$code,$html]=otRequest(null,'/admin/login.php');preg_match('/name="_csrf" value="([^"]+)"/',$html,$m);otRequest(['username'=>$emp,'password'=>$pass,'_csrf'=>$m[1]],'/admin/login.php');
[$code,$html]=otRequest();preg_match('/name="_csrf" value="([^"]+)"/',$html,$m);$csrf=$m[1];
$a=Database::q('SELECT * FROM attendance WHERE emp_no=?',[$emp])->fetch();
$post=['_csrf'=>$csrf,'emp'=>$emp,'day'=>$day,'minutes'=>90,'decision'=>'approved','reason'=>'QA overtime review','version'=>Overtime::fingerprint($a)];
foreach(['approved','rejected'] as $decision){$post['decision']=$decision;if($decision==='rejected')$post['minutes']=999;[$code,$html]=otRequest($post);$review=Overtime::current($a);if(!$review || $review['decision']!==$decision){preg_match('/<div class="alert err"[^>]*>(.*?)<\/div>/s',$html,$err);echo 'Response: '.strip_tags($err[1]??'No error message')."\n";}otCheck($review && $review['decision']===$decision && (int)$review['approved_minutes']===($decision==='approved'?90:0),$decision.' saved through HTTP');otCheck(!str_contains($html,'<h2>Overtime QA'), 'Reviewed entry leaves Needs review');
[$code,$history]=otRequest(null,'/admin/overtime.php?view='.$decision);otCheck(str_contains($history,'<h2>Overtime QA') && str_contains($history,ucfirst($decision)), 'Entry appears in '.$decision.' tab with history');}
$count=Database::q('SELECT COUNT(*) FROM overtime_reviews WHERE emp_no=?',[$emp])->fetchColumn();
otRequest($post);otCheck(Database::q('SELECT COUNT(*) FROM overtime_reviews WHERE emp_no=?',[$emp])->fetchColumn()===$count,'Repeated submission does not duplicate review history');
[$code,$html]=otRequest(null,'/admin/attendance-detail.php?panel=1&emp='.urlencode($emp).'&day='.$day);otCheck($code===200 && str_contains($html,'Overtime history') && str_contains($html,'panel-page'),'Attendance detail panel renders sessions and history');
$post['reason']='';[$code,$html]=otRequest($post);otCheck(str_contains($html,'provide a reason') && Database::q('SELECT COUNT(*) FROM overtime_reviews WHERE emp_no=?',[$emp])->fetchColumn()===$count,'Missing reason gives an error without saving');
$post['reason']='QA review reason';$post['version']='stale';[$code,$html]=otRequest($post);otCheck(str_contains($html,'Attendance changed') && Database::q('SELECT COUNT(*) FROM overtime_reviews WHERE emp_no=?',[$emp])->fetchColumn()===$count,'Stale attendance cannot be reviewed');
Database::q('UPDATE attendance SET work_minutes=601 WHERE emp_no=?',[$emp]);
[$code,$html]=otRequest();otCheck(str_contains($html,'<h2>Overtime QA'),'Changed attendance returns to Needs review');
[$code,$html]=otRequest(null,'/admin/overtime.php?view=rejected');otCheck(!str_contains($html,'<h2>Overtime QA'),'Stale rejection leaves the rejected tab');
} finally {
foreach(['overtime_reviews','attendance','employees'] as $table)Database::q("DELETE FROM $table WHERE emp_no=?",[$emp]);if($id)Database::q('DELETE FROM admins WHERE id=?',[$id]);Database::q('DELETE FROM login_attempts WHERE account_key=?',[hash('sha256',$emp)]);curl_close($curl);echo "Disposable fixtures removed.\n";
}
