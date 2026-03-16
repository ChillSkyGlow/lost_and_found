<?php
// backend/api/auth/logout.php
require_once '../../config/database.php';
require_once '../../config/helpers.php';

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed');
}

try {
    // 使用辅助函数检查是否已登录
    if (!isLoggedIn()) {
        sendResponse(false, '用户未登录');
        return; // 提前退出
    }

    // 清除所有会话数据
    $_SESSION = array();

    // 删除会话cookie
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // 销毁会话
    session_destroy();

    sendResponse(true, '注销成功');

} catch (Exception $e) {
    error_log("Logout Error: " . $e->getMessage());
    http_response_code(500);
    sendResponse(false, '注销过程中发生错误');
}
?>
