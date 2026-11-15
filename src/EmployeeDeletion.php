<?php
class EmployeeDeletion {
 const TABLES=['attendance_corrections','overtime_reviews','shift_assignments','notifications','leaves','employee_profiles','attendance','events'];
 public static function remove(string $emp): void {
  $pdo=Database::pdo();$pdo->beginTransaction();$paths=[];
  try{$person=Database::q('SELECT * FROM employees WHERE emp_no=? FOR UPDATE',[$emp])->fetch();if(!$person)throw new InvalidArgumentException('Employee not found.');
   if(!empty($person['photo_path']))$paths[]=$person['photo_path'];
   foreach(Database::q('SELECT image_path FROM events WHERE emp_no=? AND image_path IS NOT NULL',[$emp])->fetchAll(PDO::FETCH_COLUMN) as $path)$paths[]=$path;
   foreach(self::TABLES as $table)Database::q('DELETE FROM '.$table.' WHERE emp_no=?',[$emp]);
   Database::q('DELETE FROM settings WHERE k=?',[EmployeeSync::key($emp)]);Database::q('DELETE FROM employees WHERE emp_no=?',[$emp]);
   Database::q('INSERT INTO account_audit(actor_id,target_id,action,details) VALUES(?,0,?,?)',[(int)($_SESSION['admin_id']??0),'employee_deleted',json_encode(['emp_no'=>$emp,'name'=>$person['name']])]);$pdo->commit();
  }catch(Throwable $ex){$pdo->rollBack();throw $ex;}
  foreach(array_unique($paths) as $path){if(!preg_match('~^storage/(photos|events)/[a-zA-Z0-9_.-]+$~',$path))continue;if(Database::q('SELECT 1 FROM employees WHERE photo_path=? LIMIT 1',[$path])->fetchColumn() || Database::q('SELECT 1 FROM events WHERE image_path=? LIMIT 1',[$path])->fetchColumn())continue;@unlink(BASE_PATH.'/'.$path);}
 }
}
