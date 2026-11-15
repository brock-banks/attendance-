<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/bootstrap.php';
function ensure($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$pdo=Database::pdo();$pdo->beginTransaction();
try {
 $emp='__flow_'.bin2hex(random_bytes(5));$shift=['id'=>null,'name'=>'QA original','start_time'=>'03:00:00','end_time'=>'11:00:00','grace_minutes'=>0,'break_minutes'=>60,'half_day_minutes'=>240,'workdays'=>'1,2,3,4,5'];
 Database::q('INSERT INTO shift_assignments(emp_no,effective_date,snapshot) VALUES(?,?,?)',[$emp,'1000-01-01',json_encode($shift)]);
 $new=$shift;$new['start_time']='10:00:00';Database::q('INSERT INTO shift_assignments(emp_no,effective_date,snapshot) VALUES(?,?,?)',[$emp,'2026-11-10',json_encode($new)]);
 ensure(Shift::forEmployee($emp,'2026-11-06')['start_time']==='03:00:00' && Shift::forEmployee($emp,'2026-11-11')['start_time']==='10:00:00','Dated assignments preserve earlier shift');
 $sessions=[['in'=>'2026-11-06 03:00:00','out'=>'2026-11-06 03:30:00'],['in'=>'2026-11-06 06:30:00','out'=>'2026-11-06 07:00:00']];
 Database::q("INSERT INTO attendance_corrections(emp_no,work_date,check_in,check_out,reason,admin_id,admin_user,previous_json,approval_status,sessions_json) VALUES(?,'2026-11-06',?,?,'QA sessions',-1,'QA','{}','approved',?)",[$emp,$sessions[0]['in'],$sessions[1]['out'],json_encode($sessions)]);
 Attendance::updateDay($emp,'2026-11-06');$row=Database::q('SELECT * FROM attendance WHERE emp_no=?',[$emp])->fetch();
 ensure((int)$row['work_minutes']===60 && count(json_decode($row['sessions_json'],true))===2,'Approved split-session correction totals one hour');
 Attendance::updateDay($emp,'2026-11-06',$new);$again=Database::q('SELECT * FROM attendance WHERE emp_no=?',[$emp])->fetch();ensure(json_decode($again['shift_snapshot'],true)['start_time']==='03:00:00','Stored shift snapshot survives changed template');
 $fingerprint=Overtime::fingerprint($row);$row['work_minutes']++;ensure($fingerprint!==Overtime::fingerprint($row),'Changed attendance invalidates overtime approval');
 $filters=Report::filters(['from'=>'2026-11-06','to'=>'2026-11-06','emp'=>$emp,'sort'=>'worked','direction'=>'asc']);ensure(Report::count($filters)===1 && count(Report::rows($filters,1))===1,'Filtered SQL count and paged rows agree');
}finally{$pdo->rollBack();}
echo "Workflow fixtures rolled back.\n";
