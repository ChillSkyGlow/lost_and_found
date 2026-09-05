<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, '无效的请求方法');
    exit;
}

if (isset($_SESSION['admin_logged_in']) || isset($_SESSION['admin_user_id'])) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_user_id']);
}

session_regenerate_id(true);

sendResponse(true, '管理员已成功登出。');
