<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/bootstrap.php';
$cols=Database::q('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN);if(!in_array('recovery_email',$cols,true))Database::pdo()->exec('ALTER TABLE admins ADD recovery_email VARCHAR(254) NULL');
Database::pdo()->exec('CREATE TABLE IF NOT EXISTS password_resets(admin_id INT PRIMARY KEY,token_hash VARCHAR(255) NOT NULL,expires_at DATETIME NOT NULL,attempts INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
echo "Password recovery schema ready.\n";
