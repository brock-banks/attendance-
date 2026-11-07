<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Location: ' . APP_BASE_URL . '/admin/' . (Auth::check() ? 'index.php' : 'login.php'));
