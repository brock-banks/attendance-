<?php
/**
 * Phase C migration — notifications log + alert settings.
 * Run:  php tools/migrate_c.php
 */
require __DIR__ . '/../includes/bootstrap.php';
$pdo = Database::pdo();

echo "Creating notifications table...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(16) NOT NULL,            -- late | absent | summary
    emp_no VARCHAR(32) NOT NULL DEFAULT '',
    ref_date DATE NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_once (type, emp_no, ref_date)
) ENGINE=InnoDB");

echo "Seeding default alert settings...\n";
$defaults = [
    'alerts_enabled'       => '0',
    'alert_late'           => '1',
    'alert_absent'         => '1',
    'alert_summary'        => '1',
    'alert_recipient'      => '',
    'absent_after_minutes' => '60',
    'summary_time'         => '18:00',
    // SMTP (editable in Settings → Email alerts)
    'smtp_host'            => '',
    'smtp_port'            => '587',
    'smtp_secure'          => 'tls',
    'smtp_user'            => '',
    'smtp_pass'            => '',
    'smtp_from'            => '',
    'smtp_from_name'       => 'Attendance',
];
foreach ($defaults as $k => $v) {
    $pdo->prepare("INSERT IGNORE INTO settings (k, v) VALUES (?, ?)")->execute([$k, $v]);
}

echo "Done.\n";
