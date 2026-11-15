<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../includes/bootstrap.php';
function check($ok, $label) { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
check(Attendance::isEligible(['emp_no'=>'test','major_event'=>5,'sub_event'=>75]), 'Successful scan accepted');
check(!Attendance::isEligible(['emp_no'=>'test','major_event'=>5,'sub_event'=>76]), 'Failure event excluded');
$base=['emp_no'=>'test','event_time'=>'2026-11-06 08:00:00','major_event'=>5,'sub_event'=>75];
check(Attendance::eventKey($base+['raw_json'=>'{"ipAddress":"device","delivery":1}']) === Attendance::eventKey($base+['raw_json'=>'{"delivery":2,"ipAddress":"device"}']), 'Retry identity ignores metadata and JSON order');
foreach (['01:00:00','08:00:00','22:00:00'] as $start) {
 $shiftCase=['start_time'=>$start,'end_time'=>'06:00:00'];
 [$begin,$end]=Shift::window('2026-11-06',$shiftCase);
 check(Shift::businessDate($begin,$shiftCase)==='2026-11-06', 'Start boundary '.$start);
 check(Shift::businessDate($end,$shiftCase)==='2026-11-07', 'End boundary '.$start);
}
$pdo=Database::pdo(); $pdo->beginTransaction();
try {
 $emp='__test_'.bin2hex(random_bytes(6));
 $shift=['id'=>null,'start_time'=>'22:00:00','end_time'=>'06:00:00','grace_minutes'=>0,'break_minutes'=>0,'half_day_minutes'=>240];
 foreach ([['2026-11-06 21:55:00',1],['2026-11-07 06:00:00',1],['2026-11-07 20:00:00',1],['2026-11-07 10:00:00',0]] as [$time,$eligible]) Database::q('INSERT INTO events(emp_no,event_time,eligible) VALUES(?,?,?)',[$emp,$time,$eligible]);
 Attendance::updateDay($emp,'2026-11-06',$shift);
 $row=Database::q('SELECT * FROM attendance WHERE emp_no=?',[$emp])->fetch();
 check($row['check_out']==='2026-11-07 06:00:00','Neighboring shift and failed event excluded');
 Database::q('INSERT INTO attendance_corrections(emp_no,work_date,check_in,check_out,reason,admin_id,admin_user,previous_json) VALUES(?,?,?,?,?,?,?,?)',[$emp,'2026-11-06','2026-11-06 22:00:00','2026-11-07 05:00:00','Test correction',0,'test','{}']);
 Database::q("INSERT INTO attendance_corrections(emp_no,work_date,check_in,reason,admin_id,admin_user,previous_json,approval_status) VALUES(?,?,?,'Pending test',0,'test','{}','pending')",[$emp,'2026-11-06','2026-11-06 23:00:00']);
 Attendance::updateDay($emp,'2026-11-06',$shift);
 check(Database::q('SELECT work_minutes FROM attendance WHERE emp_no=?',[$emp])->fetchColumn()==420,'Approved correction survives recalculation; pending request excluded');
 $dayShift=['id'=>null,'start_time'=>'09:00:00','end_time'=>'17:00:00','grace_minutes'=>5,'break_minutes'=>60,'half_day_minutes'=>240];
 foreach ([['08:30:00','18:00:00',510,90,0],['09:20:00','17:00:00',400,0,15],['09:00:00','09:30:00',0,0,0]] as $i=>$case) {
  $caseEmp=$emp.'_'.$i;
  foreach ([$case[0],$case[1]] as $time) Database::q('INSERT INTO events(emp_no,event_time,eligible) VALUES(?,?,1)',[$caseEmp,'2026-11-06 '.$time]);
  Attendance::updateDay($caseEmp,'2026-11-06',$dayShift);
  $actual=Database::q('SELECT * FROM attendance WHERE emp_no=?',[$caseEmp])->fetch();
  check((int)$actual['work_minutes']===$case[2] && (int)$actual['overtime_minutes']===$case[3] && (int)$actual['late_minutes']===$case[4], 'Day shift worked/overtime/late case '.$i);
 }
} finally { $pdo->rollBack(); }
echo "Test data rolled back.\n";
