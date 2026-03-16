<?php
// backend/api/listings/get_listing_details.php

// 仅报告严重错误，以避免警告信息污染JSON输出
error_reporting(E_ERROR | E_PARSE);

require_once '../../config/helpers.php';
require_once '../../config/database.php';

// 允许跨域请求
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    sendResponse(false, null, 'Method Not Allowed');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

if ($id <= 0 || ($type !== 'lost' && $type !== 'found')) {
    http_response_code(400);
    sendResponse(false, null, '无效的参数：必须提供有效的ID和类型(lost/found)');
}

$conn = get_db_connection();

// --- 根据类型确定表名和字段名 ---
$listing_table = $type === 'lost' ? 'lost_listings' : 'found_listings';
$listing_id_field = $type === 'lost' ? 'lost_listing_id' : 'found_listing_id';
$comment_table = $type === 'lost' ? 'lost_comment' : 'found_comment';
$comment_listing_id_field = $type === 'lost' ? 'lost_listing_id' : 'found_listing_id';

// --- 1. 获取物品详情 ---
$sql_details = "SELECT l.*, u.username 
                FROM {$listing_table} l 
                JOIN users u ON l.user_id = u.user_id 
                WHERE l.{$listing_id_field} = ?";

$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param('i', $id);
$stmt_details->execute();
$result_details = $stmt_details->get_result();
$details = $result_details->fetch_assoc();
$stmt_details->close();

if (!$details) {
    http_response_code(404);
    sendResponse(false, null, '未找到指定的物品信息');
}

// 为了前端统一处理，将主键名统一为 'id'
$details['id'] = $details[$listing_id_field];
$details['listing_type'] = $type;

// 统一字段名
$details['image_path'] = $details['image_file_path'];
unset($details['image_file_path']);
$details['title'] = $details['item_name'];
unset($details['item_name']);
$details['location_coords'] = $details['location_coordinates'];
unset($details['location_coordinates']);

// --- 2. 获取相关评论 ---
$comments = [];
$sql_comments = "SELECT c.*, u.username 
                 FROM {$comment_table} c 
                 JOIN users u ON c.user_id = u.user_id 
                 WHERE c.{$comment_listing_id_field} = ? 
                 ORDER BY c.created_at ASC";

$stmt_comments = $conn->prepare($sql_comments);
$stmt_comments->bind_param('i', $id);
$stmt_comments->execute();
$comment_result = $stmt_comments->get_result();
while ($row = $comment_result->fetch_assoc()) {
    $comments[] = $row;
}
$stmt_comments->close();

// --- 3. 返回合并后的数据 ---
// 修正了参数顺序：第二个参数是消息，第三个参数才是数据
sendResponse(true, '获取成功', [
    'details' => $details,
    'comments' => $comments
]);

$conn->close();
?>
