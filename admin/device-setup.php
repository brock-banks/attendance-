<?php
$active = ''; $pageTitle = 'Device setup';
require_once dirname(__DIR__) . '/includes/header.php';

$isapi = new IsapiClient();
$result = ''; $resultClass = '';

// Detect this server's LAN IP as a sensible default for the form.
function detect_lan_ip(): string {
    $candidates = [];
    if (function_exists('net_get_interfaces')) {
        foreach (net_get_interfaces() as $if) {
            foreach ($if['unicast'] ?? [] as $u) {
                $ip = $u['address'] ?? '';
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && !str_starts_with($ip, '127.') && !str_starts_with($ip, '169.254.')) {
                    $candidates[] = $ip;
                }
            }
        }
    }
    if (!$candidates) {
        $host = gethostbyname(gethostname());
        if ($host && !str_starts_with($host, '127.')) $candidates[] = $host;
    }
    return $candidates[0] ?? '';
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

if ($action === 'test') {
    $r = $isapi->deviceInfo();
    if (in_array($r['code'], [200, 201], true)) {
        $resultClass = 'ok';
        $result = "Connected to the terminal. Device responded:\n" . $r['body'];
    } else {
        $resultClass = 'err';
        $result = "Could not reach the terminal (HTTP {$r['code']}).\n" . $r['body']
            . "\n\nCheck DEVICE_IP / DEVICE_USER / DEVICE_PASS in config/config.php and that this PC can ping the device.";
    }
}

if ($action === 'list') {
    $r = $isapi->listHttpHosts();
    $resultClass = in_array($r['code'], [200,201], true) ? 'ok' : 'err';
    $result = "HTTP (listening) hosts on device (HTTP {$r['code']}):\n" . $r['body'];
}

if ($action === 'register') {
    $ip   = trim($_POST['server_ip'] ?? '');
    $port = (int)($_POST['port'] ?? 80);
    $path = '/attendance/api/event-receiver.php' . (EVENT_TOKEN !== '' ? '?token=' . rawurlencode(EVENT_TOKEN) : '');
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $resultClass = 'err'; $result = 'Enter a valid IPv4 address for this PC.';
    } else {
        $r = $isapi->setHttpHost($ip, $port, $path);
        if (in_array($r['code'], [200, 201], true)) {
            // Remember the target so the cron watchdog can re-register if pushes stall.
            Shift::putSetting('event_host_ip', $ip);
            Shift::putSetting('event_host_port', (string)$port);
            $resultClass = 'ok';
            $result = "Registered this server as the terminal's event host.\n"
                . "Events will POST to http://$ip:$port$path\n\n"
                . "Next: in the terminal, enable \"Notify Surveillance Center\" on the "
                . "Face / Authentication event so it actually fires, then do a test scan.";
        } else {
            $resultClass = 'err';
            $result = "Device rejected the host (HTTP {$r['code']}).\n" . $r['body']
                . "\n\nYour firmware may not accept ISAPI host registration — use the web-UI method in the guide instead.";
        }
    }
}

$defaultIp = detect_lan_ip();
?>
<h1>Device setup <small>DS-K1T342MFWX-E1</small></h1>
<p class="muted">Configure the terminal in <?= e(DEVICE_IP) ?> to push scans to this app.
First fill device details in <code>config/config.php</code>, then use the buttons below.</p>

<section class="grid2">
  <div class="panel">
    <h2>1 · Test connection</h2>
    <p class="muted">Confirms the IP and admin credentials in config.php work.</p>
    <form method="post"><input type="hidden" name="action" value="test">
      <button class="btn">Test device connection</button></form>
  </div>

  <div class="panel">
    <h2>2 · Register event host</h2>
    <form method="post">
      <input type="hidden" name="action" value="register">
      <label>This PC's LAN IP
        <input name="server_ip" value="<?= e($defaultIp) ?>" placeholder="e.g. 192.168.1.50" required></label>
      <label>Apache port <input name="port" value="80" style="max-width:120px"></label>
      <button class="btn">Register this server on the device</button>
    </form>
    <p style="margin-top:10px"><a href="?action=list">View hosts currently on device →</a></p>
  </div>
</section>

<?php if ($result): ?>
  <div class="alert <?= e($resultClass) ?>" style="white-space:pre-wrap;font-family:ui-monospace,monospace;font-size:12px"><?= e($result) ?></div>
<?php endif; ?>

<div class="panel" style="margin-top:16px">
  <h2>3 · Enable the linkage on the terminal</h2>
  <ol class="muted" style="line-height:1.9">
    <li>Open the terminal's web UI: <b>http://<?= e(DEVICE_IP) ?></b> (admin login).</li>
    <li>Go to <b>Configuration → Event → Basic Event</b> (or <b>Linkage</b>).</li>
    <li>On the <b>Face Authentication / Access</b> event, tick
        <b>Notify Surveillance Center</b> and save.</li>
    <li>Do a test face scan at the terminal, then open the
        <a href="<?= APP_BASE_URL ?>/admin/index.php">Dashboard</a> — the scan should appear in the live feed.</li>
  </ol>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
