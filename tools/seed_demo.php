<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../includes/bootstrap.php';
$people=['DEMO-1001'=>'Alex Morgan (Demo)','DEMO-1002'=>'Sarah Ahmed (Demo)','DEMO-1003'=>'Daniel Lee (Demo)','DEMO-1004'=>'Maya Patel (Demo)','DEMO-1005'=>'Omar Hassan (Demo)'];
$shift=['id'=>null,'name'=>'Demo day shift','start_time'=>'09:00:00','end_time'=>'17:00:00','grace_minutes'=>10,'break_minutes'=>60,'half_day_minutes'=>240,'workdays'=>'1,2,3,4,5'];
$pdo=Database::pdo();$pdo->beginTransaction();
try {
 foreach($people as $emp=>$name) if(Database::q('SELECT id FROM employees WHERE emp_no=?',[$emp])->fetchColumn()) throw new RuntimeException('Demo employees already exist; no duplicates were added.');
 $index=0;$eventCount=0;$dayCount=0;
 foreach($people as $emp=>$name){
  Database::q('INSERT INTO employees(emp_no,name,department,active,on_device) VALUES(?,?,?,1,0)',[$emp,$name,'Demo']);
  Database::q('INSERT INTO shift_assignments(emp_no,effective_date,snapshot) VALUES(?,?,?)',[$emp,'2026-11-01',json_encode($shift)]);
  for($d=3;$d<=9;$d++){
   $day=sprintf('2026-11-%02d',$d);$kind=($d+$index)%5;
   $arrival=($kind===1?9*60+random_int(20,55):9*60-random_int(0,12));
   $departure=$kind===3?19*60+random_int(0,30):17*60+random_int(0,12);
   $times=$kind===2?[$arrival,12*60,14*60+random_int(0,30),$departure]:($kind===4?[$arrival]:[$arrival,$departure]);
   foreach($times as $i=>$minutes){
    $time=$day.' '.sprintf('%02d:%02d:00',intdiv($minutes,60),$minutes%60);
    $payload=json_encode(['demo'=>true,'AccessControllerEvent'=>['attendanceStatus'=>$i%2===0?'checkIn':'checkOut']]);
    Database::q('INSERT INTO events(emp_no,name,event_time,major_event,sub_event,raw_json,eligible,event_key) VALUES(?,?,?,5,75,?,1,?)',[$emp,$name,$time,$payload,hash('sha256','demo-preview|'.$emp.'|'.$time)]);$eventCount++;
   }
   Attendance::updateDay($emp,$day,$shift);$dayCount++;
  }$index++;
 }
 $pdo->commit();echo "Added 5 demo employees, $dayCount attendance days and $eventCount scans for November 3–9, 2026. No terminal calls made.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
