<?php
/** Pair chronologically ordered punches without counting time away. */
class WorkSessions {
 public static function calculate(array $events, int $rescanSeconds): array {
  usort($events,static fn($a,$b)=>strcmp($a['event_time'],$b['event_time']));
  $sessions=[];$open=null;$first=null;$lastAccepted=null;$lastDirection=null;$review=false;$inferred=false;
  foreach($events as $event) {
   $raw=json_decode($event['raw_json']??'{}',true)?:[];
   $value=strtolower(preg_replace('/[^a-z]/i','',(string)($raw['AccessControllerEvent']['attendanceStatus']??'')));
   $direction=in_array($value,['checkin','breakin','overtimein'],true)?'in':(in_array($value,['checkout','breakout','overtimeout'],true)?'out':null);
   $time=$event['event_time'];$ts=strtotime($time);
   // Suppress same-direction/unknown rapid scans, but preserve explicit short visits.
   if($lastAccepted!==null && $ts-$lastAccepted<$rescanSeconds && (!$direction || $direction===$lastDirection)) continue;
   $lastAccepted=$ts;$lastDirection=$direction;
   if(!$direction) { $direction=$open===null?'in':'out';$inferred=true; }
   if($direction==='in') {
    if($open!==null) { $review=true; continue; }
    $open=$time;$first=$first??$time;
   } else {
    if($open===null) { $review=true;continue; }
    if($ts>strtotime($open)) $sessions[]=['in'=>$open,'out'=>$time];
    else $review=true;
    $open=null;
   }
  }
  $seconds=0;$away=0;$previous=null;
  foreach($sessions as $session) { $seconds+=strtotime($session['out'])-strtotime($session['in']);if($previous!==null)$away+=max(0,strtotime($session['in'])-strtotime($previous));$previous=$session['out']; }
  return ['check_in'=>$first,'check_out'=>$open===null?($previous??null):null,'sessions'=>$sessions,'minutes'=>(int)floor($seconds/60),'away_minutes'=>(int)floor($away/60),'review_required'=>$review||$inferred,'open'=>$open];
 }
}
