<?php
// backend/api/users/change_password.php
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

if (empty($_POST['current_password']) || empty($_POST['new_password'])) {
    http_response_code(400);
    sendResponse(false, '当前密码和新密码不能为空');
}

$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];

if (strlen($new_password) < 8) {
    http_response_code(400);
    sendResponse(false, '新密码必须至少8个字符');
}

// 1. 获取当前用户的密码哈希
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    sendResponse(false, '用户不存在');
}

// 2. 验证当前密码是否正确
if (!password_verify($current_password, $user['password_hash'])) {
    http_response_code(403);
    sendResponse(false, '当前密码不正确');
}

// 3. 将新密码哈希化并更新
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
$update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
$update_stmt->bind_param('si', $new_password_hash, $user_id);

if ($update_stmt->execute()) {
    sendResponse(true, '密码修改成功！');
} else {
    http_response_code(500);
    sendResponse(false, '密码修改失败');
}

$update_stmt->close();
$conn->close();
?>
