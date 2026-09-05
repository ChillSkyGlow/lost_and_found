<?php
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
    require_once '../../config/mailer.php'; // 引入邮件发送模块

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
    $required_fields = ['item_name', 'description', 'location_details', 'event_time', 'listing_type'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            header('Content-Type: application/json');
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => "字段 '{$field}' 是必需的"]);
            exit;
        }
    }

    // 提取并清理数据
    $item_name = htmlspecialchars(strip_tags($_POST['item_name']));
    $description = htmlspecialchars(strip_tags($_POST['description']));
    $location_details = htmlspecialchars(strip_tags($_POST['location_details']));
    $event_time = $_POST['event_time'];
    $location_coordinates = isset($_POST['location_coordinates']) ? htmlspecialchars(strip_tags($_POST['location_coordinates'])) : '';
    $type = $_POST['listing_type']; // 'lost' or 'found'

    // 字段长度校验（2026-09-05 功能2 新增，匹配 varchar 上限）
    if (mb_strlen($item_name) < 1 || mb_strlen($item_name) > 100) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '物品名称长度必须在 1-100 个字符之间']);
        exit;
    }
    if (mb_strlen($location_details) > 255) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '地点详情长度不能超过 255 个字符']);
        exit;
    }
    // 设置默认分类并校验长度
    $category = isset($_POST['category']) ? htmlspecialchars(strip_tags($_POST['category'])) : '其他';
    if (mb_strlen($category) < 1 || mb_strlen($category) > 50) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '物品类别长度必须在 1-50 个字符之间']);
        exit;
    }

    // 验证发布类型
    if ($type !== 'lost' && $type !== 'found') {
        header('Content-Type: application/json');
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => '无效的发布类型']);
        exit;
    }

    // 获取数据库连接
    $conn = get_db_connection();
    
    // 设置默认图像路径为null
    $image_file_path = null;

    // 处理图片上传
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
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

        // 检查扩展名（第一关）
        $file_info = pathinfo($_FILES['image']['name']);
        $file_ext = strtolower($file_info['extension']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_exts)) {
            header('Content-Type: application/json');
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => '无效的文件类型，仅支持JPG、JPEG、PNG和GIF']);
            exit;
        }

        // 检查真实 MIME 类型（第二关，2026-09-05 功能2 新增 finfo 双保险）
        if (!function_exists('finfo_open')) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '服务器缺少 finfo 扩展，无法校验图片合法性']);
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($real_mime, $allowed_mimes, true)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '图片真实内容类型不合法，仅允许上传真实的 JPG/PNG/GIF 图片']);
            exit;
        }
        // MIME 与扩展名一致性（防止 .png 扩展名但真实 image/jpeg 这种不一致，防绕过）
        $mime_to_ext = ['image/jpeg' => ['jpg','jpeg'], 'image/png' => ['png'], 'image/gif' => ['gif']];
        if (!isset($mime_to_ext[$real_mime]) || !in_array($file_ext, $mime_to_ext[$real_mime], true)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '图片扩展名与真实内容类型不一致，请更换合法图片']);
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

        $image_file_path = 'uploads/' . $new_file_name;
    }

    // 根据类型选择表和状态
    $table_name = $type === 'lost' ? 'lost_listings' : 'found_listings';
    $status = $type === 'lost' ? 'pending' : 'unclaimed';
    $comment_is_updated = 0; // 显式写入 0，不依赖数据库 DEFAULT

    // 准备插入语句（2026-09-05 功能2 新增 comment_is_updated 列，显式写入 0）
    $sql = "INSERT INTO {$table_name} (user_id, item_name, description, location_details, location_coordinates, event_time, image_file_path, status, category, comment_is_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    // 准备并执行查询
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '数据库查询准备失败: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("issssssssi", $user_id, $item_name, $description, $location_details, $location_coordinates, $event_time, $image_file_path, $status, $category, $comment_is_updated);

    if (!$stmt->execute()) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => '数据库操作失败: ' . $stmt->error]);
        exit;
    }

    $listing_id = $stmt->insert_id;
    $stmt->close();
    
    // 物品发布成功后，查找匹配并发送通知
    find_and_notify_matches($conn, $listing_id, $type, $item_name);

    $conn->close();

    // 返回成功响应
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => '发布成功！', 
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

function find_and_notify_matches($conn, $new_listing_id, $new_listing_type, $item_name) {
    try {
        $match_table_name = $new_listing_type == 'lost' ? 'found_listings' : 'lost_listings';
        $match_id_col = $new_listing_type == 'lost' ? 'found_listing_id' : 'lost_listing_id';

        $sql_match = "SELECT * FROM {$match_table_name} WHERE item_name LIKE ?";
        $stmt_match = $conn->prepare($sql_match);
        $item_name_like = '%' . $item_name . '%';
        $stmt_match->bind_param("s", $item_name_like);
        $stmt_match->execute();
        $result_match = $stmt_match->get_result();

        while ($match = $result_match->fetch_assoc()) {
            $lost_listing_id = ($new_listing_type == 'lost') ? $new_listing_id : $match[$match_id_col];
            $found_listing_id = ($new_listing_type == 'found') ? $new_listing_id : $match[$match_id_col];
            
            // 新增：发送邮件通知
            try {
                // 1. 获取双方用户的邮箱和物品信息
                $sql_users_info = "
                    SELECT 
                        l.item_name as lost_item_name, u_lost.email as lost_user_email,
                        f.item_name as found_item_name, u_found.email as found_user_email
                    FROM lost_listings l
                    JOIN users u_lost ON l.user_id = u_lost.user_id
                    JOIN found_listings f ON f.found_listing_id = ?
                    JOIN users u_found ON f.user_id = u_found.user_id
                    WHERE l.lost_listing_id = ?
                ";
                $stmt_users = $conn->prepare($sql_users_info);
                $stmt_users->bind_param("ii", $found_listing_id, $lost_listing_id);
                $stmt_users->execute();
                $users_info = $stmt_users->get_result()->fetch_assoc();

                if ($users_info) {
                    // 2. 发送邮件给失主
                    $subject_to_lost = "您的失物可能已找到！";
                    $body_to_lost = "您发布的失物 '{$users_info['lost_item_name']}' 与一个新发布的招领 '{$users_info['found_item_name']}' 匹配。<br><br>请登录平台查看详情并确认。";
                    send_notification_email($users_info['lost_user_email'], $subject_to_lost, $body_to_lost);

                    // 3. 发送邮件给拾主
                    $subject_to_found = "您发布的招领物品可能找到了失主！";
                    $body_to_found = "您发布的招领 '{$users_info['found_item_name']}' 与一个失物 '{$users_info['lost_item_name']}' 匹配。<br><br>请登录平台查看详情并等待对方联系。";
                    send_notification_email($users_info['found_user_email'], $subject_to_found, $body_to_found);
                }
                $stmt_users->close();

            } catch (Exception $e) {
                // 邮件发送失败不应影响主流程，记录错误即可
                error_log("匹配邮件发送失败: " . $e->getMessage());
            }
        }
        $stmt_match->close();
    } catch (Exception $e) {
        error_log("匹配查询失败: " . $e->getMessage());
    }
}