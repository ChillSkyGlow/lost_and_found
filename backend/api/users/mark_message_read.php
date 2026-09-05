<?php
// 开启错误显示
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, '未登录', []);
    exit;
}

$user_id = $_SESSION['user_id'];

// 检查必要参数
if (!isset($_POST['message_id']) || !isset($_POST['message_type'])) {
    sendResponse(false, '缺少必要参数', []);
    exit;
}

$message_id = $_POST['message_id'];
$message_type = $_POST['message_type'];

$conn = get_db_connection();

try {
    if ($message_type === 'match' || $message_type === 'claim' || $message_type === 'claim_approved' || $message_type === 'claim_rejected') {
        // 标记匹配消息/认领申请/审核结果消息为已读（均存储在 matched_notifications 表中，通过 type 列区分）
        $sql = "UPDATE matched_notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("准备更新matched_notifications查询失败: " . $conn->error);
        }
        $stmt->bind_param('ii', $message_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } else if ($message_type === 'comment') {
        if (!isset($_POST['listing_type'])) {
            sendResponse(false, '缺少 listing_type 参数', []);
            exit;
        }
        $listing_type = $_POST['listing_type'];

        $table_name = '';
        if ($listing_type === 'lost') {
            $table_name = 'lost_comment';
        } else if ($listing_type === 'found') {
            $table_name = 'found_comment';
        } else {
            sendResponse(false, '无效的 listing_type', []);
            exit;
        }

        $sql = "UPDATE $table_name SET is_read = 1 WHERE comment_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("准备更新 $table_name 查询失败: " . $conn->error);
        }
        $stmt->bind_param('i', $message_id);
        $stmt->execute();
        $stmt->close();
    }

    sendResponse(true, '标记已读成功', []);
} catch (Exception $e) {
    sendResponse(false, '标记已读失败: ' . $e->getMessage(), []);
} finally {
    $conn->close();
} 