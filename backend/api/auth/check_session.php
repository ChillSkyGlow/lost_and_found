<?php
// backend/api/auth/check_session.php

require_once '../../config/database.php';
require_once '../../config/helpers.php';

// 确保会话已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed');
}

// 检查会话中是否存在 user_id
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    $user_data = [
        'userId' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => 'user'
    ];
    $conn = get_db_connection();
    $role_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
    if ($role_stmt) {
        $uid = (int)$_SESSION['user_id'];
        $role_stmt->bind_param('i', $uid);
        $role_stmt->execute();
        $role_stmt->bind_result($role);
        if ($role_stmt->fetch()) {
            $user_data['role'] = $role ? $role : 'user';
        }
        $role_stmt->close();
    }
    $conn->close();
    sendResponse(true, '会话有效', [
        'isLoggedIn' => true,
        'user' => $user_data
    ]);
} else {
    // 如果不存在，返回未登录状态
    sendResponse(false, '用户未登录', ['isLoggedIn' => false]);
}

?>
