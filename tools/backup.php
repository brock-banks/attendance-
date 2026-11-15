<?php
if(PHP_SAPI!=='cli') { http_response_code(403); exit; }
require __DIR__.'/../includes/bootstrap.php';
$directory='F:/xampp/attendance-backups';
if(!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Cannot create backup directory');
$pdo=Database::pdo(); $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $pdo->beginTransaction();
try {
 $data=['created_at'=>date('c'),'tables'=>[]];
 foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
  if(!preg_match('/^[a-zA-Z0-9_]+$/',$table)) throw new RuntimeException('Unsupported table name');
  $data['tables'][$table]=['schema'=>$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1],'rows'=>$pdo->query('SELECT * FROM `'.$table.'`')->fetchAll()];
 }
 $pdo->commit();
} catch(Throwable $ex) { if($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
$file=$directory.'/attendance-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json.gz';
$bytes=gzencode(json_encode($data,JSON_THROW_ON_ERROR),9);
if(file_put_contents($file.'.tmp',$bytes)===false || !rename($file.'.tmp',$file)) throw new RuntimeException('Backup write failed');
// Every database backup is restored into a new isolated database and compared.
$test='attendance_restore_'.bin2hex(random_bytes(6));
$pdo->exec('CREATE DATABASE `'.$test.'` CHARACTER SET utf8mb4');
try {
 $restored=json_decode(gzdecode(file_get_contents($file)),true,512,JSON_THROW_ON_ERROR);
 $pdo->exec('USE `'.$test.'`');
 foreach($restored['tables'] as $name=>$table) {
  $pdo->exec($table['schema']);
  foreach($table['rows'] as $row) {
   $keys=array_keys($row);$sql='INSERT INTO `'.$name.'` (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')';
   $pdo->prepare($sql)->execute(array_values($row));
  }
  $actual=$pdo->query('SELECT * FROM `'.$name.'`')->fetchAll();
  $normalize=static function($rows) { $encoded=array_map(static fn($r)=>json_encode(array_map(static fn($v)=>$v===null?null:(string)$v,$r)),$rows); sort($encoded);return $encoded; };
  if($normalize($actual)!==$normalize($table['rows'])) throw new RuntimeException('Restore comparison failed: '.$name);
 }
 file_put_contents($file.'.verified',date('c')." All database tables restored and compared successfully.\n");
} finally { $pdo->exec('USE `'.DB_NAME.'`'); $pdo->exec('DROP DATABASE `'.$test.'`'); }
// Preserve photo and snapshot files alongside the database snapshot.
$media=$file.'.media'; mkdir($media,0700);
foreach(['photos','events'] as $folder) {
 $source=BASE_PATH.'/storage/'.$folder; if(!is_dir($source)) continue;
 $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
 mkdir($media.'/'.$folder,0700);
 foreach($iterator as $item) { if($item->isLink()) continue; $relative=substr($item->getPathname(),strlen($source)+1);$dest=$media.'/'.$folder.'/'.$relative; if($item->isDir()) mkdir($dest,0700,true); elseif(!copy($item->getPathname(),$dest) || hash_file('sha256',$dest)!==hash_file('sha256',$item->getPathname())) throw new RuntimeException('Media backup verification failed'); }
}
Shift::putSetting('backup_verified_at',date('Y-m-d H:i:s'));
Shift::putSetting('backup_verified_file',basename($file));
echo "Backup created and restore verified: ".$file."\n";
