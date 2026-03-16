<?php
// backend/api/listings/publish_listing.php
// 抑制错误显示，防止PHP错误污染JSON输出
error_reporting(E_ERROR);
ini_set('display_errors', 0);

require_once '../../config/helpers.php';
require_once '../../config/database.php';

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed');
}

// 验证用户是否登录
requireLogin();
$user_id = $_SESSION['user_id'];

// 基本字段验证
$required_fields = ['item_name', 'description', 'location_details', 'event_time', 'listing_type'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        sendResponse(false, "字段 '{$field}' 是必需的");
    }
}

// 变量赋值和清理
$item_name = htmlspecialchars(strip_tags($_POST['item_name']));
$description = htmlspecialchars(strip_tags($_POST['description']));
$location_details = htmlspecialchars(strip_tags($_POST['location_details']));
$event_time = $_POST['event_time'];
$location_coordinates = isset($_POST['location_coordinates']) ? htmlspecialchars(strip_tags($_POST['location_coordinates'])) : null;
$type = $_POST['listing_type']; // 'lost' or 'found'

if ($type !== 'lost' && $type !== 'found') {
    http_response_code(400);
    sendResponse(false, '无效的发布类型');
}

$conn = get_db_connection(); // 正确初始化数据库连接

$image_file_path = null; // 默认为 null

// 图片处理
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../uploads/';
    // 确保上传目录存在
    if (!is_dir($upload_dir)) {
        try {
            // 创建目录并设置权限
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception("无法创建上传目录");
            }
            // 设置目录权限
            chmod($upload_dir, 0777);
        } catch (Exception $e) {
            http_response_code(500);
            sendResponse(false, '上传目录配置错误: ' . $e->getMessage());
        }
    }

    $file_info = pathinfo($_FILES['image']['name']);
    $file_ext = strtolower($file_info['extension']);
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_ext, $allowed_exts)) {
        // 使用更安全的方法生成唯一文件名
        $new_file_name = bin2hex(random_bytes(16)) . '.' . $file_ext;
        $dest_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest_path)) {
            $image_file_path = 'uploads/' . $new_file_name;
        } else {
            http_response_code(500);
            sendResponse(false, '文件上传失败');
        }
    } else {
        http_response_code(400);
        sendResponse(false, '无效的文件类型. 只允许 JPG, PNG, GIF');
    }
}

// 根据类型选择正确的表
$table_name = $type === 'lost' ? 'lost_listings' : 'found_listings';
$status = $type === 'lost' ? 'pending' : 'unclaimed'; // 根据类型设置初始状态

$sql = "INSERT INTO {$table_name} (user_id, item_name, description, location_details, location_coordinates, event_time, image_file_path, status, comment_is_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isssssss", $user_id, $item_name, $description, $location_details, $location_coordinates, $event_time, $image_file_path, $status);

if ($stmt->execute()) {
    $new_listing_id = $stmt->insert_id;
    
    // 在发布成功后立即执行匹配算法
    // 提取关键词
    $keywords = array_filter(explode(' ', preg_replace('/[，。,.!！?？]/u', ' ', $item_name . ' ' . $description)));
    $keywords = array_unique($keywords);
    
    // 查找匹配的物品
    $params = [];
    $sql = '';
    
    if ($type === 'lost') {
        // 如果发布的是失物信息，则匹配他人发布的拾物
        $sql = "SELECT f.found_listing_id as id, f.user_id as owner_id, f.item_name as title, 'found' as listing_type
                FROM found_listings f
                WHERE f.user_id != ? AND f.status = 'unclaimed' ";
        $params[] = $user_id;
    } else {
        // 如果发布的是拾物信息，则匹配他人发布的失物
        $sql = "SELECT l.lost_listing_id as id, l.user_id as owner_id, l.item_name as title, 'lost' as listing_type
                FROM lost_listings l
                WHERE l.user_id != ? AND l.status = 'pending' ";
        $params[] = $user_id;
    }
    
    // 关键词匹配
    if (count($keywords) > 0) {
        $sql .= " AND (" . implode(' OR ', array_fill(0, count($keywords), "(item_name LIKE ? OR description LIKE ?)") ) . ")";
        foreach ($keywords as $kw) {
            $params[] = "%$kw%";
            $params[] = "%$kw%";
        }
    }
    
    // 时间接近（±7天）
    if ($event_time) {
        $sql .= " AND ABS(DATEDIFF(event_time, ?)) <= 7";
        $params[] = $event_time;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 10";
    $stmt_match = $conn->prepare($sql);
    
    // 动态绑定参数
    if (count($params) > 0) {
        $types = str_repeat('s', count($params));
        $stmt_match->bind_param($types, ...$params);
    }
    
    $stmt_match->execute();
    $result = $stmt_match->get_result();
    
    // 为匹配的物品创建交叉通知
    while ($row = $result->fetch_assoc()) {
        // $user_id = 后发布者ID (当前用户)
        // $new_listing_id = 新物品ID
        // $type = 新物品类型
        // $row['owner_id'] = 先发布者ID
        // $row['id'] = 已存在物品ID
        // $row['listing_type'] = 已存在物品类型

        // 1. 通知先发布者，他们现有的物品($row)与一个新物品($new_listing_id)匹配
        $check_sql_1 = "SELECT id FROM matched_notifications WHERE user_id = ? AND listing_id = ? AND listing_type = ? AND source_listing_id = ? AND source_listing_type = ?";
        $check_stmt_1 = $conn->prepare($check_sql_1);
        $check_stmt_1->bind_param('iisis', $row['owner_id'], $new_listing_id, $type, $row['id'], $row['listing_type']);
        $check_stmt_1->execute();
        $check_result_1 = $check_stmt_1->get_result();
        
        if ($check_result_1->num_rows == 0) {
            $insert_sql_1 = "INSERT INTO matched_notifications (user_id, listing_id, listing_type, source_listing_id, source_listing_type, is_read) VALUES (?, ?, ?, ?, ?, 0)";
            $insert_stmt_1 = $conn->prepare($insert_sql_1);
            $insert_stmt_1->bind_param('iisis', $row['owner_id'], $new_listing_id, $type, $row['id'], $row['listing_type']);
            $insert_stmt_1->execute();
            $insert_stmt_1->close();
        }
        $check_stmt_1->close();
        
        // 2. 通知后发布者(当前用户)，他们的新物品($new_listing_id)与一个现有物品($row)匹配
        $check_sql_2 = "SELECT id FROM matched_notifications WHERE user_id = ? AND listing_id = ? AND listing_type = ? AND source_listing_id = ? AND source_listing_type = ?";
        $check_stmt_2 = $conn->prepare($check_sql_2);
        $check_stmt_2->bind_param('iisis', $user_id, $row['id'], $row['listing_type'], $new_listing_id, $type);
        $check_stmt_2->execute();
        $check_result_2 = $check_stmt_2->get_result();
        
        if ($check_result_2->num_rows == 0) {
            $insert_sql_2 = "INSERT INTO matched_notifications (user_id, listing_id, listing_type, source_listing_id, source_listing_type, is_read) VALUES (?, ?, ?, ?, ?, 0)";
            $insert_stmt_2 = $conn->prepare($insert_sql_2);
            $insert_stmt_2->bind_param('iisis', $user_id, $row['id'], $row['listing_type'], $new_listing_id, $type);
            $insert_stmt_2->execute();
            $insert_stmt_2->close();
        }
        $check_stmt_2->close();
    }
    
    $stmt_match->close();
    
    sendResponse(true, '发布成功！', ['listing_id' => $new_listing_id]);
} else {
    http_response_code(500);
    sendResponse(false, '发布失败');
}

$stmt->close();
$conn->close();
?>
