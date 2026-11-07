<?php
/**
 * Phase B migration — holidays + leave.
 * Run:  php tools/migrate_b.php
 */
require __DIR__ . '/../includes/bootstrap.php';
$pdo = Database::pdo();

echo "Creating holidays table...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    recurring TINYINT(1) NOT NULL DEFAULT 0,  -- repeats yearly (match month-day)
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

echo "Creating leaves table...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_no VARCHAR(32) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    type VARCHAR(24) NOT NULL DEFAULT 'paid',  -- paid | sick | unpaid | other
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (emp_no),
    INDEX (start_date),
    INDEX (end_date)
) ENGINE=InnoDB");

echo "Done.\n";
