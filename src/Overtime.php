<?php
class Overtime {
 public static function fingerprint(array $a): string { return hash('sha256',json_encode([$a['check_in'],$a['check_out'],$a['work_minutes'],$a['overtime_minutes'],$a['sessions_json']])); }
 public static function current(array $a): ?array { $r=Database::q('SELECT * FROM overtime_reviews WHERE emp_no=? AND work_date=? ORDER BY id DESC LIMIT 1',[$a['emp_no'],$a['work_date']])->fetch();return $r?:null; }
 public static function review(string $emp,string $day,int $minutes,string $decision,string $reason,string $expected): void {
  if(!in_array($decision,['approved','rejected'],true) || strlen(trim($reason))<5 || strlen($reason)>1000) throw new InvalidArgumentException('Choose a decision and provide a reason of 5–1000 characters.');
  $lock=Attendance::lockName($emp);if(!Database::q('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn())throw new RuntimeException('Attendance is busy.');
  try { Database::pdo()->beginTransaction();$a=Database::q('SELECT * FROM attendance WHERE emp_no=? AND work_date=? FOR UPDATE',[$emp,$day])->fetch();
   if(!$a || !hash_equals(self::fingerprint($a),$expected)) throw new InvalidArgumentException('Attendance changed. Reload before reviewing.');
   if($decision==='rejected') $minutes=0;
   if($minutes<0 || $minutes>(int)$a['overtime_minutes'])throw new InvalidArgumentException('Approved minutes must be between zero and calculated overtime.');
   $previous=self::current($a);
   if($previous && $previous['attendance_hash']===$expected && $previous['decision']===$decision && (int)$previous['approved_minutes']===$minutes && $previous['reason']===trim($reason)){Database::pdo()->commit();return;}
   Database::q('INSERT INTO overtime_reviews(emp_no,work_date,attendance_hash,calculated_minutes,approved_minutes,decision,reason,reviewer) VALUES(?,?,?,?,?,?,?,?)',[$emp,$day,$expected,$a['overtime_minutes'],$decision==='approved'?$minutes:0,$decision,trim($reason),Auth::user()]);Database::pdo()->commit();
  }catch(Throwable $e){if(Database::pdo()->inTransaction())Database::pdo()->rollBack();throw $e;}finally{Database::q('SELECT RELEASE_LOCK(?)',[$lock]);}
 }
}
