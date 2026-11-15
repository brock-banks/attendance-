<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../includes/bootstrap.php';
$pdo = Database::pdo();
$columns = $pdo->query('SHOW COLUMNS FROM events')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('event_key', $columns)) $pdo->exec('ALTER TABLE events ADD event_key CHAR(64) NULL, ADD UNIQUE KEY uq_event_key(event_key)');
if (!in_array('eligible', $columns)) $pdo->exec('ALTER TABLE events ADD eligible TINYINT(1) NOT NULL DEFAULT 0, ADD INDEX ix_attendance_scan(emp_no, eligible, event_time)');
$pdo->exec("CREATE TABLE IF NOT EXISTS attendance_corrections (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, emp_no VARCHAR(32) NOT NULL, work_date DATE NOT NULL,
 check_in DATETIME NOT NULL, check_out DATETIME NULL, reason VARCHAR(1000) NOT NULL,
 admin_id INT NOT NULL, admin_user VARCHAR(64) NOT NULL, previous_json TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(emp_no, work_date)
) ENGINE=InnoDB");
// Classify legacy events without changing published attendance totals.
$pdo->exec('UPDATE events SET eligible=1 WHERE major_event=5 AND sub_event=75');
echo "Reliability migration complete. Existing attendance totals preserved.\n";
