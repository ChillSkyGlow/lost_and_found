<?php
// backend/api/listings/get_matched_listings.php
error_reporting(E_ERROR);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../config/helpers.php';

try {
    // 清除所有之前的输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();

    requireLogin();
    $user_id = getCurrentUserId();
    $conn = get_db_connection();

    // 获取当前用户所有未完成的失物和拾物信息
    $lost_query = "SELECT lost_listing_id, item_name, description, event_time, 'lost' as type FROM lost_listings WHERE user_id = ? AND status = 'pending'";
    $found_query = "SELECT found_listing_id, item_name, description, event_time, 'found' as type FROM found_listings WHERE user_id = ? AND status = 'unclaimed'";

    $user_items = [];
    // 失物
    $stmt = $conn->prepare($lost_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_items[] = $row;
    }
    $stmt->close();
    // 拾物
    $stmt = $conn->prepare($found_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_items[] = $row;
    }
    $stmt->close();

    $matches = [];
    // 针对每条用户信息，查找匹配的他人帖子
    foreach ($user_items as $item) {
        // 当前用户的物品信息
        $source_listing_id = $item['type'] === 'lost' ? $item['lost_listing_id'] : $item['found_listing_id'];
        $source_listing_type = $item['type'];

        $type = $item['type'];
        $item_name = $item['item_name'];
        $desc = $item['description'];
        $event_time = $item['event_time'];
        $keywords = array_filter(explode(' ', preg_replace('/[，。,.!！?？]/u', ' ', $item_name . ' ' . $desc)));
        $keywords = array_unique($keywords);
        $params = [];
        $sql = '';
        if ($type === 'lost') {
            // 匹配他人发布的拾物
            $sql = "SELECT f.found_listing_id as id, f.user_id as owner_id, f.item_name as title, f.description, f.image_file_path as image_path, f.status, f.created_at, u.username, 'found' as listing_type
                    FROM found_listings f
                    JOIN users u ON f.user_id = u.user_id
                    WHERE f.user_id != ? AND f.status = 'unclaimed' ";
            $params[] = $user_id;
        } else {
            // 匹配他人发布的失物
            $sql = "SELECT l.lost_listing_id as id, l.user_id as owner_id, l.item_name as title, l.description, l.image_file_path as image_path, l.status, l.created_at, u.username, 'lost' as listing_type
                    FROM lost_listings l
                    JOIN users u ON l.user_id = u.user_id
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
        $stmt = $conn->prepare($sql);
        // 动态绑定参数
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['match_source'] = $type;
            $matches[] = $row;
            
            // 对方物品的信息
            $matched_user_id = $row['owner_id'];
            $matched_listing_id = $row['id'];
            $matched_listing_type = $row['listing_type'];

            // 1. 通知对方用户，他们现有的物品与当前用户的物品匹配
            $check_sql_1 = "SELECT id FROM matched_notifications WHERE user_id = ? AND listing_id = ? AND listing_type = ? AND source_listing_id = ? AND source_listing_type = ?";
            $check_stmt_1 = $conn->prepare($check_sql_1);
            $check_stmt_1->bind_param('iisis', $matched_user_id, $source_listing_id, $source_listing_type, $matched_listing_id, $matched_listing_type);
            $check_stmt_1->execute();
            $check_result_1 = $check_stmt_1->get_result();
            
            if ($check_result_1->num_rows == 0) {
                $insert_sql_1 = "INSERT INTO matched_notifications (user_id, listing_id, listing_type, source_listing_id, source_listing_type, is_read) VALUES (?, ?, ?, ?, ?, 0)";
                $insert_stmt_1 = $conn->prepare($insert_sql_1);
                $insert_stmt_1->bind_param('iisis', $matched_user_id, $source_listing_id, $source_listing_type, $matched_listing_id, $matched_listing_type);
                $insert_stmt_1->execute();
                $insert_stmt_1->close();
            }
            $check_stmt_1->close();

            // 2. 通知当前用户，他们发布的物品与一个现有物品匹配
            $check_sql_2 = "SELECT id FROM matched_notifications WHERE user_id = ? AND listing_id = ? AND listing_type = ? AND source_listing_id = ? AND source_listing_type = ?";
            $check_stmt_2 = $conn->prepare($check_sql_2);
            $check_stmt_2->bind_param('iisis', $user_id, $matched_listing_id, $matched_listing_type, $source_listing_id, $source_listing_type);
            $check_stmt_2->execute();
            $check_result_2 = $check_stmt_2->get_result();
            
            if ($check_result_2->num_rows == 0) {
                $insert_sql_2 = "INSERT INTO matched_notifications (user_id, listing_id, listing_type, source_listing_id, source_listing_type, is_read) VALUES (?, ?, ?, ?, ?, 0)";
                $insert_stmt_2 = $conn->prepare($insert_sql_2);
                $insert_stmt_2->bind_param('iisis', $user_id, $matched_listing_id, $matched_listing_type, $source_listing_id, $source_listing_type);
                $insert_stmt_2->execute();
                $insert_stmt_2->close();
            }
            $check_stmt_2->close();
        }
        $stmt->close();
    }
    // 去重（按id+listing_type）
    $unique = [];
    foreach ($matches as $m) {
        $key = $m['listing_type'] . '_' . $m['id'];
        $unique[$key] = $m;
    }
    $matches = array_values($unique);
    // 最多返回20条
    $matches = array_slice($matches, 0, 20);
    sendResponse(true, '获取匹配物品成功', $matches);
    $conn->close();
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $e->getMessage()
    ]);
} finally {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
} 