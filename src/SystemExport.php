<?php
/** Portable ZIP export without requiring the optional PHP ZIP extension. */
class SystemExport {
 public static function build(): string {
  $zip=tempnam(sys_get_temp_dir(),'attendance_export_');$sql=tempnam(sys_get_temp_dir(),'attendance_sql_');$out=null;$db=null;
  try{
   $db=fopen($sql,'wb');$pdo=Database::pdo();$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();
   try{fwrite($db,"CREATE DATABASE IF NOT EXISTS `attendance` CHARACTER SET utf8mb4;\nUSE `attendance`;\nSET NAMES utf8mb4;\n");foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/',$table))throw new RuntimeException('Unsupported table');fwrite($db,$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1].";\n");$rows=$pdo->query('SELECT * FROM `'.$table.'`');while($row=$rows->fetch()){ $keys=array_map(static fn($key)=>'`'.$key.'`',array_keys($row));$values=array_map(static fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($row));fwrite($db,'INSERT INTO `'.$table.'` ('.implode(',',$keys).') VALUES ('.implode(',',$values).");\n");}}$pdo->commit();}catch(Throwable $ex){$pdo->rollBack();throw $ex;}fclose($db);$db=null;
   $out=fopen($zip,'wb');$central='';$count=0;
   $add=function(string $path,string $name)use($out,&$central,&$count){
    $size=filesize($path);$offset=ftell($out);if($size>0xffffffff || $offset+$size>0xffffffff || ++$count>65535)throw new RuntimeException('Export exceeds ZIP size limit.');
    $crc=(int)hexdec(hash_file('crc32b',$path));$flags=2048;$date=33;$time=0;
    fwrite($out,pack('VvvvvvVVVvv',0x04034b50,20,$flags,0,$time,$date,$crc,$size,$size,strlen($name),0).$name);
    $in=fopen($path,'rb');$copied=stream_copy_to_stream($in,$out);fclose($in);if($copied!==$size)throw new RuntimeException('File changed while exporting. Try again.');
    $central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,$flags,0,$time,$date,$crc,$size,$size,strlen($name),0,0,0,0,0,$offset).$name;
   };
   $add($sql,'database.sql');
   $add(BASE_PATH.'/tools/TRANSFER-README.txt','MOVE-TO-NEW-PC.txt');
   foreach(['admin','api','assets','config','includes','sql','src','storage','tools'] as $folder){$root=BASE_PATH.'/'.$folder;if(!is_dir($root))continue;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){if(!$file->isFile() || $file->isLink())continue;$relative=str_replace('\\','/',substr($file->getPathname(),strlen(BASE_PATH)+1));$add($file->getPathname(),'attendance/'.$relative);}}
   foreach(['index.php','cron.php','install.php','README.md'] as $file)if(is_file(BASE_PATH.'/'.$file))$add(BASE_PATH.'/'.$file,'attendance/'.$file);
   $offset=ftell($out);fwrite($out,$central);fwrite($out,pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($central),$offset,0));fclose($out);$out=null;return $zip;
  }catch(Throwable $ex){if(is_resource($out))fclose($out);@unlink($zip);throw $ex;}finally{if(is_resource($db))fclose($db);@unlink($sql);}
 }
}
