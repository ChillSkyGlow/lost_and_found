<?php
// 开启错误显示
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, '未登录', []);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = get_db_connection();
$messages = [];
$debug_info = [];

try {
    $base_select_fields = "m.id,
        m.listing_id,
        COALESCE(m.type, 'match') as type,
        m.listing_type,
        m.created_at as time,
        CASE
            WHEN m.listing_type = 'lost' THEN l.item_name
            WHEN m.listing_type = 'found' THEN f.item_name
            ELSE 'Unknown'
        END as matched_item_name,
        m.source_listing_id,
        m.source_listing_type,
        CASE
            WHEN m.source_listing_type = 'lost' THEN sl.item_name
            WHEN m.source_listing_type = 'found' THEN sf.item_name
            ELSE 'Unknown'
        END as source_item_name";
    $from_join = "FROM matched_notifications m
        LEFT JOIN lost_listings l ON m.listing_type = 'lost' AND m.listing_id = l.lost_listing_id
        LEFT JOIN found_listings f ON m.listing_type = 'found' AND m.listing_id = f.found_listing_id
        LEFT JOIN lost_listings sl ON m.source_listing_type = 'lost' AND m.source_listing_id = sl.lost_listing_id
        LEFT JOIN found_listings sf ON m.source_listing_type = 'found' AND m.source_listing_id = sf.found_listing_id
        WHERE m.user_id = ? AND m.is_read = 0";

    $sql_match = "SELECT {$base_select_fields} {$from_join} AND COALESCE(m.type, 'match') = 'match' ORDER BY m.created_at DESC";
    $stmt = $conn->prepare($sql_match);
    if (!$stmt) {
        throw new Exception("准备matched_notifications match查询失败: " . $conn->error);
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $debug_info['match_query'] = $sql_match;
    $debug_info['match_count'] = $result->num_rows;
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'type' => 'match',
            'listing_id' => $row['listing_id'],
            'listing_type' => $row['listing_type'],
            'time' => $row['time'],
            'item_name' => $row['matched_item_name'],
            'source_listing_id' => $row['source_listing_id'],
            'source_listing_type' => $row['source_listing_type'],
            'source_item_name' => $row['source_item_name']
        ];
    }
    $stmt->close();

    $sql_claim = "SELECT {$base_select_fields} {$from_join} AND COALESCE(m.type, 'match') = 'claim' ORDER BY m.created_at DESC";
    $stmt_c = $conn->prepare($sql_claim);
    if ($stmt_c) {
        $stmt_c->bind_param('i', $user_id);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();
        $debug_info['claim_count'] = $res_c->num_rows;
        while ($row = $res_c->fetch_assoc()) {
            $messages[] = [
                'id' => $row['id'],
                'type' => 'claim',
                'listing_id' => $row['listing_id'],
                'listing_type' => $row['listing_type'],
                'time' => $row['time'],
                'item_name' => $row['matched_item_name'],
                'source_listing_id' => $row['source_listing_id'],
                'source_listing_type' => $row['source_listing_type'],
                'source_item_name' => $row['source_item_name']
            ];
        }
        $stmt_c->close();
    }

    $sql_claim_approved = "SELECT {$base_select_fields} {$from_join} AND COALESCE(m.type, 'match') = 'claim_approved' ORDER BY m.created_at DESC";
    $stmt_ca = $conn->prepare($sql_claim_approved);
    if ($stmt_ca) {
        $stmt_ca->bind_param('i', $user_id);
        $stmt_ca->execute();
        $res_ca = $stmt_ca->get_result();
        $debug_info['claim_approved_count'] = $res_ca->num_rows;
        while ($row = $res_ca->fetch_assoc()) {
            $messages[] = [
                'id' => $row['id'],
                'type' => 'claim_approved',
                'listing_id' => $row['listing_id'],
                'listing_type' => $row['listing_type'],
                'time' => $row['time'],
                'item_name' => $row['matched_item_name'],
                'source_listing_id' => $row['source_listing_id'],
                'source_listing_type' => $row['source_listing_type'],
                'source_item_name' => $row['source_item_name']
            ];
        }
        $stmt_ca->close();
    }

    $sql_claim_rejected = "SELECT {$base_select_fields} {$from_join} AND COALESCE(m.type, 'match') = 'claim_rejected' ORDER BY m.created_at DESC";
    $stmt_cr = $conn->prepare($sql_claim_rejected);
    if ($stmt_cr) {
        $stmt_cr->bind_param('i', $user_id);
        $stmt_cr->execute();
        $res_cr = $stmt_cr->get_result();
        $debug_info['claim_rejected_count'] = $res_cr->num_rows;
        while ($row = $res_cr->fetch_assoc()) {
            $messages[] = [
                'id' => $row['id'],
                'type' => 'claim_rejected',
                'listing_id' => $row['listing_id'],
                'listing_type' => $row['listing_type'],
                'time' => $row['time'],
                'item_name' => $row['matched_item_name'],
                'source_listing_id' => $row['source_listing_id'],
                'source_listing_type' => $row['source_listing_type'],
                'source_item_name' => $row['source_item_name']
            ];
        }
        $stmt_cr->close();
    }

    // 2.1 失物评论
    $sql_lost_comment = "SELECT 
        c.comment_id as id,
        c.lost_listing_id as listing_id, 
        'lost' as listing_type, 
        c.created_at as time,
        l.item_name
        FROM lost_comment c
        JOIN lost_listings l ON c.lost_listing_id = l.lost_listing_id
        WHERE l.user_id = ? AND c.user_id != ? AND c.is_read = 0
        ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($sql_lost_comment);
    if (!$stmt) {
        throw new Exception("准备lost_comment查询失败: " . $conn->error);
    }
    
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 记录失物评论查询结果
    $debug_info['lost_comment_count'] = $result->num_rows;
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'type' => 'comment',
            'listing_id' => $row['listing_id'],
            'listing_type' => $row['listing_type'],
            'time' => $row['time'],
            'item_name' => $row['item_name']
        ];
    }
    $stmt->close();

    // 2.2 招领评论
    $sql_found_comment = "SELECT 
        c.comment_id as id,
        c.found_listing_id as listing_id, 
        'found' as listing_type, 
        c.created_at as time,
        l.item_name
        FROM found_comment c
        JOIN found_listings l ON c.found_listing_id = l.found_listing_id
        WHERE l.user_id = ? AND c.user_id != ? AND c.is_read = 0
        ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($sql_found_comment);
    if (!$stmt) {
        throw new Exception("准备found_comment查询失败: " . $conn->error);
    }
    
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 记录招领评论查询结果
    $debug_info['found_comment_count'] = $result->num_rows;
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'type' => 'comment',
            'listing_id' => $row['listing_id'],
            'listing_type' => $row['listing_type'],
            'time' => $row['time'],
            'item_name' => $row['item_name']
        ];
    }
    $stmt->close();

    // 格式化时间
    foreach ($messages as &$message) {
        if (isset($message['time'])) {
            $timestamp = strtotime($message['time']);
            $message['time'] = date('Y-m-d H:i', $timestamp);
        }
    }

    // 添加调试信息
    $debug_info['total_messages'] = count($messages);
    $debug_info['user_id'] = $user_id;
    
    // 检查matched_notifications表中是否有数据
    $check_sql = "SELECT COUNT(*) as count FROM matched_notifications";
    $check_result = $conn->query($check_sql);
    $row = $check_result->fetch_assoc();
    $debug_info['total_notifications'] = $row['count'];
    
    // 成功返回
    sendResponse(true, '消息获取成功', $messages, $debug_info);
} catch (Exception $e) {
    // 返回错误信息
    sendResponse(false, '消息获取失败: ' . $e->getMessage(), [], $debug_info);
} 