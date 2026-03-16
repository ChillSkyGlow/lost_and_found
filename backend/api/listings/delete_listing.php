<?php
// backend/api/listings/delete_listing.php
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

if ($listing_id <= 0) {
        header('Content-Type: application/json');
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => '无效的请求参数']);
        exit;
}

    // 获取数据库连接
    $conn = get_db_connection();
    
    // 新增：前端传type参数，优先用type和id定位，彻底避免id冲突
    $type = isset($_POST['type']) ? $_POST['type'] : '';
$listing_info = null;
    if ($type === 'lost') {
        $stmt = $conn->prepare("SELECT user_id, image_file_path FROM lost_listings WHERE lost_listing_id = ?");
        $stmt->bind_param('i', $listing_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $listing_info = $result->fetch_assoc();
            $listing_info['type'] = 'lost';
        }
        $stmt->close();
    } elseif ($type === 'found') {
        $stmt = $conn->prepare("SELECT user_id, image_file_path FROM found_listings WHERE found_listing_id = ?");
        $stmt->bind_param('i', $listing_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $listing_info = $result->fetch_assoc();
            $listing_info['type'] = 'found';
        }
        $stmt->close();
    } else {
        // 兼容老前端：自动判断
$stmt = $conn->prepare("SELECT user_id, image_file_path FROM lost_listings WHERE lost_listing_id = ?");
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $listing_info = $result->fetch_assoc();
    $listing_info['type'] = 'lost';
}
$stmt->close();
if (!$listing_info) {
    $stmt = $conn->prepare("SELECT user_id, image_file_path FROM found_listings WHERE found_listing_id = ?");
    $stmt->bind_param('i', $listing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $listing_info = $result->fetch_assoc();
        $listing_info['type'] = 'found';
    }
    $stmt->close();
        }
}

    // 物品不存在
if (!$listing_info) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => '物品不存在或已被删除']);
        exit;
}

    // 验证所有权
if ($listing_info['user_id'] != $user_id) {
        header('Content-Type: application/json');
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => '您没有权限删除此物品']);
        exit;
    }

    // 执行删除
$table_name = $listing_info['type'] . '_listings';
$id_field = $listing_info['type'] . '_listing_id';
$comment_table = $listing_info['type'] . '_comment';

$conn->begin_transaction();

try {
    // 删除相关评论
    $comment_sql = "DELETE FROM {$comment_table} WHERE {$id_field} = ?";
    $comment_stmt = $conn->prepare($comment_sql);
        if (!$comment_stmt) {
            throw new Exception('无法准备删除评论的查询');
        }
        
    $comment_stmt->bind_param('i', $listing_id);
        if (!$comment_stmt->execute()) {
            throw new Exception('删除评论失败');
        }
    $comment_stmt->close();

    // 删除物品信息
        $delete_sql = "DELETE FROM {$table_name} WHERE {$id_field} = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
        if (!$delete_stmt) {
            throw new Exception('无法准备删除物品的查询');
        }
        
        $delete_stmt->bind_param('ii', $listing_id, $user_id);
        if (!$delete_stmt->execute()) {
            throw new Exception('删除物品失败');
        }
    $delete_stmt->close();

    $conn->commit();

    // 数据库操作成功后，删除图片文件
    if (!empty($listing_info['image_file_path'])) {
        $image_path = '../../' . $listing_info['image_file_path'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => '删除成功']);

    } catch (Exception $e) {
    $conn->rollback();
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '删除失败: ' . $e->getMessage()]);
}

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
