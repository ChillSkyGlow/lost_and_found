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

    // 物品发布成功后，按 4 维（item_name + category + location + 时间±7天）查找匹配并发送双向邮件 + 站内匹配通知
    find_and_notify_matches($conn, $listing_id, $type, $item_name, $category, $location_details, $event_time, $user_id);

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

function find_and_notify_matches($conn, $new_listing_id, $new_listing_type, $item_name, $category, $location_details, $event_time, $user_id) {
    try {
        $match_table_name = $new_listing_type == 'lost' ? 'found_listings' : 'lost_listings';
        $match_id_col = $new_listing_type == 'lost' ? 'found_listing_id' : 'lost_listing_id';
        $match_status = $new_listing_type == 'lost' ? 'unclaimed' : 'pending';

        $params = [];
        $types = '';
        $sql_match = "SELECT * FROM {$match_table_name} WHERE user_id != ? AND status = ? AND category = ?";
        $params[] = $user_id; $types .= 'i';
        $params[] = $match_status; $types .= 's';
        $params[] = $category; $types .= 's';

        $combined = trim($item_name . ' ' . $location_details);
        if ($combined !== '') {
            $keywords = array_filter(explode(' ', preg_replace('/[，。,.!！?？、\s]/u', ' ', $combined)));
            $keywords = array_values(array_unique(array_filter($keywords, function($w) { return mb_strlen($w) >= 1; })));
        } else {
            $keywords = [];
        }
        if (count($keywords) > 0) {
            $kw_clauses = [];
            foreach ($keywords as $kw) {
                $kw_clauses[] = "(item_name LIKE ? OR description LIKE ? OR location_details LIKE ?)";
                $kw_like = '%' . $kw . '%';
                $params[] = $kw_like; $types .= 's';
                $params[] = $kw_like; $types .= 's';
                $params[] = $kw_like; $types .= 's';
            }
            $sql_match .= " AND (" . implode(' OR ', $kw_clauses) . ")";
        }

        if ($event_time) {
            $sql_match .= " AND ABS(DATEDIFF(event_time, ?)) <= 7";
            $params[] = $event_time; $types .= 's';
        }
        $sql_match .= " ORDER BY created_at DESC LIMIT 10";

        $stmt_match = $conn->prepare($sql_match);
        if (!$stmt_match) {
            error_log("匹配查询 prepare 失败: " . $conn->error . " SQL=" . $sql_match);
            return;
        }
        if (count($params) > 0) {
            $stmt_match->bind_param($types, ...$params);
        }
        $stmt_match->execute();
        $result_match = $stmt_match->get_result();

        while ($match = $result_match->fetch_assoc()) {
            $match_listing_id = $match[$match_id_col];
            $match_owner_id = $match['user_id'];
            $match_listing_type = $new_listing_type == 'lost' ? 'found' : 'lost';

            $lost_listing_id = ($new_listing_type == 'lost') ? $new_listing_id : $match_listing_id;
            $found_listing_id = ($new_listing_type == 'found') ? $new_listing_id : $match_listing_id;

            // a) 写入 matches 匹配对表（UNIQUE KEY lost_found 去重），match_score = 1.0（命中 4 维）
            try {
                $sql_pair = "INSERT IGNORE INTO matches (lost_listing_id, found_listing_id, match_score) VALUES (?, ?, 1.0)";
                $stmt_pair = $conn->prepare($sql_pair);
                $stmt_pair->bind_param("ii", $lost_listing_id, $found_listing_id);
                $stmt_pair->execute();
                $stmt_pair->close();
            } catch (Exception $e) {
                error_log("写入 matches 匹配对失败: " . $e->getMessage());
            }

            // b) 双向写入 matched_notifications 站内匹配通知（先去重）— 复用 publish_listing 模式
            try {
                // 方向1：通知先发布者（match_owner_id）：他们已有的物品（match_id / match_type）与一个新物品（new_id / new_type）匹配
                $check_sql_1 = "SELECT id FROM matched_notifications
                    WHERE user_id = ? AND listing_id = ? AND listing_type = ?
                      AND source_listing_id = ? AND source_listing_type = ?";
                $check_stmt_1 = $conn->prepare($check_sql_1);
                $check_stmt_1->bind_param('iisis', $match_owner_id, $new_listing_id, $new_listing_type, $match_listing_id, $match_listing_type);
                $check_stmt_1->execute();
                $check_result_1 = $check_stmt_1->get_result();
                if ($check_result_1->num_rows == 0) {
                    $insert_sql_1 = "INSERT INTO matched_notifications
                        (user_id, listing_id, listing_type, source_listing_id, source_listing_type, is_read)
                        VALUES (?, ?, ?, ?, ?, 0)";
                    $insert_stmt_1 = $conn->prepare($insert_sql_1);
                    $insert_stmt_1->bind_param('iisis', $match_owner_id, $new_listing_id, $new_listing_type, $match_listing_id, $match_listing_type);
                    $insert_stmt_1->execute();
                    $insert_stmt_1->close();
                }
                $check_stmt_1->close();

                // 方向2：通知后发布者（当前用户 user_id）：他们刚发布的新物品（new_id / new_type）与一个已存在物品（match_id / match_type）匹配
                $check_sql_2 = "SELECT id FROM matched_notifications
                    WHERE user_id = ? AND listing_id = ? AND listing_type = ?
                      AND source_listing_id = ? AND source_listing_type = ?";
                $check_stmt_2 = $conn->prepare($check_sql_2);
                $check_stmt_2->bind_param('iisis', $user_id, $match_listing_id, $match_listing_type, $new_listing_id, $new_listing_type);
                $check_stmt_2->execute();
                $check_result_2 = $check_stmt_2->get_result();
                if ($check_result_2->num_rows == 0) {
                    $insert_sql_2 = "INSERT INTO matched_notifications
                        (user_id, listing_id, listing_type, source_listing_id, source_listing_type, is_read)
                        VALUES (?, ?, ?, ?, ?, 0)";
                    $insert_stmt_2 = $conn->prepare($insert_sql_2);
                    $insert_stmt_2->bind_param('iisis', $user_id, $match_listing_id, $match_listing_type, $new_listing_id, $new_listing_type);
                    $insert_stmt_2->execute();
                    $insert_stmt_2->close();
                }
                $check_stmt_2->close();
            } catch (Exception $e) {
                error_log("写入 matched_notifications 失败: " . $e->getMessage());
            }

            // c) 发送邮件通知（保持原逻辑，不影响主流程）
            try {
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
                    $subject_to_lost = "您的失物可能已找到！";
                    $body_to_lost = "您发布的失物 '{$users_info['lost_item_name']}' 与一个新发布的招领 '{$users_info['found_item_name']}' 匹配。<br><br>请登录平台查看详情并确认。";
                    send_notification_email($users_info['lost_user_email'], $subject_to_lost, $body_to_lost);

                    $subject_to_found = "您发布的招领物品可能找到了失主！";
                    $body_to_found = "您发布的招领 '{$users_info['found_item_name']}' 与一个失物 '{$users_info['lost_item_name']}' 匹配。<br><br>请登录平台查看详情并等待对方联系。";
                    send_notification_email($users_info['found_user_email'], $subject_to_found, $body_to_found);
                }
                $stmt_users->close();

            } catch (Exception $e) {
                error_log("匹配邮件发送失败: " . $e->getMessage());
            }
        }
        $stmt_match->close();
    } catch (Exception $e) {
        error_log("匹配查询失败: " . $e->getMessage());
    }
}
