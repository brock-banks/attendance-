<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../includes/bootstrap.php';
$cols=Database::q('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN);
foreach (["role VARCHAR(16) NOT NULL DEFAULT 'admin'", "active TINYINT NOT NULL DEFAULT 1", "session_version INT NOT NULL DEFAULT 1"] as $def) if(!in_array(explode(' ',$def)[0],$cols)) Database::pdo()->exec('ALTER TABLE admins ADD '.$def);
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS login_attempts (id BIGINT AUTO_INCREMENT PRIMARY KEY, account_key CHAR(64) NOT NULL, ip_key CHAR(64) NOT NULL, attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(account_key,attempted_at), INDEX(ip_key,attempted_at)) ENGINE=InnoDB");
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS account_audit (id BIGINT AUTO_INCREMENT PRIMARY KEY, actor_id INT NOT NULL, target_id INT NOT NULL, action VARCHAR(64) NOT NULL, details TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
echo "Security schema ready.\n";
