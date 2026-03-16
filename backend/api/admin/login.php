<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, '无效的请求方法');
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    sendResponse(false, '用户名和密码不能为空。');
    exit;
}

$conn = get_db_connection();

try {
    // 查询用户，并核对角色
    $stmt = $conn->prepare("SELECT user_id, password_hash, role FROM users WHERE username = ?");
    if (!$stmt) {
        throw new Exception('数据库查询准备失败: ' . $conn->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, '用户名或密码不正确。');
        exit;
    }

    $user = $result->fetch_assoc();

    // 验证密码
    if (!password_verify($password, $user['password_hash'])) {
        sendResponse(false, '用户名或密码不正确。');
        exit;
    }

    // **核心安全检查：验证角色**
    if ($user['role'] !== 'admin') {
        sendResponse(false, '权限不足，只有管理员才能登录。');
        exit;
    }

    // 登录成功，设置管理员会话
    session_start();
    session_regenerate_id(true); // 防止会话固定攻击
    $_SESSION['admin_user_id'] = $user['user_id'];
    $_SESSION['admin_logged_in'] = true;

    sendResponse(true, '登录成功！');

} catch (Exception $e) {
    sendResponse(false, '服务器错误: ' . $e->getMessage());
} finally {
    if (isset($stmt) && $stmt) $stmt->close();
    $conn->close();
} 