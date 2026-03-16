<?php
// backend/api/users/forgot_password_step1.php
require_once '../../config/database.php';
require_once '../../config/helpers.php';
require_once '../../config/mailer.php'; // 引入邮件发送模块

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

if (empty($_POST['email'])) {
    http_response_code(400);
    sendResponse(false, '需要提供邮箱地址');
}

$email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    sendResponse(false, '邮箱格式不正确');
}

$stmt = $conn->prepare("SELECT user_id, security_question FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // 生成并存储验证码
    $verification_code = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $update_stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_code_expires_at = ? WHERE user_id = ?");
    $update_stmt->bind_param("ssi", $verification_code, $expires_at, $user['user_id']);
    $update_stmt->execute();
    $update_stmt->close();

    // 发送邮件
    try {
        $subject = "密码重置请求";
        $body = "您的密码重置验证码是：<b>{$verification_code}</b><br>该验证码将在10分钟内有效。";
        send_notification_email($email, $subject, $body);
    } catch (Exception $e) {
        // 即使邮件发送失败，也不应阻止流程，因为用户可以请求重发
        error_log("密码重置邮件发送失败: " . $e->getMessage());
    }

    // 启动会话
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // 将用户ID临时存入会话，以便在第二步验证
    $_SESSION['password_reset_user_id'] = $user['user_id'];
    $_SESSION['password_reset_email'] = $email;
    $_SESSION['password_reset_step1_completed'] = true;

    sendResponse(true, '成功获取安全问题', ['question' => $user['security_question']]);
} else {
    http_response_code(404);
    sendResponse(false, '未找到使用该邮箱地址的账户');
}

$stmt->close();
$conn->close();
?>
