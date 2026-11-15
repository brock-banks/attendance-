<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../includes/bootstrap.php';
$pdo=Database::pdo();
$cols=$pdo->query('SHOW COLUMNS FROM attendance_corrections')->fetchAll(PDO::FETCH_COLUMN);
foreach (["approval_status VARCHAR(16) NOT NULL DEFAULT 'approved'", "reviewed_by VARCHAR(64) NULL", "reviewed_at DATETIME(6) NULL", "review_note VARCHAR(1000) NULL"] as $definition) {
 $name=explode(' ', $definition)[0];
 if (!in_array($name,$cols)) $pdo->exec('ALTER TABLE attendance_corrections ADD '.$definition);
}
$pdo->exec("UPDATE attendance_corrections SET reviewed_by=admin_user,reviewed_at=created_at WHERE approval_status='approved' AND reviewed_at IS NULL");
echo "Approval migration complete. Legacy approvals retained.\n";
