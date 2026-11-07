<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::logout();
header('Location: ' . APP_BASE_URL . '/admin/login.php');
