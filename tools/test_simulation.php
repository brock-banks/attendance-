<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../includes/bootstrap.php';
function verify($ok,$label) { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
$emp='__qa_20261107';
function cleanup($emp) { foreach (['attendance_corrections','attendance','events','employees'] as $table) Database::q("DELETE FROM $table WHERE emp_no=?",[$emp]); }
if (($argv[1]??'')==='cleanup') { cleanup($emp); echo "Test records removed.\n"; exit; }
if (($argv[1]??'')==='setup') {
 if (Database::q('SELECT id FROM employees WHERE emp_no=?',[$emp])->fetchColumn()) throw new RuntimeException('Fixture already exists');
 Database::q("INSERT INTO employees(emp_no,name,active) VALUES(?,'Disposable QA employee',0)",[$emp]);
 foreach (['Approve browser test','Reject browser test'] as $reason) Database::q("INSERT INTO attendance_corrections(emp_no,work_date,check_in,check_out,reason,admin_id,admin_user,previous_json,approval_status) VALUES(?,'2026-11-06','2026-11-06 09:00:00','2026-11-06 17:00:00',?,-1,'QA requester','{}','pending')",[$emp,$reason]);
 echo "Browser fixture ready: $emp\n"; exit;
}
$emp='__qa_'.bin2hex(random_bytes(5));
$last=Database::q("SELECT v FROM settings WHERE k='last_push_at'")->fetchColumn();
try {
 $url='http://localhost'.APP_BASE_URL.'/api/event-receiver.php'.(EVENT_TOKEN!==''?'?token='.rawurlencode(EVENT_TOKEN):'');
 $payload=['eventType'=>'AccessControllerEvent','dateTime'=>'2026-11-06T09:00:00','ipAddress'=>'qa-device','AccessControllerEvent'=>['employeeNoString'=>$emp,'majorEventType'=>5,'subEventType'=>75,'serialNo'=>123]];
 $multi=curl_multi_init(); $handles=[];
 for ($i=0;$i<8;$i++) { $h=curl_init($url); curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]); curl_multi_add_handle($multi,$h);$handles[]=$h; }
 do { curl_multi_exec($multi,$running); if($running) curl_multi_select($multi,1); } while($running);
 $ids=[];
 foreach($handles as $h) { $r=json_decode(curl_multi_getcontent($h),true); verify(curl_getinfo($h,CURLINFO_HTTP_CODE)===200 && !empty($r['ok']),'Concurrent HTTP delivery accepted'); $ids[]=$r['event_id']; curl_multi_remove_handle($multi,$h); curl_close($h); }
 curl_multi_close($multi);
 verify(count(array_unique($ids))===1,'Eight concurrent retries returned one event ID');
 verify(Database::q('SELECT COUNT(*) FROM events WHERE emp_no=?',[$emp])->fetchColumn()==1,'Only one raw event persisted');
 $row=Database::q('SELECT * FROM attendance WHERE emp_no=?',[$emp])->fetch(); verify($row && $row['check_out']===null,'Single scan has no fabricated checkout');
 $e=['emp_no'=>$emp,'event_time'=>'2026-11-06 08:00:00','major_event'=>5,'sub_event'=>75,'raw_json'=>'{}'];
 Attendance::recordEvent($e);
 $day=Shift::businessDate($e['event_time'],Shift::forEmployee($emp));
 $before=Database::q('SELECT * FROM attendance WHERE emp_no=? AND work_date=?',[$emp,$day])->fetch();
 Database::q("INSERT INTO attendance_corrections(emp_no,work_date,check_in,check_out,reason,admin_id,admin_user,previous_json,approval_status) VALUES(?,?,?,?,'Test approval',-1,'QA requester','{}','pending')",[$emp,$day,'2026-11-06 08:00:00','2026-11-06 17:00:00']);
 $id=(int)Database::pdo()->lastInsertId();
 try { Correction::review($id,'approved',-1,'QA requester','Self approval test'); throw new RuntimeException('Self approval allowed'); } catch(InvalidArgumentException $ex) { echo "PASS: Self approval blocked\n"; }
 Correction::review($id,'approved',-2,'QA reviewer','Approved in automated test');
 $audit=Database::q('SELECT * FROM attendance_corrections WHERE id=?',[$id])->fetch();
 verify($audit['reviewed_by']==='QA reviewer' && json_decode($audit['previous_json'],true)===$before,'Reviewer and previous values preserved');
 verify(Database::q('SELECT check_out FROM attendance WHERE emp_no=? AND work_date=?',[$emp,$day])->fetchColumn()==='2026-11-06 17:00:00','Approval updates report');
 try { Correction::review($id,'rejected',-2,'QA reviewer','Repeat decision test'); throw new RuntimeException('Repeat review allowed'); } catch(InvalidArgumentException $ex) { echo "PASS: Repeated review blocked\n"; }
} finally { cleanup($emp); if($last===false) Database::q("DELETE FROM settings WHERE k='last_push_at'"); else Shift::putSetting('last_push_at',$last); echo "Simulation records removed; heartbeat setting restored.\n"; }
