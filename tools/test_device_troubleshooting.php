<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/bootstrap.php';
class TroubleshootingClient extends IsapiClient {
 public string $sent='';public bool $fail=false;
 public function listHttpHosts():array {return $this->fail?['code'=>503,'body'=>'Unavailable']:['code'=>200,'body'=>'<HttpHostNotificationList xmlns="http://www.isapi.org/ver20/XMLSchema"><HttpHostNotification><id>1</id><ipAddress>192.168.1.1</ipAddress><portNo>80</portNo><url>/old</url><SubscribeEvent><heartbeat>30</heartbeat><eventMode>all</eventMode></SubscribeEvent></HttpHostNotification></HttpHostNotificationList>'];}
 public function request(string $method,string $path,$body=null,string $contentType='application/json',array $extraHeaders=[]):array {$this->sent=$body;return ['code'=>200,'body'=>'OK'];}
}
$c=new TroubleshootingClient();$c->setHttpHost('192.168.100.28',80,'/attendance/api/event-receiver.php');$xml=simplexml_load_string($c->sent);
if((string)$xml->ipAddress!=='192.168.100.28' || (string)$xml->SubscribeEvent->heartbeat!=='30' || (string)$xml->SubscribeEvent->eventMode!=='all')throw new RuntimeException('Repair lost subscription settings');echo "PASS: Repair preserves heartbeat and event subscription\n";
$c->fail=true;$c->sent='';$r=$c->setHttpHost('192.168.100.28');if($r['code']!==503 || $c->sent!=='')throw new RuntimeException('Unsafe overwrite');echo "PASS: Unreadable device settings are not overwritten\n";
