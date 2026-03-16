<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';

session_start();
requireLogin();

$user_id = $_SESSION['user_id'];
$code = $_POST['code'] ?? '';

if (empty($code)) {
    sendResponse(false, '验证码不能为空。');
    exit;
}

$conn = get_db_connection();

try {
    // 1. 验证验证码
    $stmt = $conn->prepare("SELECT verification_code, verification_code_expires_at FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || is_null($user['verification_code']) || $code != $user['verification_code']) {
        sendResponse(false, '验证码不正确。');
        exit;
    }
    if (new DateTime() > new DateTime($user['verification_code_expires_at'])) {
        sendResponse(false, '验证码已过期，请重新请求。');
        exit;
    }

    // 2. 开始数据库事务，准备删除所有相关数据
    $conn->begin_transaction();

    try {
        // a. 删除 solve 表中的记录 (无级联删除)
        $stmt_solve = $conn->prepare("DELETE FROM solve WHERE lost_user_id = ? OR found_user_id = ?");
        $stmt_solve->bind_param("ii", $user_id, $user_id);
        $stmt_solve->execute();
        $stmt_solve->close();

        // b. 删除 matched_listings 表中的记录 (无级联删除)
        $stmt_matched = $conn->prepare("DELETE FROM matched_listings WHERE user_id = ?");
        $stmt_matched->bind_param("i", $user_id);
        $stmt_matched->execute();
        $stmt_matched->close();

        // c. 删除用户本身 (这将触发其他表的级联删除)
        $stmt_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $stmt_user->close();

        // d. 提交事务
        $conn->commit();

        // 3. 销毁会话，强制用户退出
        session_destroy();

        sendResponse(true, '账户已成功注销。');

    } catch (Exception $e) {
        // 如果事务中任何一步失败，则回滚所有操作
        $conn->rollback();
        // 记录内部错误，但给用户一个通用提示
        error_log("注销账户事务失败: " . $e->getMessage());
        sendResponse(false, '注销过程中发生内部错误，请稍后重试。');
    }

} catch (Exception $e) {
    sendResponse(false, '处理请求时发生错误。', ['error' => $e->getMessage()]);
} finally {
    $conn->close();
} 