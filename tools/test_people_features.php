<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/bootstrap.php';require BASE_PATH.'/src/EmployeeImport.php';
function peopleCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$prefix='qa_people_'.bin2hex(random_bytes(4));$tmp=tempnam(sys_get_temp_dir(),'people');$employees=[$prefix.'a',$prefix.'b'];
try {
peopleCheck(count(array_filter(EmployeeProfile::validate([])))===0,'All contact fields accept blanks');
try{EmployeeProfile::validate(['email'=>'invalid']);throw new RuntimeException('Invalid email accepted');}catch(InvalidArgumentException $ex){echo "PASS: Invalid optional email rejected\n";}
try{EmployeeProfile::validate(['joining_date'=>'2026-02-30']);throw new RuntimeException('Invalid date accepted');}catch(InvalidArgumentException $ex){echo "PASS: Invalid joining date rejected\n";}
$f=fopen($tmp,'w');fputcsv($f,EmployeeImport::COLUMNS,',','"','');foreach($employees as $emp)fputcsv($f,[$emp,'People QA','QA','','Coordinator','012345','qa@example.com','2026-11-01','Contact','098765'],',','"','');fclose($f);
$rows=EmployeeImport::preview($tmp);peopleCheck(count($rows)===2 && !$rows[0]['error'],'CSV validates optional fields');peopleCheck(!Database::q('SELECT 1 FROM employees WHERE emp_no=?',[$employees[0]])->fetchColumn(),'Preview does not create employees');peopleCheck(EmployeeImport::commit($rows)===2,'Confirmed import adds both employees');peopleCheck(EmployeeProfile::get($employees[0])['phone']==='012345','Phone keeps leading zero');
$duplicates=EmployeeImport::preview($tmp);peopleCheck($duplicates[0]['error']!=='' && $duplicates[1]['error']!=='','Existing employees flagged in preview');
try{EmployeeImport::commit($rows);throw new RuntimeException('Repeated import accepted');}catch(InvalidArgumentException $ex){echo "PASS: Repeated import prevented\n";}
Database::q("INSERT INTO attendance(emp_no,work_date,work_minutes,status) VALUES(?,'2026-11-01',60,'half')",[$employees[0]]);
EmployeeProfile::archive($employees[0],true);peopleCheck((int)Database::q('SELECT active FROM employees WHERE emp_no=?',[$employees[0]])->fetchColumn()===0 && EmployeeProfile::get($employees[0])['archived_at']!==null,'Archive deactivates employee');peopleCheck((int)Database::q('SELECT work_minutes FROM attendance WHERE emp_no=?',[$employees[0]])->fetchColumn()===60,'Archive preserves attendance');EmployeeProfile::archive($employees[0],false);peopleCheck((int)Database::q('SELECT active FROM employees WHERE emp_no=?',[$employees[0]])->fetchColumn()===1 && EmployeeProfile::get($employees[0])['archived_at']===null,'Restore reactivates employee');
}finally{foreach($employees as $emp)foreach(['attendance','employee_profiles','employees'] as $table)Database::q("DELETE FROM $table WHERE emp_no=?",[$emp]);@unlink($tmp);echo "Disposable fixtures removed.\n";}
