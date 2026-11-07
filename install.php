<?php
/**
 * One-time installer. Open http://localhost/attendance/install.php
 * Creates the database + tables and an admin account, then DELETE this file.
 */
require_once __DIR__ . '/config/config.php';

$done = []; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser = trim($_POST['username'] ?? 'admin');
    $adminPass = $_POST['password'] ?? '';
    if (strlen($adminPass) < 6) {
        $err = 'Password must be at least 6 characters.';
    } else {
        try {
            // Connect without DB first so we can create it.
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT),
                DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
            // Run statements one by one.
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt !== '') { $pdo->exec($stmt); }
            }
            $done[] = 'Database and tables created.';

            $pdo->exec('USE `' . DB_NAME . '`');
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $st = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?,?)
                                 ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)');
            $st->execute([$adminUser, $hash]);
            $done[] = "Admin account '$adminUser' is ready.";

            foreach ([UPLOAD_DIR, EVENT_IMG_DIR] as $d) {
                if (!is_dir($d)) { @mkdir($d, 0775, true); }
            }
            $done[] = 'Storage folders ready.';
        } catch (Throwable $ex) {
            $err = $ex->getMessage();
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8">
<title>Install · Attendance</title>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/app.css"></head>
<body class="login-body">
<div class="login-card" style="width:420px">
  <h1><span class="logo">A</span> Install</h1>
  <?php if ($done): ?>
    <?php foreach ($done as $d): ?><div class="alert ok"><?= htmlspecialchars($d) ?></div><?php endforeach; ?>
    <div class="alert err">Now DELETE install.php, then <a href="<?= APP_BASE_URL ?>/admin/login.php">sign in</a>.</div>
  <?php else: ?>
    <p class="sub">Create tables and your admin login.</p>
    <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post" style="display:flex;flex-direction:column;gap:14px">
      <label>Admin username <input name="username" value="admin" required></label>
      <label>Admin password <input type="password" name="password" required minlength="6"></label>
      <button class="btn" type="submit">Run installer</button>
    </form>
  <?php endif; ?>
</div>
</body></html>
