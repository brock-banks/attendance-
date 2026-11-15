<?php
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/../includes/bootstrap.php';
$columns=Database::q('SHOW COLUMNS FROM attendance')->fetchAll(PDO::FETCH_COLUMN);
foreach(['sessions_json LONGTEXT NULL','review_required TINYINT NOT NULL DEFAULT 0'] as $def) if(!in_array(explode(' ',$def)[0],$columns)) Database::pdo()->exec('ALTER TABLE attendance ADD '.$def);
echo "Session columns ready.\n";
