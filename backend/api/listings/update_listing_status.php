<?php
// backend/api/listings/update_listing_status.php
// 抑制所有类型的错误显示，只记录到错误日志
error_reporting(0);
ini_set('display_errors', 0);

// 设置自定义错误和异常处理程序，确保任何错误都以JSON格式返回
function exception_handler($exception) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '服务器异常: ' . $exception->getMessage()
    ]);
    exit;
}

function error_handler($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $errstr
    ]);
    exit;
}

// 注册自定义错误处理函数
set_exception_handler('exception_handler');
set_error_handler('error_handler');

// 清除所有之前的输出缓冲
while (ob_get_level()) {
    ob_end_clean();
}

// 启动新的输出缓冲
ob_start();

try {
    // 包含必要的配置文件
require_once '../../config/database.php';
require_once '../../config/helpers.php';

    // 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'message' => '只允许POST请求']);
        exit;
}

    // 验证用户是否登录
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => '请先登录']);
        exit;
    }

$user_id = $_SESSION['user_id'];

    // 获取并验证输入参数
$listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$type = $_POST['type'] ?? ''; // type is needed to know which table to update

if ($listing_id <= 0 || !in_array($type, ['lost', 'found'])) {
        header('Content-Type: application/json');
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => '无效的请求参数']);
        exit;
}

    // 根据类型确定表名、主键列和新状态
if ($type === 'lost') {
    $table_name = 'lost_listings';
    $id_column = 'lost_listing_id';
    $new_status = 'solved';
} else { // 'found'
    $table_name = 'found_listings';
    $id_column = 'found_listing_id';
    $new_status = 'claimed';
}

    // 获取数据库连接
    $conn = get_db_connection();

    // 先检查物品是否存在且属于当前用户
    $check_sql = "SELECT * FROM {$table_name} WHERE {$id_column} = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '数据库查询准备失败']);
        exit;
    }
    
    $check_stmt->bind_param('ii', $listing_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        header('Content-Type: application/json');
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => '您无权修改此物品或物品不存在']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();

    // 执行状态更新
    $update_sql = "UPDATE {$table_name} SET status = ? WHERE {$id_column} = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    if (!$update_stmt) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '数据库查询准备失败']);
        exit;
    }

    $update_stmt->bind_param('sii', $new_status, $listing_id, $user_id);

    if (!$update_stmt->execute()) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '数据库更新失败: ' . $update_stmt->error]);
        $update_stmt->close();
        exit;
    }
    
    // 检查是否有行受到影响
    if ($update_stmt->affected_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => '物品状态已成功更新！',
            'data' => ['newStatus' => $new_status]
        ]);
    } else {
        // 理论上不会到这里，因为我们已经检查过物品是否存在
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '状态更新失败，请重试']);
}

    $update_stmt->close();
$conn->close();

} catch (Exception $e) {
    // 捕获并处理任何未预见的异常
    header('Content-Type: application/json');
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
} finally {
    // 确保输出缓冲被刷新
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}
?>
