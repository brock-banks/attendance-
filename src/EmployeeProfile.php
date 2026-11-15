<?php
class EmployeeProfile {
 const FIELDS=['job_title'=>'Job title','phone'=>'Phone','email'=>'Email','joining_date'=>'Joining date','emergency_name'=>'Emergency contact name','emergency_phone'=>'Emergency contact phone'];
 public static function get(string $emp): array {return Database::q('SELECT * FROM employee_profiles WHERE emp_no=?',[$emp])->fetch()?:[];}
 public static function validate(array $input): array {
  $data=[];foreach(self::FIELDS as $key=>$label){$value=trim((string)($input[$key]??''));$max=$key==='email'?254:(str_contains($key,'phone')?64:128);if(mb_strlen($value)>$max)throw new InvalidArgumentException($label.' is too long.');$data[$key]=$value?:null;}
  if($data['email'] && !filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address or leave it blank.');
  if($data['joining_date'] && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$data['joining_date']) || date('Y-m-d',strtotime($data['joining_date']))!==$data['joining_date']))throw new InvalidArgumentException('Enter a valid joining date or leave it blank.');
  return $data;
 }
 public static function save(string $emp,array $data): void {
  $data=self::validate($data);Database::q('INSERT INTO employee_profiles(emp_no,job_title,phone,email,joining_date,emergency_name,emergency_phone) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE job_title=VALUES(job_title),phone=VALUES(phone),email=VALUES(email),joining_date=VALUES(joining_date),emergency_name=VALUES(emergency_name),emergency_phone=VALUES(emergency_phone)',array_merge([$emp],array_values($data)));
 }
 public static function fields(array $values): string {
  $html='<details class="schedule-disclosure"><summary>Contact details <span class="muted">All optional</span></summary><div class="employee-fields">';
  foreach(self::FIELDS as $key=>$label){$type=$key==='email'?'email':($key==='joining_date'?'date':(str_contains($key,'phone')?'tel':'text'));$html.='<label>'.e($label).'<input type="'.$type.'" name="'.$key.'" value="'.e($values[$key]??'').'"></label>';}
  return $html.'</div></details>';
 }
 public static function archive(string $emp,bool $archive): void {
  $pdo=Database::pdo();$pdo->beginTransaction();try{if(!Database::q('SELECT emp_no FROM employees WHERE emp_no=? FOR UPDATE',[$emp])->fetchColumn())throw new InvalidArgumentException('Employee not found.');Database::q('INSERT INTO employee_profiles(emp_no,archived_at) VALUES(?,?) ON DUPLICATE KEY UPDATE archived_at=VALUES(archived_at)',[$emp,$archive?date('Y-m-d H:i:s'):null]);Database::q('UPDATE employees SET active=? WHERE emp_no=?',[$archive?0:1,$emp]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}
 }
}
