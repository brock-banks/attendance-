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
