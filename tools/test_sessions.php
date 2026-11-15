<?php
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/../includes/bootstrap.php';
function verifySession($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function punches($times,$directions=[]){$out=[];foreach($times as $i=>$time)$out[]=['event_time'=>'2026-11-06 '.$time,'raw_json'=>json_encode(['AccessControllerEvent'=>['attendanceStatus'=>$directions[$i]??'undefined']])];return $out;}
$times=['03:00:00','03:30:00','06:30:00','07:00:00'];
$r=WorkSessions::calculate(punches($times,['checkIn','checkOut','checkIn','checkOut']),60);
verifySession($r['minutes']===60 && count($r['sessions'])===2 && !$r['review_required'],'Two visits total one hour, excluding three-hour gap');
$r=WorkSessions::calculate(punches($times),60);verifySession($r['minutes']===60 && $r['review_required'],'Unlabelled punches are paired and flagged');
$r=WorkSessions::calculate(punches(['03:00:00','03:00:20','03:30:00']),60);verifySession($r['minutes']===30,'Rapid duplicate suppressed');
$r=WorkSessions::calculate(punches(['03:00:00','03:30:00','06:30:00']),60);verifySession($r['minutes']===30 && $r['check_out']===null,'Open session does not invent working time');
$r=WorkSessions::calculate(punches(['03:00:00','06:30:00','07:00:00'],['checkIn','checkIn','checkOut']),60);verifySession($r['review_required'],'Repeated explicit IN is flagged');
$pdo=Database::pdo();$pdo->beginTransaction();
try{
 $emp='__session_'.bin2hex(random_bytes(4));
 foreach(punches($times) as $event)Database::q('INSERT INTO events(emp_no,event_time,raw_json,eligible) VALUES(?,?,?,1)',[$emp,$event['event_time'],$event['raw_json']]);
 $shift=['id'=>null,'start_time'=>'03:00:00','end_time'=>'11:00:00','grace_minutes'=>0,'break_minutes'=>60,'half_day_minutes'=>240];
 Attendance::updateDay($emp,'2026-11-06',$shift);
 $a=Database::q('SELECT * FROM attendance WHERE emp_no=?',[$emp])->fetch();
 verifySession((int)$a['work_minutes']===60 && $a['status']==='half' && (int)$a['overtime_minutes']===0,'Stored attendance is one hour and half-day, not full-day');
}finally{$pdo->rollBack();}
echo "Test records rolled back.\n";
