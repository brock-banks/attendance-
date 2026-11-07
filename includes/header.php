<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();
$active = $active ?? '';

// Fluent-style inline SVG icons (stroke = currentColor).
function nav_icon(string $k): string {
    $s = '<svg class="ic" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
    $e = '</svg>';
    switch ($k) {
        case 'index':     return $s . '<rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="11" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="11" width="6" height="6" rx="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5"/>' . $e;
        case 'employees': return $s . '<circle cx="8" cy="6.5" r="2.6"/><path d="M3.5 16c0-2.8 2-4.5 4.5-4.5s4.5 1.7 4.5 4.5"/><circle cx="14.6" cy="7" r="2"/><path d="M13.6 11.7c1.9.3 3.3 1.8 3.3 4.3"/>' . $e;
        case 'enroll':    return $s . '<circle cx="8" cy="6.5" r="2.6"/><path d="M3.5 16c0-2.8 2-4.5 4.5-4.5c.9 0 1.7.2 2.4.6"/><path d="M14.5 11.5v4M12.5 13.5h4"/>' . $e;
        case 'reports':   return $s . '<path d="M3 16.5h14"/><rect x="4.5" y="9" width="2.8" height="5.5" rx="1"/><rect x="8.6" y="5" width="2.8" height="9.5" rx="1"/><rect x="12.7" y="11" width="2.8" height="3.5" rx="1"/>' . $e;
        case 'device':    return $s . '<rect x="5.5" y="2.5" width="9" height="15" rx="2"/><rect x="7.5" y="5" width="5" height="4.5" rx="1"/><circle cx="10" cy="14" r="1"/>' . $e;
        case 'settings':  return $s . '<path d="M3.5 7h7M14 7h2.5"/><circle cx="12.3" cy="7" r="1.7"/><path d="M3.5 13h2.5M9.5 13h7"/><circle cx="7.7" cy="13" r="1.7"/>' . $e;
        case 'leave':     return $s . '<rect x="3.5" y="4.5" width="13" height="12" rx="2"/><path d="M3.5 8h13M7 2.8v3M13 2.8v3"/>' . $e;
    }
    return $s . $e;
}

$nav = [
    'index'     => 'Dashboard',
    'employees' => 'Employees',
    'enroll'    => 'Enroll',
    'reports'   => 'Reports',
    'leave'     => 'Leave',
    'settings'  => 'Settings',
    'device'    => 'Device',
];
$urls = [
    'index' => '/admin/index.php', 'employees' => '/admin/employees.php',
    'enroll' => '/admin/enroll.php', 'reports' => '/admin/reports.php',
    'leave' => '/admin/leave.php',
    'settings' => '/admin/settings.php', 'device' => '/admin/device-setup.php',
];
$initial = strtoupper(substr((string)Auth::user(), 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance · <?= e($pageTitle ?? 'Admin') ?></title>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/app.css">
</head>
<body>
<div class="app">
  <header class="appbar">
    <div class="brand"><span class="logo">A</span> Attendance</div>
    <div class="spacer"></div>
    <div class="who">
      <span class="avatar"><?= e($initial ?: 'A') ?></span>
      <span><?= e(Auth::user()) ?></span>
      <a href="<?= APP_BASE_URL ?>/admin/logout.php">Sign out</a>
    </div>
  </header>
  <div class="shell">
    <nav class="nav">
      <?php foreach ($nav as $k => $label): ?>
        <a href="<?= APP_BASE_URL . $urls[$k] ?>" class="<?= $active === $k ? 'on' : '' ?>">
          <?= nav_icon($k) ?><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <main class="content">
