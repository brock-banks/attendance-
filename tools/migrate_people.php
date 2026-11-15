<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/bootstrap.php';
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS employee_profiles(emp_no VARCHAR(32) PRIMARY KEY,job_title VARCHAR(128) NULL,phone VARCHAR(64) NULL,email VARCHAR(254) NULL,joining_date DATE NULL,emergency_name VARCHAR(128) NULL,emergency_phone VARCHAR(64) NULL,archived_at DATETIME NULL) ENGINE=InnoDB");
echo "Optional employee profiles ready. Existing attendance unchanged.\n";
