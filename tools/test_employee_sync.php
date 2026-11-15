<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/bootstrap.php';
class FakeSyncClient extends IsapiClient {
 public bool $fail=false;
 public function addUser(string $empNo,string $name,?string $cardNo=null): array {return ['code'=>$this->fail?503:200,'body'=>'{"statusCode":1}'];}
 public function setFace(string $empNo,string $imagePath): array {return ['code'=>200,'body'=>'{"statusCode":1}'];}
}
function syncCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$emp='qa_sync_'.bin2hex(random_bytes(4));
try {
Database::q("INSERT INTO employees(emp_no,name,active) VALUES(?,'Sync QA',0)",[$emp]);
$e=Database::q('SELECT * FROM employees WHERE emp_no=?',[$emp])->fetch();$client=new FakeSyncClient();
syncCheck(EmployeeSync::state($e)==='Saved in app','Unsent employee shows saved in app');
$client->fail=true;syncCheck(!EmployeeSync::run($e,$client) && EmployeeSync::state($e)==='Sync failed','Failed sync recorded');
$client->fail=false;syncCheck(EmployeeSync::run($e,$client) && EmployeeSync::state($e)==='Synced','Successful retry recorded');
$e['name']='Changed';syncCheck(EmployeeSync::state($e)==='Changes to sync','Edited details need syncing');
syncCheck(!EmployeeSync::accepted(['code'=>200,'body'=>'{"statusCode":4}']),'Device application error is not treated as success');
}finally{Database::q('DELETE FROM employee_profiles WHERE emp_no=?',[$emp]);Database::q('DELETE FROM employees WHERE emp_no=?',[$emp]);Database::q('DELETE FROM settings WHERE k=?',[EmployeeSync::key($emp)]);echo "Disposable fixtures removed. No device contacted.\n";}
