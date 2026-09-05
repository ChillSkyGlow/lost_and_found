<?php
// backend/api/auth/login.php

// 包含数据库配置文件, 该文件现在提供 get_db_connection() 和 sendResponse() 等函数
require_once '../../config/database.php';
require_once '../../config/helpers.php';

// 在所有逻辑之前启动会话
// 这可以防止 "headers already sent" 错误
if (session_status() === PHP_SESSION_NONE) {
    // 为防止会话固定攻击，配置cookie
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// 只处理POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, '无效的请求方法');
}

// 从POST请求中获取用户名和密码
// 使用null合并运算符提供默认空字符串，防止未定义索引的警告
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// 验证输入
if (empty($username) || empty($password)) {
    sendResponse(false, '用户名和密码不能为空');
}

// 获取数据库连接
$conn = get_db_connection();

// 使用预处理语句来防止SQL注入
$stmt = $conn->prepare("SELECT user_id, username, password_hash, verification_code, is_verified FROM users WHERE username = ?");
if (!$stmt) {
    // 如果prepare失败，这是一个服务器端问题
    http_response_code(500);
    sendResponse(false, '服务器内部错误: 无法准备查询语句');
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // 验证密码
    if (password_verify($password, $user['password_hash'])) {

        // 校验邮箱是否已完成验证（正式需求：登录后才允许发布失物/招领）
        if ((int)$user['is_verified'] !== 1 || $user['verification_code'] !== null) {
            http_response_code(403);
            sendResponse(false, '邮箱尚未验证，请先完成邮箱验证再登录。');
        }

        // 登录成功
        session_regenerate_id(true);

        // 存储用户信息到会话
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];

        // 准备要返回给前端的用户数据
        $userData = [
            'id' => $user['user_id'],
            'username' => $user['username']
        ];
        
        // 发送成功响应
        sendResponse(true, '登录成功', $userData);

    } else {
        // 密码错误
        sendResponse(false, '用户名或密码错误');
    }
} else {
    // 用户名不存在
    sendResponse(false, '用户名或密码错误');
}

// 关闭语句和连接
$stmt->close();
$conn->close();

?>
