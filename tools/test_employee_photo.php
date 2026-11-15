<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/bootstrap.php';
function checkPhoto($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$prefix='qa_photo_'.bin2hex(random_bytes(4));$password=bin2hex(random_bytes(16));$paths=[];$id=null;
$curl=curl_init();curl_setopt_array($curl,[CURLOPT_COOKIEFILE=>'',CURLOPT_RETURNTRANSFER=>true]);
function photoRequest($path,$post=null){global $curl;curl_setopt_array($curl,[CURLOPT_URL=>'http://localhost'.APP_BASE_URL.$path,CURLOPT_POST=>$post!==null]);if($post!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$post);$body=curl_exec($curl);return [curl_getinfo($curl,CURLINFO_HTTP_CODE),$body];}
function photoToken($html){preg_match('/name="_csrf" value="([^"]+)"/',$html,$m);return $m[1]??'';}
$tmp=tempnam(sys_get_temp_dir(),'portrait');
try {
Database::q('INSERT INTO admins(username,password_hash,role) VALUES(?,?,?)',[$prefix,password_hash($password,PASSWORD_DEFAULT),'admin']);$id=Database::pdo()->lastInsertId();
[$code,$html]=photoRequest('/admin/login.php');photoRequest('/admin/login.php',['username'=>$prefix,'password'=>$password,'_csrf'=>photoToken($html)]);
[$code,$html]=photoRequest('/admin/enroll.php');$csrf=photoToken($html);
[$code,$html]=photoRequest('/admin/enroll.php',['_csrf'=>$csrf,'emp_no'=>$prefix,'name'=>'Photo test']);
$row=Database::q('SELECT * FROM employees WHERE emp_no=?',[$prefix])->fetch();checkPhoto($code===200 && $row && !$row['photo_path'],'Employee can be added without photo or device connection');
$img=imagecreatetruecolor(30,30);imagepng($img,$tmp);imagedestroy($img);
[$code,$html]=photoRequest('/admin/employee-edit.php?emp='.urlencode($prefix));$csrf=photoToken($html);
$post=['_csrf'=>$csrf,'name'=>'Photo test','department'=>'QA','shift_id'=>'','active'=>'1','photo'=>new CURLFile($tmp,'image/png','portrait.png')];
[$code,$html]=photoRequest('/admin/employee-edit.php?emp='.urlencode($prefix),$post);
$path=Database::q('SELECT photo_path FROM employees WHERE emp_no=?',[$prefix])->fetchColumn();if($path)$paths[]=$path;
checkPhoto($code===302 && $path && getimagesize(BASE_PATH.'/'.$path)[2]===IMAGETYPE_JPEG,'PNG upload saved as a valid JPEG portrait');
[$code,$html]=photoRequest('/admin/employees.php?q='.urlencode($prefix));checkPhoto(str_contains($html,$path),'Directory displays the saved photo');
[$code,$html]=photoRequest('/admin/employee.php?emp='.urlencode($prefix));checkPhoto(str_contains($html,$path),'Profile displays the saved photo');
file_put_contents($tmp,'This is not an image');$post['photo']=new CURLFile($tmp,'image/png','invalid.png');
[$code,$html]=photoRequest('/admin/employee-edit.php?emp='.urlencode($prefix),$post);checkPhoto(str_contains($html,'Choose a JPG or PNG') && Database::q('SELECT photo_path FROM employees WHERE emp_no=?',[$prefix])->fetchColumn()===$path,'Invalid photo rejected without replacing existing portrait');
unset($post['photo']);$post['remove_photo']='1';photoRequest('/admin/employee-edit.php?emp='.urlencode($prefix),$post);
checkPhoto(Database::q('SELECT photo_path FROM employees WHERE emp_no=?',[$prefix])->fetchColumn()===null,'Photo can be removed');
} finally {
Database::q('DELETE FROM employee_profiles WHERE emp_no=?',[$prefix]);Database::q('DELETE FROM employees WHERE emp_no=?',[$prefix]);if($id)Database::q('DELETE FROM admins WHERE id=?',[$id]);Database::q('DELETE FROM login_attempts WHERE account_key=?',[hash('sha256',$prefix)]);
foreach($paths as $path)if(preg_match('~^storage/photos/portrait_[a-f0-9]+\.jpg$~',$path))@unlink(BASE_PATH.'/'.$path);
@unlink($tmp);curl_close($curl);echo "Disposable fixtures removed.\n";
}

