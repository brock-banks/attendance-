<?php
/**
 * Alert runner — schedule this to run every ~10 minutes.
 *
 * Windows Task Scheduler (run as your user):
 *   Program:  F:\xampp\php\php.exe
 *   Args:     F:\xampp\htdocs\attendance\cron.php
 *   Trigger:  repeat every 10 minutes
 *
 * It sends late / absent / daily-summary emails per your Settings → Alerts,
 * and only sends each alert once per day.
 */
require __DIR__ . '/includes/bootstrap.php';

// --- Push watchdog: if the terminal has gone quiet, re-register the event host ---
$ip   = Shift::setting('event_host_ip', '');
$port = (int)Shift::setting('event_host_port', '80');
$last = Shift::setting('last_push_at', '');
if ($ip !== '') {
    $staleSec = $last ? (time() - strtotime($last)) : PHP_INT_MAX;
    if ($staleSec > 300) {   // no pushes (not even a heartbeat) for >5 minutes
        try {
            $r = (new IsapiClient())->setHttpHost($ip, $port, '/attendance/api/event-receiver.php');
            echo date('c') . " watchdog: pushes stale ({$staleSec}s) — re-registered {$ip}:{$port} -> HTTP {$r['code']}" . PHP_EOL;
        } catch (Throwable $e) {
            echo date('c') . ' watchdog error: ' . $e->getMessage() . PHP_EOL;
        }
    }
}

$res = Notifier::run();
echo date('c') . ' ' . json_encode($res) . PHP_EOL;
