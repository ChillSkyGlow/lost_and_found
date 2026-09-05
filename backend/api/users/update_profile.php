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
$real_name = isset($_POST['real_name']) ? trim($_POST['real_name']) : '';
$student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

if (empty($username) || empty($email) || empty($real_name) || empty($student_id) || empty($phone)) {
    http_response_code(400);
    sendResponse(false, '用户名、邮箱、姓名、学号、联系电话不能为空');
}

if (!$email) {
    http_response_code(400);
    sendResponse(false, '邮箱格式不正确');
}

if (mb_strlen($real_name) < 2 || mb_strlen($real_name) > 50) {
    http_response_code(400);
    sendResponse(false, '姓名长度必须在2-50个字符之间');
}

if (!preg_match('/^[a-zA-Z0-9\-_]{4,50}$/', $student_id)) {
    http_response_code(400);
    sendResponse(false, '学号格式不正确');
}

if (!preg_match('/^1[3-9]\d{9}$/', $phone) && !preg_match('/^0\d{2,3}-?\d{7,8}$/', $phone)) {
    http_response_code(400);
    sendResponse(false, '联系电话格式不正确');
}

// 检查新用户名、邮箱、学号是否已被其他用户占用
$stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ? OR student_id = ?) AND user_id != ?");
$stmt->bind_param('sssi', $username, $email, $student_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    http_response_code(409);
    sendResponse(false, '用户名、邮箱或学号已被其他用户占用');
}
$stmt->close();

// 更新用户信息
$update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, real_name = ?, student_id = ?, phone = ? WHERE user_id = ?");
$update_stmt->bind_param('sssssi', $username, $email, $real_name, $student_id, $phone, $user_id);

if ($update_stmt->execute()) {
    // 更新会话中的用户名
    $_SESSION['username'] = $username;
    sendResponse(true, '个人资料更新成功！', ['username' => $username, 'email' => $email, 'real_name' => $real_name, 'student_id' => $student_id, 'phone' => $phone]);
} else {
    http_response_code(500);
    sendResponse(false, '数据库更新失败');
}

$update_stmt->close();
$conn->close();
?>
