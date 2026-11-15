<?php
/**
 * HTTP Listening endpoint for the Hikvision terminal.
 *
 * Configure the terminal (web UI or iVMS):
 *   Network > HTTP Listening  (or  Event > Linkage > Notify Surveillance Center)
 *   URL:  http://<this-server-ip>/attendance/api/event-receiver.php
 *   (append ?token=XXX if you set EVENT_TOKEN in config.php)
 *
 * The device POSTs either raw JSON or multipart/form-data (JSON part + snapshot).
 * This endpoint is tolerant of both shapes and of firmware field-name variations.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

// Optional shared-token gate.
if (EVENT_TOKEN !== '' && !hash_equals(EVENT_TOKEN, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$json = null;

// 1) Raw JSON body.
$decoded = json_decode($raw, true);
if (is_array($decoded) && isset($decoded['eventType']) || (is_array($decoded) && isset($decoded['AccessControllerEvent']))) {
    $json = $decoded;
}

// 2) Multipart: JSON arrives as a form field; find the one that decodes.
if ($json === null && !empty($_POST)) {
    foreach ($_POST as $val) {
        $d = json_decode((string)$val, true);
        if (is_array($d) && (isset($d['eventType']) || isset($d['AccessControllerEvent']))) {
            $json = $d;
            break;
        }
    }
}

if (!is_array($json)) {
    // Nothing usable. Acknowledge so the device is happy.
    echo json_encode(['ok' => true, 'note' => 'no event payload']);
    exit;
}

// Record that the device is actively pushing (heartbeats included) — the
// watchdog in cron.php uses this to detect and recover a dropped subscription.
try { Shift::putSetting('last_push_at', date('Y-m-d H:i:s')); } catch (Throwable $ex) {}

// Heartbeats / keepalives: acknowledge but don't store as attendance events.
if (($json['eventType'] ?? '') === 'heartBeat') {
    echo json_encode(['ok' => true, 'note' => 'heartbeat']);
    exit;
}

$ace = $json['AccessControllerEvent'] ?? [];

// Extract employee number across known firmware field names.
$empNo = $ace['employeeNoString'] ?? $ace['employeeNo'] ?? $json['employeeNoString'] ?? null;
$empNo = ($empNo === '' || $empNo === '0') ? null : $empNo;

$name = $ace['name'] ?? $json['name'] ?? null;

// Event time (ISO8601 with tz) -> MySQL DATETIME.
$dt = $json['dateTime'] ?? $ace['dateTime'] ?? date('c');
if (!is_string($dt) || strtotime($dt) === false) { http_response_code(422); echo json_encode(['ok'=>false, 'error'=>'Invalid event time']); exit; }
$eventTime = date('Y-m-d H:i:s', strtotime($dt));

// Save snapshot if one was uploaded with the event.
$imagePath = null;
if (!empty($_FILES)) {
    foreach ($_FILES as $f) {
        if (is_uploaded_file($f['tmp_name']) && @getimagesize($f['tmp_name'])) {
            $fn = 'evt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.jpg';
            if (move_uploaded_file($f['tmp_name'], EVENT_IMG_DIR . '/' . $fn)) {
                $imagePath = 'storage/events/' . $fn;
            }
            break;
        }
    }
}

$event = [
    'emp_no'      => $empNo,
    'name'        => $name,
    'event_time'  => $eventTime,
    'major_event' => $ace['majorEventType'] ?? null,
    'sub_event'   => $ace['subEventType']   ?? null,
    'verify_mode' => $ace['currentVerifyMode'] ?? ($ace['verifyMode'] ?? null),
    'image_path'  => $imagePath,
    'raw_json'    => json_encode($json, JSON_UNESCAPED_UNICODE),
];

try {
    $id = Attendance::recordEvent($event);
    echo json_encode(['ok' => true, 'event_id' => $id]);
} catch (Throwable $ex) {
    http_response_code(500);
    error_log('event-receiver: ' . $ex->getMessage());
    echo json_encode(['ok' => false, 'error' => 'server error']);
}
