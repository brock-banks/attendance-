<?php
class EmployeeSync {
 public static function fingerprint(array $e): string {return hash('sha256',json_encode([$e['name'],$e['card_no'],$e['photo_path']]));}
 public static function key(string $emp): string {return 'sync_'.substr(hash('sha256',$emp),0,40);}
 public static function state(array $e): string {
  $saved=json_decode(Shift::setting(self::key($e['emp_no']),''),true);
  if(!$saved)return $e['on_device']?'Sync not verified':'Saved in app';
  if(($saved['hash']??'')!==self::fingerprint($e))return 'Changes to sync';
  return ($saved['ok']??false)?'Synced':'Sync failed';
 }
 public static function run(array $e,?IsapiClient $client=null): bool {
  $client=$client??new IsapiClient();$ok=false;
  try {$result=$client->addUser($e['emp_no'],$e['name'],$e['card_no']?:null);$ok=self::accepted($result);
   if($ok && $e['photo_path'])$ok=self::accepted($client->setFace($e['emp_no'],BASE_PATH.'/'.$e['photo_path']));
  }catch(Throwable $ex){error_log('Employee sync: '.$ex->getMessage());}
  Shift::putSetting(self::key($e['emp_no']),json_encode(['hash'=>self::fingerprint($e),'ok'=>$ok,'at'=>date('c')]));
  if($ok)Database::q('UPDATE employees SET on_device=1 WHERE emp_no=?',[$e['emp_no']]);
  return $ok;
 }
 public static function accepted(array $result): bool {
  if(!in_array($result['code'],[200,201],true))return false;
  $body=json_decode($result['body'],true);
  return !isset($body['statusCode']) || (int)$body['statusCode']===1;
 }
}
