<?php
// backend/api/listings/update_listing.php
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

    // 验证必填字段
    $required_fields = ['listing_id', 'type', 'title', 'description', 'location_details', 'event_time'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            header('Content-Type: application/json');
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => "字段 '{$field}' 是必需的"]);
            exit;
        }
    }

    // 提取并清理数据
    $listing_id = (int)$_POST['listing_id'];
    $listing_type = $_POST['type']; // 'lost' or 'found'
    $title = htmlspecialchars(strip_tags($_POST['title']));
    $description = htmlspecialchars(strip_tags($_POST['description']));
    $location_details = htmlspecialchars(strip_tags($_POST['location_details']));
    $event_time = $_POST['event_time'];
    $location_coordinates = isset($_POST['location_coordinates']) ? htmlspecialchars(strip_tags($_POST['location_coordinates'])) : null;

    // 验证发布类型
    if ($listing_type !== 'lost' && $listing_type !== 'found') {
        header('Content-Type: application/json');
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => '无效的物品类型']);
        exit;
    }

    // 根据类型选择表和ID字段
    $table_name = $listing_type . '_listings';
    $id_field = $listing_type . '_listing_id';

    // 获取数据库连接
    $conn = get_db_connection();
    
    // 首先验证物品所有权
    $ownership_sql = "SELECT user_id, image_file_path FROM {$table_name} WHERE {$id_field} = ?";
    $stmt = $conn->prepare($ownership_sql);
    if (!$stmt) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '数据库查询准备失败: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('i', $listing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => '找不到该物品']);
        exit;
    }
    
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if ($item['user_id'] != $user_id) {
        header('Content-Type: application/json');
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => '您没有权限修改此物品']);
        exit;
    }

    // 处理图片上传 (如果有)
    $image_file_path = $item['image_file_path']; // 默认使用现有图片路径
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
        $upload_dir = '../../uploads/';
        
        // 确保上传目录存在
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                header('Content-Type: application/json');
                http_response_code(500); // Internal Server Error
                echo json_encode(['success' => false, 'message' => '无法创建上传目录']);
                exit;
            }
        }

        // 检查文件类型
        $file_info = pathinfo($_FILES['image']['name']);
        $file_ext = strtolower($file_info['extension']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_exts)) {
            header('Content-Type: application/json');
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => '无效的文件类型，仅支持JPG、PNG和GIF']);
            exit;
        }

        // 生成唯一的文件名
        $new_file_name = 'img_' . uniqid('', true) . '.' . $file_ext;
        $dest_path = $upload_dir . $new_file_name;

        // 移动上传的文件
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest_path)) {
            header('Content-Type: application/json');
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => '文件上传失败']);
            exit;
        }

        // 如果成功上传新图片，则删除旧图片
        if ($item['image_file_path'] && file_exists('../../' . $item['image_file_path'])) {
            unlink('../../' . $item['image_file_path']);
        }

        $image_file_path = 'uploads/' . $new_file_name;
    }

    // 设置默认分类（如果数据库需要）
    $category = isset($_POST['category']) ? htmlspecialchars(strip_tags($_POST['category'])) : '其他';

    // 准备更新语句
    $update_sql = "UPDATE {$table_name} SET 
                    item_name = ?,
                    description = ?,
                    location_details = ?,
                    event_time = ?,
                    image_file_path = ?,
                    location_coordinates = ?,
                    category = ?
                WHERE {$id_field} = ? AND user_id = ?";
    
    // 准备并执行查询
    $update_stmt = $conn->prepare($update_sql);
    if (!$update_stmt) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '数据库查询准备失败: ' . $conn->error]);
        exit;
    }

    $update_stmt->bind_param('sssssssii', 
        $title, 
        $description, 
        $location_details, 
        $event_time,
        $image_file_path,
        $location_coordinates,
        $category,
        $listing_id,
        $user_id
    );

    if (!$update_stmt->execute()) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '数据库操作失败: ' . $update_stmt->error]);
        exit;
    }

    // 检查是否有行被更新
    if ($update_stmt->affected_rows === 0) {
        header('Content-Type: application/json');
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => '没有任何更改']);
        exit;
    }

    $update_stmt->close();
    $conn->close();

    // 返回成功响应
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => '更新成功！', 
        'data' => ['listing_id' => $listing_id]
    ]);
    
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