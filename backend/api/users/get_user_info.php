<?php
// backend/api/users/get_user_info.php
require_once '../../config/database.php';
require_once '../../config/helpers.php';

error_reporting(E_ERROR);
ini_set('display_errors', 0);

function safeSendResponse($success, $message = '', $data = []) {
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    safeSendResponse(false, 'Method not allowed');
}

// 验证用户是否登录
if (!isLoggedIn()) {
    http_response_code(401);
    safeSendResponse(false, '请先登录');
}

$user_id = getCurrentUserId();

$conn = get_db_connection();
$stmt = $conn->prepare("SELECT username, real_name, student_id, phone, email, security_question, is_verified, created_at FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_info = $result->fetch_assoc();
$stmt->close();
$conn->close();

if ($user_info) {
    safeSendResponse(true, '成功获取用户信息', $user_info);
} else {
    http_response_code(404);
    safeSendResponse(false, '未找到用户信息');
}
?>
