<?php
// backend/api/debug_notifications.php
// 开启错误显示
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/helpers.php';
session_start();

// 确保只有管理员可以访问
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, '未登录', []);
    exit;
}

$conn = get_db_connection();
$debug_info = [];

try {
    // 1. 获取所有用户
    $users_sql = "SELECT user_id, username FROM users";
    $users_result = $conn->query($users_sql);
    $users = [];
    while ($row = $users_result->fetch_assoc()) {
        $users[$row['user_id']] = $row['username'];
    }
    $debug_info['users'] = $users;
    
    // 2. 获取所有匹配通知
    $notifications_sql = "SELECT 
        m.id, 
        m.user_id,
        m.listing_id, 
        m.listing_type,
        m.is_read,
        m.created_at,
        CASE 
            WHEN m.listing_type = 'lost' THEN l.item_name
            WHEN m.listing_type = 'found' THEN f.item_name
            ELSE 'Unknown'
        END as item_name,
        CASE 
            WHEN m.listing_type = 'lost' THEN l.user_id
            WHEN m.listing_type = 'found' THEN f.user_id
            ELSE 0
        END as owner_id
        FROM matched_notifications m
        LEFT JOIN lost_listings l ON m.listing_type = 'lost' AND m.listing_id = l.lost_listing_id
        LEFT JOIN found_listings f ON m.listing_type = 'found' AND m.listing_id = f.found_listing_id
        ORDER BY m.created_at DESC";
    
    $notifications_result = $conn->query($notifications_sql);
    $notifications = [];
    while ($row = $notifications_result->fetch_assoc()) {
        $row['username'] = isset($users[$row['user_id']]) ? $users[$row['user_id']] : '未知用户';
        $row['owner_name'] = isset($users[$row['owner_id']]) ? $users[$row['owner_id']] : '未知用户';
        $notifications[] = $row;
    }
    $debug_info['notifications'] = $notifications;
    
    // 3. 检查匹配情况
    $matches = [];
    // 3.1 失物-拾物匹配
    $lost_found_sql = "SELECT 
        l.lost_listing_id, l.item_name as lost_item, l.user_id as lost_user_id,
        f.found_listing_id, f.item_name as found_item, f.user_id as found_user_id
        FROM lost_listings l
        JOIN found_listings f ON 
            (l.item_name LIKE CONCAT('%', f.item_name, '%') OR f.item_name LIKE CONCAT('%', l.item_name, '%'))
            AND ABS(DATEDIFF(l.event_time, f.event_time)) <= 7
        WHERE l.status = 'pending' AND f.status = 'unclaimed'
        ORDER BY l.created_at DESC";
    
    $matches_result = $conn->query($lost_found_sql);
    while ($row = $matches_result->fetch_assoc()) {
        $row['lost_username'] = isset($users[$row['lost_user_id']]) ? $users[$row['lost_user_id']] : '未知用户';
        $row['found_username'] = isset($users[$row['found_user_id']]) ? $users[$row['found_user_id']] : '未知用户';
        
        // 检查是否存在匹配通知
        $lost_user_notified = false;
        $found_user_notified = false;
        
        foreach ($notifications as $notification) {
            // 检查失物用户是否收到关于拾物的通知
            if ($notification['user_id'] == $row['lost_user_id'] && 
                $notification['listing_id'] == $row['found_listing_id'] && 
                $notification['listing_type'] == 'found') {
                $lost_user_notified = true;
            }
            
            // 检查拾物用户是否收到关于失物的通知
            if ($notification['user_id'] == $row['found_user_id'] && 
                $notification['listing_id'] == $row['lost_listing_id'] && 
                $notification['listing_type'] == 'lost') {
                $found_user_notified = true;
            }
        }
        
        $row['lost_user_notified'] = $lost_user_notified;
        $row['found_user_notified'] = $found_user_notified;
        $matches[] = $row;
    }
    $debug_info['matches'] = $matches;
    
    // 4. 检查缺失的通知
    $missing_notifications = [];
    foreach ($matches as $match) {
        if (!$match['lost_user_notified']) {
            $missing_notifications[] = [
                'user_id' => $match['lost_user_id'],
                'username' => $match['lost_username'],
                'listing_id' => $match['found_listing_id'],
                'listing_type' => 'found',
                'item_name' => $match['found_item']
            ];
        }
        
        if (!$match['found_user_notified']) {
            $missing_notifications[] = [
                'user_id' => $match['found_user_id'],
                'username' => $match['found_username'],
                'listing_id' => $match['lost_listing_id'],
                'listing_type' => 'lost',
                'item_name' => $match['lost_item']
            ];
        }
    }
    $debug_info['missing_notifications'] = $missing_notifications;
    
    // 5. 修复缺失的通知
    if (isset($_GET['fix']) && $_GET['fix'] == 'true') {
        $fixed_count = 0;
        foreach ($missing_notifications as $missing) {
            $insert_sql = "INSERT INTO matched_notifications (user_id, listing_id, listing_type, is_read) VALUES (?, ?, ?, 0)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param('iis', $missing['user_id'], $missing['listing_id'], $missing['listing_type']);
            $insert_stmt->execute();
            $insert_stmt->close();
            $fixed_count++;
        }
        $debug_info['fixed_count'] = $fixed_count;
    }
    
    // 成功返回
    sendResponse(true, '调试信息获取成功', $debug_info);
} catch (Exception $e) {
    // 返回错误信息
    sendResponse(false, '调试信息获取失败: ' . $e->getMessage(), [], $debug_info);
}
?> 