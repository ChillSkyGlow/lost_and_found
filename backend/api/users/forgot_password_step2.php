<?php
// backend/api/users/forgot_password_step2.php
require_once '../../config/database.php';
require_once '../../config/helpers.php';

error_reporting(E_ERROR);
ini_set('display_errors', 0);
if (ob_get_level() > 0) ob_end_clean();
// 确保数据库连接变量已定义
$conn = get_db_connection();

// 启动会话以访问会话变量
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed');
}

// 验证第一步是否已完成
if (empty($_SESSION['password_reset_step1_completed']) || $_SESSION['password_reset_step1_completed'] !== true) {
    http_response_code(403);
    sendResponse(false, '请先完成第一步验证');
}

if (empty($_POST['answer']) || empty($_POST['new_password']) || empty($_POST['verification_code'])) {
    http_response_code(400);
    sendResponse(false, '安全答案、新密码和验证码均不能为空');
}

$user_id = $_SESSION['password_reset_user_id'];
$email = $_SESSION['password_reset_email'];
$security_answer = $_POST['answer'];
$new_password = $_POST['new_password'];
$verification_code = $_POST['verification_code'];

if (strlen($new_password) < 8) {
    http_response_code(400);
    sendResponse(false, '新密码必须至少8个字符');
}

// 1. 获取存储的安全答案哈希及验证码
$stmt = $conn->prepare("SELECT security_answer, verification_code, verification_code_expires_at FROM users WHERE user_id = ? AND email = ?");
$stmt->bind_param("is", $user_id, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // 2. 优先验证邮箱验证码
    if (is_null($user['verification_code']) || $verification_code != $user['verification_code']) {
        sendResponse(false, '验证码不正确');
        exit;
    }

    if (new DateTime() > new DateTime($user['verification_code_expires_at'])) {
        sendResponse(false, '验证码已过期，请返回上一步重新获取');
        exit;
    }

    // 3. 验证安全答案
    if (password_verify($security_answer, $user['security_answer'])) {
        // 答案正确，更新密码
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // 同时清空验证码
        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ?, verification_code = NULL, verification_code_expires_at = NULL WHERE user_id = ?");
        $update_stmt->bind_param("si", $new_password_hash, $user_id);
        
        if ($update_stmt->execute()) {
            // 清除会话中的重置信息
            unset($_SESSION['password_reset_user_id']);
            unset($_SESSION['password_reset_email']);
            unset($_SESSION['password_reset_step1_completed']);
            sendResponse(true, '密码已成功重置');
        } else {
            http_response_code(500);
            sendResponse(false, '重置密码失败');
        }
        $update_stmt->close();
    } else {
        // 答案错误
        http_response_code(403);
        sendResponse(false, '安全问题回答错误');
    }
} else {
    // 理论上不会发生，因为第一步已经验证过邮箱和ID
    http_response_code(500);
    sendResponse(false, '发生意外错误，请重试');
}

$stmt->close();
$conn->close();
?> 