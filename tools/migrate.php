<?php
/**
 * Idempotent migration for Phase A (shifts + accurate hours).
 * Run:  php tools/migrate.php
 */
require __DIR__ . '/../includes/bootstrap.php';

$pdo = Database::pdo();

function hasColumn(PDO $pdo, string $table, string $col): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns
                        WHERE table_schema=? AND table_name=? AND column_name=?");
    $s->execute([DB_NAME, $table, $col]);
    return (int)$s->fetchColumn() > 0;
}

echo "Creating shifts table...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    grace_minutes INT NOT NULL DEFAULT 10,
    break_minutes INT NOT NULL DEFAULT 60,
    half_day_minutes INT NOT NULL DEFAULT 240,
    workdays VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

echo "Creating settings table...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    k VARCHAR(64) PRIMARY KEY,
    v VARCHAR(255)
) ENGINE=InnoDB");

echo "Adding employees.shift_id...\n";
if (!hasColumn($pdo, 'employees', 'shift_id')) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN shift_id INT NULL AFTER department");
}

echo "Adding attendance metric columns...\n";
foreach ([
    'late_minutes'     => "ALTER TABLE attendance ADD COLUMN late_minutes INT NOT NULL DEFAULT 0 AFTER work_minutes",
    'early_minutes'    => "ALTER TABLE attendance ADD COLUMN early_minutes INT NOT NULL DEFAULT 0 AFTER late_minutes",
    'overtime_minutes' => "ALTER TABLE attendance ADD COLUMN overtime_minutes INT NOT NULL DEFAULT 0 AFTER early_minutes",
    'shift_id'         => "ALTER TABLE attendance ADD COLUMN shift_id INT NULL AFTER overtime_minutes",
] as $col => $sql) {
    if (!hasColumn($pdo, 'attendance', $col)) { $pdo->exec($sql); }
}

echo "Seeding default shift (if none)...\n";
$count = (int)$pdo->query("SELECT COUNT(*) FROM shifts")->fetchColumn();
if ($count === 0) {
    $pdo->exec("INSERT INTO shifts (name,start_time,end_time,grace_minutes,break_minutes,half_day_minutes,workdays,is_default)
                VALUES ('General','09:00:00','17:00:00',10,60,240,'1,2,3,4,5',1)");
}

echo "Done.\n";
