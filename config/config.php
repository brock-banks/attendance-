<?php
/**
 * Central configuration.
 * Edit the DEVICE_* values to match your DS-K1T342MFWX-E1 terminal.
 */

// ---- Database (XAMPP defaults) ----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'attendance');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Hikvision terminal (ISAPI) ----
define('DEVICE_IP',   '192.168.100.16'); // <-- your terminal's IP
define('DEVICE_PORT', 80);               // ISAPI HTTP port (80, or 443 for https)
define('DEVICE_USER', 'admin');          // <-- terminal admin login
define('DEVICE_PASS', 'SRS25326');      // <-- terminal admin password
define('DEVICE_HTTPS', false);           // true if the terminal uses HTTPS

// ---- Email (SMTP) for alerts ----
// Normally configured in the UI: Settings → Email alerts (stored in the DB).
// These constants are only an optional fallback if you prefer to hardcode them.
define('SMTP_HOST', '');            // e.g. smtp.gmail.com  ('' = use DB setting)
define('SMTP_PORT', 587);           // 587 for TLS, 465 for SSL
define('SMTP_SECURE', 'tls');       // 'tls' | 'ssl' | '' (none)
define('SMTP_USER', '');            // SMTP login (usually your email)
define('SMTP_PASS', '');            // SMTP password / app password
define('SMTP_FROM', '');            // From address ('' = use SMTP_USER)
define('SMTP_FROM_NAME', 'Attendance');

// ---- Event push security ----
// Optional shared token. If set, the device-event endpoint requires
// ?token=... in the HTTP Listening URL you configure on the terminal.
define('EVENT_TOKEN', '');               // e.g. 'a1b2c3'  ('' = disabled)

// ---- Attendance / shift rules ----
define('SHIFT_START', '09:00');          // on-time threshold; later = "late"
define('SHIFT_END',   '17:00');
define('MIN_RESCAN_SECONDS', 60);        // ignore duplicate scans within this window
date_default_timezone_set('Asia/Dubai');  // +04:00, matches the terminal's clock

// ---- Paths ----
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/storage/photos');   // enrolled face photos
define('EVENT_IMG_DIR', BASE_PATH . '/storage/events'); // snapshots from scans
define('APP_BASE_URL', '/attendance');   // URL prefix where this app is served
