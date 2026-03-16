<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';
require_once '../../config/mailer.php';

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, '无效的请求方法');
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email'])) {
    sendResponse(false, '缺少邮箱地址');
    exit;
}

$email = $data['email'];
$conn = get_db_connection();

try {
    // 1. 查找用户
    $stmt = $conn->prepare("SELECT user_id, verification_code FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, '该邮箱未注册');
        exit;
    }
    $user = $result->fetch_assoc();

    // 2. 检查用户是否已验证
    if (is_null($user['verification_code'])) {
        sendResponse(false, '该账户已验证，无需重发验证码。');
        exit;
    }

    // 3. 生成新的验证码和过期时间
    $new_code = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // 4. 更新数据库
    $update_stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_code_expires_at = ? WHERE user_id = ?");
    $update_stmt->bind_param("ssi", $new_code, $expires_at, $user['user_id']);
    $update_stmt->execute();

    // 5. 发送新邮件
    $subject = "您的新邮箱验证码";
    $body = "您的新邮箱验证码是：<b>{$new_code}</b><br>该验证码将在10分钟内有效。";
    send_notification_email($email, $subject, $body);

    sendResponse(true, '新的验证码已发送，请查收。');

} catch (Exception $e) {
    sendResponse(false, '发送失败: ' . $e->getMessage());
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($update_stmt)) $update_stmt->close();
    $conn->close();
} 