<?php
class EmployeeImport {
 const COLUMNS=['emp_no','name','department','card_no','job_title','phone','email','joining_date','emergency_name','emergency_phone'];
 public static function preview(string $path): array {
  $file=fopen($path,'rb');if(!$file)throw new InvalidArgumentException('Could not read the CSV.');
  try{$header=fgetcsv($file,0,',','"','');if(!$header)throw new InvalidArgumentException('The CSV is empty.');$header=array_map(static fn($v)=>trim((string)$v),$header);$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
   if(count(array_unique($header))!==count($header) || !in_array('emp_no',$header,true)||!in_array('name',$header,true))throw new InvalidArgumentException('Use the template headers, including emp_no and name.');
   if(array_diff($header,self::COLUMNS))throw new InvalidArgumentException('Unknown column. Use the provided template headers.');
   $rows=[];$seen=[];$line=1;
   while(($cells=fgetcsv($file,0,',','"',''))!==false){$line++;if(count($cells)===1 && trim((string)$cells[0])==='')continue;if(count($rows)>=500)throw new InvalidArgumentException('Import up to 500 employees at a time.');$error='';$data=[];
    try{if(count($cells)!==count($header))throw new InvalidArgumentException('Column count does not match the headers.');$data=array_map(static fn($v)=>trim((string)$v),array_combine($header,$cells));if(!mb_check_encoding(implode('',$data),'UTF-8'))throw new InvalidArgumentException('Save the file as CSV UTF-8.');
     if(empty($data['emp_no'])||empty($data['name']))throw new InvalidArgumentException('Employee number and name are required.');
     foreach(['emp_no'=>32,'name'=>128,'department'=>128,'card_no'=>64] as $key=>$max)if(mb_strlen($data[$key]??'')>$max)throw new InvalidArgumentException($key.' is too long.');
     $key=mb_strtolower($data['emp_no']);if(isset($seen[$key]))throw new InvalidArgumentException('Duplicate employee number in this file.');$seen[$key]=true;
     if(Database::q('SELECT 1 FROM employees WHERE emp_no=?',[$data['emp_no']])->fetchColumn())throw new InvalidArgumentException('Employee number already exists.');EmployeeProfile::validate($data);
    }catch(InvalidArgumentException $ex){$error=$ex->getMessage();}
    $rows[]=['line'=>$line,'data'=>$data,'error'=>$error];
   }if(!$rows)throw new InvalidArgumentException('Add employee rows below the header.');return $rows;
  }finally{fclose($file);}
 }
 public static function commit(array $rows): int {
  if(!$rows)throw new InvalidArgumentException('Preview a CSV first.');$pdo=Database::pdo();$pdo->beginTransaction();
  try{foreach($rows as $row){if($row['error']!=='')throw new InvalidArgumentException('Fix the preview errors before importing.');$d=$row['data'];if(Database::q('SELECT 1 FROM employees WHERE emp_no=?',[$d['emp_no']])->fetchColumn())throw new InvalidArgumentException('Employee '.$d['emp_no'].' already exists. Upload the file again to refresh the preview.');Database::q('INSERT INTO employees(emp_no,name,department,card_no) VALUES(?,?,?,?)',[$d['emp_no'],$d['name'],$d['department']??null,$d['card_no']??null]);EmployeeProfile::save($d['emp_no'],$d);}$pdo->commit();return count($rows);}catch(Throwable $ex){$pdo->rollBack();throw $ex;}
 }
}
