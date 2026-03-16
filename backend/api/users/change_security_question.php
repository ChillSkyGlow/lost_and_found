<?php
// backend/api/users/change_security_question.php
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

if (empty($_POST['current_password']) || empty($_POST['new_question']) || empty($_POST['new_answer'])) {
    http_response_code(400);
    sendResponse(false, '当前密码、新安全问题和新答案均不能为空');
}

$current_password = $_POST['current_password'];
$new_question = trim($_POST['new_question']);
$new_answer = trim($_POST['new_answer']);

// 1. 验证密码
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($current_password, $user['password_hash'])) {
    http_response_code(403);
    sendResponse(false, '当前密码不正确，无法修改安全问题');
}

// 2. 将新答案哈希化并更新
$new_answer_hash = password_hash($new_answer, PASSWORD_DEFAULT);
$update_stmt = $conn->prepare("UPDATE users SET security_question = ?, security_answer = ? WHERE user_id = ?");
$update_stmt->bind_param('ssi', $new_question, $new_answer_hash, $user_id);

if ($update_stmt->execute()) {
    sendResponse(true, '安全问题修改成功！');
} else {
    http_response_code(500);
    sendResponse(false, '安全问题修改失败');
}

$update_stmt->close();
$conn->close();
?>
