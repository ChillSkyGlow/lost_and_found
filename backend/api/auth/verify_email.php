<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, '无效的请求方法');
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// 验证输入
if (!isset($data['email']) || !isset($data['code'])) {
    sendResponse(false, '缺少邮箱或验证码');
    exit;
}

$email = $data['email'];
$code = $data['code'];

$conn = get_db_connection();

try {
    // 查找用户
    $stmt = $conn->prepare("SELECT user_id, verification_code, verification_code_expires_at FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, '用户不存在');
        exit;
    }

    $user = $result->fetch_assoc();

    // 检查是否已验证
    if (is_null($user['verification_code'])) {
        sendResponse(true, '此邮箱已验证，可直接登录');
        exit;
    }
    
    // 检查验证码是否过期
    if (new DateTime() > new DateTime($user['verification_code_expires_at'])) {
        // TODO: 在这里可以加入"重发验证码"的逻辑
        sendResponse(false, '验证码已过期，请重新请求');
        exit;
    }

    // 检查验证码是否匹配
    if ($code != $user['verification_code']) {
        sendResponse(false, '验证码不正确');
        exit;
    }

    // 验证成功，清空验证码信息
    $stmt_update = $conn->prepare("UPDATE users SET verification_code = NULL, verification_code_expires_at = NULL WHERE user_id = ?");
    $stmt_update->bind_param("i", $user['user_id']);
    $stmt_update->execute();

    sendResponse(true, '邮箱验证成功！您现在可以登录了。');

} catch (Exception $e) {
    sendResponse(false, '服务器错误: ' . $e->getMessage());
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($stmt_update)) $stmt_update->close();
    $conn->close();
} 