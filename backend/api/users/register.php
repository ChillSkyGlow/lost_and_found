<?php
// backend/api/users/register.php
require_once '../../config/database.php';
require_once '../../config/helpers.php';
require_once '../../config/mailer.php';

error_reporting(E_ERROR);
ini_set('display_errors', 0);
if (ob_get_level() > 0) ob_end_clean();

// 确保数据库连接变量已定义
$conn = get_db_connection();

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed');
}

if (
    empty($_POST['username']) ||
    empty($_POST['real_name']) ||
    empty($_POST['student_id']) ||
    empty($_POST['phone']) ||
    empty($_POST['password']) ||
    empty($_POST['email']) ||
    empty($_POST['security_question']) ||
    empty($_POST['security_answer'])
) {
    http_response_code(400);
    sendResponse(false, '所有字段都是必填的（含姓名、学号、联系电话）');
}

$username = trim($_POST['username']);
$real_name = trim($_POST['real_name']);
$student_id = trim($_POST['student_id']);
$phone = trim($_POST['phone']);
$password = $_POST['password'];
$email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
$security_question = trim($_POST['security_question']);
$security_answer = trim($_POST['security_answer']);

// 验证输入数据
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    http_response_code(400);
    sendResponse(false, '用户名只能包含字母、数字和下划线，长度在3-50个字符之间');
}

if (mb_strlen($real_name) < 2 || mb_strlen($real_name) > 50) {
    http_response_code(400);
    sendResponse(false, '姓名长度必须在2-50个字符之间');
}

if (!preg_match('/^[a-zA-Z0-9\-_]{4,50}$/', $student_id)) {
    http_response_code(400);
    sendResponse(false, '学号格式不正确（长度4-50，支持字母、数字、-、_）');
}

if (!preg_match('/^1[3-9]\d{9}$/', $phone) && !preg_match('/^0\d{2,3}-?\d{7,8}$/', $phone)) {
    http_response_code(400);
    sendResponse(false, '联系电话格式不正确（中国大陆手机号或固定电话）');
}

if (!$email) {
    http_response_code(400);
    sendResponse(false, '邮箱格式不正确');
}

if (strlen($password) < 8) {
    http_response_code(400);
    sendResponse(false, '密码必须至少8个字符');
}

if (strlen($security_question) < 3 || strlen($security_question) > 255) {
    http_response_code(400);
    sendResponse(false, '安全问题长度必须在3-255个字符之间');
}

if (strlen($security_answer) < 2 || strlen($security_answer) > 255) {
    http_response_code(400);
    sendResponse(false, '安全答案长度必须在2-255个字符之间');
}

// 检查用户名、邮箱或学号是否已存在
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? OR student_id = ?");
$stmt->bind_param("sss", $username, $email, $student_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    http_response_code(409);
    sendResponse(false, '用户名、邮箱或学号已被注册');
    $stmt->close();
    exit();
}
$stmt->close();

// 对密码和安全答案进行哈希处理
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$security_answer_hash = password_hash($security_answer, PASSWORD_DEFAULT);

// 生成邮箱验证码
$verification_code = rand(100000, 999999);
$verification_code_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// 准备插入新用户
$sql = "INSERT INTO users (username, real_name, student_id, phone, password_hash, email, security_question, security_answer, verification_code, verification_code_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendResponse(false, '数据库查询准备失败: ' . $conn->error);
}
$stmt->bind_param("ssssssssis", $username, $real_name, $student_id, $phone, $password_hash, $email, $security_question, $security_answer_hash, $verification_code, $verification_code_expires_at);

if ($stmt->execute()) {
    // 发送验证邮件
    try {
        $subject = "欢迎注册！请验证您的邮箱地址";
        $body = "您的邮箱验证码是：<b>{$verification_code}</b><br>该验证码将在10分钟内有效。";
        send_notification_email($email, $subject, $body);
        sendResponse(true, '注册成功！验证邮件已发送，请查收并验证后登录。');
    } catch (Exception $e) {
        // 即使邮件发送失败，用户也已创建，可以后续请求重发验证码
        error_log("注册后邮件发送失败: " . $e->getMessage());
        sendResponse(true, '注册成功！但验证邮件发送失败，请稍后尝试登录或重发验证码。');
    }
} else {
    http_response_code(500);
    sendResponse(false, '注册失败，请重试: ' . $stmt->error);
}

$stmt->close();
$conn->close();
?>
