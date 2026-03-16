<?php
// backend/api/users/update_profile.php
require_once '../../config/database.php';
require_once '../../config/helpers.php';

error_reporting(E_ERROR);
ini_set('display_errors', 0);
if (ob_get_level() > 0) ob_end_clean();

// 确保数据库连接变量已定义
$conn = get_db_connection();

// 只允许POST或PUT请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed');
}

// 验证用户是否登录
if (!isLoggedIn()) {
    http_response_code(401);
    sendResponse(false, '请先登录');
}

$user_id = getCurrentUserId();

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) : '';

if (empty($username) || empty($email)) {
    http_response_code(400);
    sendResponse(false, '用户名和邮箱不能为空');
}

if (!$email) {
    http_response_code(400);
    sendResponse(false, '邮箱格式不正确');
}

// 检查新用户名和邮箱是否已被其他用户占用
$stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
$stmt->bind_param('ssi', $username, $email, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    http_response_code(409);
    sendResponse(false, '用户名或邮箱已被其他用户占用');
}
$stmt->close();

// 更新用户信息
$update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
$update_stmt->bind_param('ssi', $username, $email, $user_id);

if ($update_stmt->execute()) {
    // 更新会话中的用户名
    $_SESSION['username'] = $username;
    sendResponse(true, '个人资料更新成功！', ['username' => $username, 'email' => $email]);
} else {
    http_response_code(500);
    sendResponse(false, '数据库更新失败');
}

$update_stmt->close();
$conn->close();
?>
