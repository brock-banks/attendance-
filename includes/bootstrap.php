<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Database.php';
require_once BASE_PATH . '/src/Auth.php';
require_once BASE_PATH . '/src/IsapiClient.php';
require_once BASE_PATH . '/src/Shift.php';
require_once BASE_PATH . '/src/Calendar.php';
require_once BASE_PATH . '/src/Leave.php';
require_once BASE_PATH . '/src/Roster.php';
require_once BASE_PATH . '/src/Attendance.php';
require_once BASE_PATH . '/src/Mailer.php';
require_once BASE_PATH . '/src/Notifier.php';

// Ensure storage dirs exist.
foreach ([UPLOAD_DIR, EVENT_IMG_DIR] as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

require_once BASE_PATH . '/src/Correction.php';

// Apply access rules before any admin page can emit output or process actions.
if (PHP_SAPI !== 'cli' && strpos(str_replace('\\','/',$_SERVER['SCRIPT_FILENAME'] ?? ''), '/admin/') !== false) {
    $route=basename($_SERVER['SCRIPT_FILENAME']);
    if (!in_array($route,['login.php','forgot-password.php'],true)) {
        Auth::requireLogin();
        if (in_array($route,['settings.php','device-setup.php','accounts.php'],true)) Auth::requireRole(['admin']);
        if ($route==='enroll.php') Auth::requireRole(['admin','hr']);
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && !in_array($route,['logout.php','reports.php','password.php'],true)) Auth::requireRole(['admin','hr']);
    }
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') Auth::verifyCsrf();
}

require_once BASE_PATH . '/src/Report.php';

require_once BASE_PATH . '/src/WorkSessions.php';

require_once BASE_PATH . '/src/Overtime.php';

/** Consistent status: semantic color, decorative icon, sentence-case label. */
function status_label(string $value): string {
    $key=strtolower(trim($value));
    $label=['half'=>'Half day','yes'=>'Yes','no'=>'No'][$key]??mb_strtoupper(mb_substr(trim($value),0,1)).mb_substr(trim($value),1);
    $tone=in_array($key,['present','approved','active','complete','still working','yes','synced','receiving updates'],true)?'success':(in_array($key,['late','half','missing check-out','review punches','needs review','review scan directions','long work day','not confirmed','changes to sync','no recent updates'],true)?'warning':(in_array($key,['absent','rejected','error','sync failed'],true)?'danger':'neutral'));
    return '<span class="status-label status-'.$tone.'"><span class="status-symbol" aria-hidden="true"></span><span>'.e($label).'</span></span>';
}

require_once BASE_PATH . '/src/EmployeePhoto.php';

require_once BASE_PATH . '/src/EmployeeSync.php';

require_once BASE_PATH . '/src/EmployeeProfile.php';
