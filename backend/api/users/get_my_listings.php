<?php
// backend/api/users/get_my_listings.php
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// 确保任何错误都以JSON格式返回
function exception_handler($exception) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '异常: ' . $exception->getMessage()
    ]);
    exit;
}

function error_handler($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '错误: ' . $errstr
    ]);
    exit;
}

set_exception_handler('exception_handler');
set_error_handler('error_handler');

try {
    // 清除所有之前的输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }
    // 启动输出缓冲
    ob_start();

    if (session_status() === PHP_SESSION_NONE) {
session_start();
    }
    
require_once '../../config/database.php';
require_once '../../config/helpers.php';

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
        sendResponse(false, 'Method not allowed');
}

// 验证用户是否登录
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        sendResponse(false, '请先登录');
    }

$user_id = $_SESSION['user_id'];

// 查询该用户的所有失物信息
$lost_query = "SELECT 'lost' as listing_type, 
                    lost_listing_id as id, 
                    item_name as title, 
                    image_file_path as image_path, 
                    status, 
                    created_at
               FROM lost_listings 
               WHERE user_id = ?";

// 查询该用户的所有拾物信息
$found_query = "SELECT 'found' as listing_type, 
                    found_listing_id as id, 
                    item_name as title, 
                    image_file_path as image_path, 
                    status, 
                    created_at
                FROM found_listings 
                WHERE user_id = ?";

// 合并查询
$final_query = "($lost_query) UNION ALL ($found_query) ORDER BY created_at DESC";

    $conn = get_db_connection();
$stmt = $conn->prepare($final_query);
if ($stmt === false) {
        sendResponse(false, '数据库查询准备失败: ' . $conn->error);
}

// 绑定两次 user_id，因为 UNION 两边各需要一次
    $stmt->bind_param('ss', $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

$listings = [];
while ($row = $result->fetch_assoc()) {
    $listings[] = $row;
}

$stmt->close();
$conn->close();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '成功获取列表',
        'data' => $listings
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $e->getMessage()
    ]);
} finally {
    // 确保输出缓冲被刷新
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}
?>
