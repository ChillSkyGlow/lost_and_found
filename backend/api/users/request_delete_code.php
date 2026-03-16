<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';
require_once '../../config/mailer.php';

session_start();
requireLogin();

$user_id = $_SESSION['user_id'];
$conn = get_db_connection();

try {
    // 1. 获取用户邮箱
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        sendResponse(false, '无法找到您的账户信息。');
        exit;
    }
    $user = $result->fetch_assoc();
    $email = $user['email'];
    $stmt->close();

    // 2. 生成并存储验证码
    $verification_code = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $update_stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_code_expires_at = ? WHERE user_id = ?");
    $update_stmt->bind_param("ssi", $verification_code, $expires_at, $user_id);
    $update_stmt->execute();
    $update_stmt->close();

    // 3. 发送邮件
    $subject = "账户注销验证";
    $body = "您正在申请注销您的账户。您的验证码是：<b>{$verification_code}</b><br>该验证码将在10分钟内有效。如果您没有进行此操作，请忽略本邮件。";
    send_notification_email($email, $subject, $body);

    sendResponse(true, '验证码已发送到您的注册邮箱，请注意查收。');

} catch (Exception $e) {
    sendResponse(false, '发送验证码失败，请稍后重试。', ['error' => $e->getMessage()]);
} finally {
    $conn->close();
} 