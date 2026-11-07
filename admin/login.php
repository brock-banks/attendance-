<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::start();
if (Auth::check()) { header('Location: ' . APP_BASE_URL . '/admin/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (Auth::attempt($u, $p)) {
        header('Location: ' . APP_BASE_URL . '/admin/index.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · Attendance</title>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/app.css">
</head><body class="login-body">
<form class="login-card" method="post">
  <h1><span class="logo">A</span> Attendance</h1>
  <p class="sub">Admin sign in</p>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <label>Username <input name="username" autofocus required></label>
  <label>Password <input type="password" name="password" required></label>
  <button type="submit" class="btn">Sign in</button>
</form>
</body></html>
