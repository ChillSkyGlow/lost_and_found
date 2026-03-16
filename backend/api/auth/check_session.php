<?php
// backend/api/auth/check_session.php

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
    // 如果存在，返回成功和用户信息
    sendResponse(true, '会话有效', [
        'isLoggedIn' => true,
        'user' => [
            'userId' => $_SESSION['user_id'],
            'username' => $_SESSION['username']
        ]
    ]);
} else {
    // 如果不存在，返回未登录状态
    sendResponse(false, '用户未登录', ['isLoggedIn' => false]);
}

?>
