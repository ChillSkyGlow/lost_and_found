<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';
require_once '../../config/mailer.php'; // 引入邮件发送模块
session_start();

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Method Not Allowed');
}

// 1. 验证用户是否登录
requireLogin();
$user_id = $_SESSION['user_id']; 
$username = $_SESSION['username'];

// 2. 获取并验证输入参数
$type = $_POST['type'] ?? '';
$listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if (!in_array($type, ['lost', 'found']) || $listing_id <= 0 || empty($content)) {
    http_response_code(400);
    sendResponse(false, '所有字段都是必需的(type, listing_id, content)');
}

$conn = get_db_connection();

$listing_type_map = [
    'lost' => ['table' => 'lost_listings', 'id_col' => 'lost_listing_id', 'comment_table' => 'lost_comment'],
    'found' => ['table' => 'found_listings', 'id_col' => 'found_listing_id', 'comment_table' => 'found_comment']
];

$table_info = $listing_type_map[$type];
$listing_table = $table_info['table'];
$listing_id_col = $table_info['id_col'];
$comment_table = $table_info['comment_table'];

$conn->begin_transaction();

try {
    // 1. 获取物品主人的 user_id 和 item_name
    $sql_get_owner = "SELECT user_id, item_name FROM $listing_table WHERE $listing_id_col = ?";
    $stmt_get_owner = $conn->prepare($sql_get_owner);
    $stmt_get_owner->bind_param("i", $listing_id);
    $stmt_get_owner->execute();
    $result_owner = $stmt_get_owner->get_result();
    if ($result_owner->num_rows === 0) {
        throw new Exception("物品不存在");
    }
    $listing_data = $result_owner->fetch_assoc();
    $owner_id = $listing_data['user_id'];
    $item_name = $listing_data['item_name'];
    $stmt_get_owner->close();

    // 检查是否是给自己评论
    if ($owner_id == $user_id) {
        // 如果是给自己评论，则不发送通知，但评论仍然需要发布
    }

    // 2. 插入评论
    $sql_insert_comment = "INSERT INTO $comment_table (user_id, {$listing_id_col}, content) VALUES (?, ?, ?)";
    $stmt_insert_comment = $conn->prepare($sql_insert_comment);
    $stmt_insert_comment->bind_param("iis", $user_id, $listing_id, $content);
    $stmt_insert_comment->execute();
    $stmt_insert_comment->close();

    // 评论插入成功，提交数据库事务
    $conn->commit();

    // 3. 发送邮件通知 (在事务提交后进行，使其不影响评论发布)
    if ($owner_id != $user_id) {
        try {
            // 获取物品主人的邮箱
            $sql_get_email = "SELECT email FROM users WHERE user_id = ?";
            $stmt_get_email = $conn->prepare($sql_get_email);
            $stmt_get_email->bind_param("i", $owner_id);
            $stmt_get_email->execute();
            $result_email = $stmt_get_email->get_result();
            if ($user_info = $result_email->fetch_assoc()) {
                $owner_email = $user_info['email'];
                $subject = "您的物品有新的评论";
                $body = "您发布的物品 '{$item_name}' 有一条新评论。<br>评论内容: {$content}<br><br>请及时登录平台查看详情。";
                send_notification_email($owner_email, $subject, $body);
            }
            $stmt_get_email->close();
        } catch (Exception $e) {
            // 如果邮件发送失败，只记录错误，不影响用户看到的成功结果
            error_log("评论成功后邮件发送失败: " . $e->getMessage());
        }
    }

    sendResponse(true, '评论发布成功');

} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, '评论发布失败: ' . $e->getMessage());
}

$conn->close();
?>
