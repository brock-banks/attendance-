<?php
class Report {
 public static function checkoutState(array $row, ?int $now=null): string {
  if(!empty($row['check_out'])) return 'Complete';
  $shift=!empty($row['shift_snapshot'])?json_decode($row['shift_snapshot'],true):Shift::forEmployee($row['emp_no'],$row['work_date']);
  $end=$shift ? $row['work_date'].' '.$shift['end_time'] : $row['work_date'].' 23:59:59';
  if($shift && Shift::crossesMidnight($shift)) $end=date('Y-m-d',strtotime($row['work_date'].' +1 day')).' '.$shift['end_time'];
  return ($now??time())>strtotime($end) ? 'Missing check-out' : 'Still working';
 }
 public static function filters(array $input): array {
  $valid=static function($v,$fallback){return is_string($v)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)&&date('Y-m-d',strtotime($v))===$v?$v:$fallback;};
  $f=['from'=>$valid($input['from']??null,date('Y-m-01')),'to'=>$valid($input['to']??null,date('Y-m-d')),'emp'=>trim((string)($input['emp']??'')),'department'=>trim((string)($input['department']??'')),'status'=>(string)($input['status']??'')];
  if($f['from']>$f['to']) [$f['from'],$f['to']]=[$f['to'],$f['from']];
  $f['sort']=in_array($input['sort']??'', ['date','employee','worked','overtime'],true)?$input['sort']:'date';
  $f['direction']=($input['direction']??'desc')==='asc'?'asc':'desc';
  return $f;
 }
 private static function query(array $f): array {
  $where='a.work_date BETWEEN ? AND ?';$params=[$f['from'],$f['to']];
  if($f['emp']!==''){$where.=' AND (a.emp_no LIKE ? OR e.name LIKE ?)';$params[]='%'.$f['emp'].'%';$params[]='%'.$f['emp'].'%';}
  if($f['department']!==''){$where.=' AND e.department=?';$params[]=$f['department'];}
  $start="COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.shift_snapshot,'$.start_time')),s.start_time,'00:00:00')";
  $end="COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.shift_snapshot,'$.end_time')),s.end_time,'23:59:59')";
  $finish="TIMESTAMP(DATE_ADD(a.work_date, INTERVAL IF($end <= $start,1,0) DAY),$end)";
  if(in_array($f['status'],['Missing check-out','Still working'],true)) {$where.=' AND a.check_out IS NULL AND '.$finish.($f['status']==='Missing check-out'?' < ?':' >= ?');$params[]=date('Y-m-d H:i:s');}
  elseif($f['status']!==''){$where.=' AND a.status=?';$params[]=$f['status'];}
  return ["FROM attendance a LEFT JOIN employees e ON e.emp_no=a.emp_no LEFT JOIN shifts s ON s.id=a.shift_id WHERE $where",$params];
 }
 public static function count(array $f): int {[$sql,$params]=self::query($f);return (int)Database::q('SELECT COUNT(*) '.$sql,$params)->fetchColumn();}
 public static function rows(array $f, ?int $page=null): array {
  [$sql,$params]=self::query($f);
  $sort=['date'=>'a.work_date','employee'=>'name','worked'=>'a.work_minutes','overtime'=>'a.overtime_minutes'][$f['sort']??'date']??'a.work_date';$direction=($f['direction']??'desc')==='asc'?'ASC':'DESC';
  $limit=$page===null?'':' LIMIT 50 OFFSET '.(max(0,$page-1)*50);
  $rows=Database::q("SELECT a.*,COALESCE(e.name,a.emp_no) name,e.department,s.name AS shift $sql ORDER BY $sort $direction,a.id DESC $limit",$params)->fetchAll();
  foreach($rows as &$r){$r['checkout_state']=self::checkoutState($r);$review=Overtime::current($r);$r['approved_overtime_minutes']=$review && hash_equals($review['attendance_hash'],Overtime::fingerprint($r))?(int)$review['approved_minutes']:0;}unset($r);
  return $rows;
 }
}
