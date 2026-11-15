<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
Auth::logout();
header('Location: ' . APP_BASE_URL . '/admin/login.php');
