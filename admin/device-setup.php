<?php
require_once dirname(__DIR__).'/includes/bootstrap.php';
Auth::requireRole(['admin']);
$active='device';$pageTitle='Device troubleshooting';$notice='';$error='';$verified=null;
$currentIp=IsapiClient::configuredIp();$candidate=trim((string)($_POST['device_ip']??$currentIp));
$localIps=[];if(function_exists('net_get_interfaces'))foreach(net_get_interfaces() as $interface)foreach($interface['unicast']??[] as $entry){$ip=$entry['address']??'';if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) && !str_starts_with($ip,'127.') && !str_starts_with($ip,'169.254.'))$localIps[]=$ip;}
$localIps=array_values(array_unique($localIps));
$serverIp=in_array($_SERVER['SERVER_ADDR']??'',$localIps,true)?$_SERVER['SERVER_ADDR']:(in_array(Shift::setting('event_host_ip',''),$localIps,true)?Shift::setting('event_host_ip',''):($localIps[0]??''));
if($_SERVER['REQUEST_METHOD']==='POST')try{
 $action=(string)($_POST['action']??'');
 if($action==='test'){
  unset($_SESSION['verified_device']);
  if(!filter_var($candidate,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) || !(str_starts_with($candidate,'10.') || str_starts_with($candidate,'192.168.') || preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./',$candidate)))throw new InvalidArgumentException('Enter the local IPv4 address displayed in the terminal’s Wi-Fi or Ethernet settings.');
  $r=(new IsapiClient($candidate))->deviceInfo();
  if($r['code']===401 || $r['code']===403)throw new InvalidArgumentException('The terminal responded, but its saved login was rejected. Contact your administrator to update the credentials.');
  if($r['code']!==200)throw new InvalidArgumentException('Cannot connect. Check the terminal is powered on, connected to the same network, and that this is its active Wi-Fi or Ethernet IP.');
  $xml=@simplexml_load_string($r['body']);if(!$xml || (string)$xml->serialNumber==='' || (string)$xml->model==='')throw new InvalidArgumentException('This address did not return valid terminal information.');
  $verified=['ip'=>$candidate,'model'=>(string)$xml->model,'serial'=>(string)$xml->serialNumber,'token'=>bin2hex(random_bytes(24)),'at'=>time()];
  $known=Shift::setting('device_serial','');if($known!=='' && $known!==$verified['serial'])throw new InvalidArgumentException('This is a different terminal. Its address was not saved.');
  $_SESSION['verified_device']=$verified;$notice='Connection successful. Check the device details below, then save this address. This test does not confirm that scans are arriving.';
 }elseif($action==='save'){
  $verified=$_SESSION['verified_device']??null;
  if(!$verified || time()-$verified['at']>300 || !hash_equals($verified['token'],(string)($_POST['verified_token']??'')))throw new InvalidArgumentException('Please test the address again before saving.');
  Shift::putSetting('device_ip',$verified['ip']);Shift::putSetting('device_serial',$verified['serial']);$currentIp=$verified['ip'];$candidate=$currentIp;unset($_SESSION['verified_device']);$verified=null;$notice='Terminal address saved. All device connections now use this address.';
 }elseif($action==='repair'){
  $target=(string)($_POST['server_ip']??'');$port=filter_var($_POST['port']??'',FILTER_VALIDATE_INT);
  if(!in_array($target,$localIps,true) || !$port || $port<1 || $port>65535)throw new InvalidArgumentException('Choose a current address of this PC and a valid web-server port.');
  $path=APP_BASE_URL.'/api/event-receiver.php'.(EVENT_TOKEN!==''?'?token='.rawurlencode(EVENT_TOKEN):'');
  $r=(new IsapiClient())->setHttpHost($target,$port,$path);
  $xml=@simplexml_load_string($r['body']);
  if(!in_array($r['code'],[200,201],true) || !$xml || (int)$xml->statusCode!==1)throw new InvalidArgumentException('The terminal did not accept the repair. Test its connection first, then try again.');
  Shift::putSetting('event_host_ip',$target);Shift::putSetting('event_host_port',(string)$port);$notice='Receiving address updated. Wait 30–60 seconds, refresh this page, then make a real face or card scan.';
 }
}catch(Throwable $ex){$error=$ex instanceof InvalidArgumentException?$ex->getMessage():'The check could not finish. Try again or contact your administrator.';error_log('Device troubleshooting: '.$ex->getMessage());}
$last=Shift::setting('last_push_at','');$age=$last?max(0,time()-strtotime($last)):null;
$lastScan=Database::q("SELECT MAX(event_time) FROM events WHERE major_event=5 AND sub_event=75 AND emp_no IS NOT NULL AND emp_no<>''")->fetchColumn();
require BASE_PATH.'/includes/header.php';
?>
<h1>Device troubleshooting</h1><p>Fix a changed terminal address and check whether data is reaching the app.</p>
<?php if($notice): ?><div class="alert ok" role="status"><?= e($notice) ?></div><?php endif; ?><?php if($error): ?><div class="alert err" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="panel"><h2>Incoming data</h2><p><?= status_label($age!==null && $age<90?'Receiving updates':'No recent updates') ?> · <?= $age===null?'No updates recorded':($age<60?'Less than a minute ago':(int)floor($age/60).' minutes since the last update') ?></p><p>Last stored attendance scan: <?= e($lastScan?:'None recorded') ?> <span class="muted">(application time)</span></p><p class="muted">Updates may be heartbeats. A successful connection test alone does not prove that attendance scans are arriving.</p><a class="btn ghost" href="device-setup.php">Refresh status</a></div>
<section class="panel" style="margin-top:20px"><h2>1. Check the terminal address</h2><p>On the terminal, open Network settings and read the IP for the connection it actually uses: Wi-Fi or Ethernet.</p><p>Saved address: <strong><?= e($currentIp) ?></strong></p>
<form method="post" class="filters"><?= Auth::field() ?><input type="hidden" name="action" value="test"><label>Terminal IP address<input name="device_ip" value="<?= e($candidate) ?>" placeholder="192.168.100.16" required></label><button class="btn">Test address</button></form>
<?php if($verified && !$error): ?><div class="alert info"><p>Device: <?= e($verified['model']) ?><br>Serial: <?= e($verified['serial']) ?><br>Address: <?= e($verified['ip']) ?></p><form method="post"><?= Auth::field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="verified_token" value="<?= e($verified['token']) ?>"><button class="btn">Save verified address</button></form></div><?php endif; ?></section>
<section class="panel" style="margin-top:20px"><h2>2. Repair incoming scans</h2><p>Use this if the connection test succeeds but updates have stopped. Choose this PC’s address on the same network as the terminal.</p>
<form method="post" class="filters"><?= Auth::field() ?><input type="hidden" name="action" value="repair"><label>This PC’s address<select name="server_ip" required><?php foreach($localIps as $ip): ?><option value="<?= e($ip) ?>" <?= $ip===$serverIp?'selected':'' ?>><?= e($ip) ?></option><?php endforeach; ?></select></label><label>Web-server port<input type="number" name="port" min="1" max="65535" value="<?= (int)Shift::setting('event_host_port','80') ?>" required></label><button class="btn ghost">Repair receiving address</button></form></section>
<section class="panel" style="margin-top:20px"><h2>3. Verify a real scan</h2><p>Scan a face or card on the terminal, then refresh this page. Check the last stored attendance scan and open the attendance report.</p><a href="reports.php">Open attendance report</a><details><summary>Still not receiving data?</summary><ul><li>Confirm both devices are connected to the same local network.</li><li>Keep this PC awake with the attendance server running.</li><li>Check that event notifications to the surveillance center are enabled on the terminal.</li><li>Ask your network administrator to reserve addresses for this PC and terminal to prevent recurring changes.</li></ul></details></section>
<?php require BASE_PATH.'/includes/footer.php'; ?>
