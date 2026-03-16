<?php
// backend/api/listings/get_listings.php

// **修正: 只报告致命错误，忽略警告，防止污染JSON输出**
error_reporting(E_ERROR | E_PARSE);

// 引入数据库配置文件
require_once '../../config/database.php';

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed');
}

// --- 1. 获取并清理输入参数 ---
// **修正: 接收'filter'和'search'参数以匹配前端**
$filter = $_GET['filter'] ?? 'all'; // 'all', 'lost', 'found'
$search = $_GET['search'] ?? '';

// **新增: 分页参数**
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 9; // 每页显示9个
$offset = ($page - 1) * $limit;

// **新增: 接收并验证地理位置参数**
$lat = isset($_GET['lat']) ? filter_var($_GET['lat'], FILTER_VALIDATE_FLOAT) : null;
$lon = isset($_GET['lon']) ? filter_var($_GET['lon'], FILTER_VALIDATE_FLOAT) : null;
$radius = isset($_GET['radius']) ? filter_var($_GET['radius'], FILTER_VALIDATE_FLOAT) : null;
$sort_key = $_GET['sort'] ?? 'time_desc'; // 接收排序参数
$date = $_GET['date'] ?? ''; // 接收日期参数

$conn = get_db_connection();
$params = [];
$types = '';

// --- 2. 构建查询 ---
// **修正: 为 'lost' 和 'found' 表分别构建查询，避免表别名混淆**
$lost_query_part = "
    SELECT 
        'lost' as listing_type, 
        l.lost_listing_id as listing_id, 
        l.item_name,
        l.image_file_path,
        u.username,
        l.description,
        l.location_details,
        l.event_time,
        l.created_at
    FROM lost_listings l 
    JOIN users u ON l.user_id = u.user_id 
    WHERE l.status = 'pending'
";

$found_query_part = "
    SELECT 
        'found' as listing_type, 
        f.found_listing_id as listing_id,
        f.item_name,
        f.image_file_path,
        u.username,
        f.description,
        f.location_details,
        f.event_time,
        f.created_at
    FROM found_listings f 
    JOIN users u ON f.user_id = u.user_id 
    WHERE f.status = 'unclaimed'
";

$where_clauses = [];
if (!empty($search)) {
    // **修正: 使用 search 参数进行搜索**
    $search_term = '%' . $search . '%';
    $where_clauses[] = "(item_name LIKE ? OR description LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// **新增: 如果提供了日期，则添加到WHERE子句**
if (!empty($date)) {
    $where_clauses[] = "DATE(event_time) = ?";
    $params[] = $date;
    $types .= 's';
}

// **新增: 如果提供了地理位置参数，则添加到WHERE子句**
if ($lat !== null && $lon !== null && $radius !== null) {
    // Haversine formula to calculate distance in km
    $haversine_sql = "
        (6371 * acos(
            cos(radians(?)) * cos(radians(SUBSTRING_INDEX(location_coordinates, ',', -1))) *
            cos(radians(SUBSTRING_INDEX(location_coordinates, ',', 1)) - radians(?)) +
            sin(radians(?)) * sin(radians(SUBSTRING_INDEX(location_coordinates, ',', -1)))
        ))
    ";
    $where_clauses[] = "(location_coordinates IS NOT NULL AND location_coordinates != '' AND {$haversine_sql} <= ?)";
    $params[] = $lat;
    $params[] = $lon;
    $params[] = $lat;
    $params[] = $radius;
    $types .= 'dddd';
}

$where_sql = !empty($where_clauses) ? ' AND ' . implode(' AND ', $where_clauses) : '';

$data_query = "";
$count_query = "";
$final_params_data = $params;
$final_types_data = $types;
$final_params_count = $params;
$final_types_count = $types;

// **修正: 简化switch逻辑**
switch ($filter) {
    case 'lost':
        $data_query = $lost_query_part . $where_sql;
        $count_query = "SELECT count(*) FROM lost_listings l WHERE l.status = 'pending'" . $where_sql;
        break;
    case 'found':
        $data_query = $found_query_part . $where_sql;
        $count_query = "SELECT count(*) FROM found_listings f WHERE f.status = 'unclaimed'" . $where_sql;
        break;
    default: // 'all'
        $data_query = "($lost_query_part $where_sql) UNION ALL ($found_query_part $where_sql)";
        $count_query = "
            SELECT SUM(total) FROM (
                SELECT count(*) as total FROM lost_listings l WHERE l.status = 'pending' $where_sql
                UNION ALL
                SELECT count(*) as total FROM found_listings f WHERE f.status = 'unclaimed' $where_sql
            ) as total_counts";
        
        if (!empty($params)) {
            $final_params_data = array_merge($params, $params);
            $final_types_data = $types . $types;
            $final_params_count = array_merge($params, $params);
            $final_types_count = $types . $types;
        }
        break;
}

try {
    // --- 3. 获取总记录数 ---
    $stmt_count = $conn->prepare($count_query);
    if (!empty($final_params_count)) {
        $stmt_count->bind_param($final_types_count, ...$final_params_count);
    }
    $stmt_count->execute();
    $total_records = $stmt_count->get_result()->fetch_row()[0] ?? 0;
    $total_pages = ceil($total_records / $limit);
    $stmt_count->close();

    // --- 4. 获取当前页数据 ---
    
    // **新增: 根据sort参数构建ORDER BY子句**
    $order_by_map = [
        'time_desc' => 'ORDER BY created_at DESC',
        // 'time_asc' => 'ORDER BY created_at ASC', // 未来可以轻松扩展
        // 'name_asc' => 'ORDER BY item_name ASC'
    ];
    $order_by_sql = $order_by_map[$sort_key] ?? $order_by_map['time_desc']; // 白名单验证，默认为按时间降序

    $data_query .= " {$order_by_sql} LIMIT ? OFFSET ?";
    $final_params_data[] = $limit;
    $final_params_data[] = $offset;
    $final_types_data .= 'ii';

    $stmt_data = $conn->prepare($data_query);
    if (!$stmt_data) {
        throw new Exception("数据库查询准备失败: " . $conn->error);
    }
    
    if (!empty($final_params_data)) {
        $stmt_data->bind_param($final_types_data, ...$final_params_data);
    }
    
    $stmt_data->execute();
    $result = $stmt_data->get_result();
    
    $listings = [];
    while ($row = $result->fetch_assoc()) {
        $listings[] = $row;
    }
    
    $stmt_data->close();
    $conn->close();
    
    // **修正: 返回包含分页信息的完整对象**
    header('Content-Type: application/json');
    echo json_encode([
        'listings' => $listings,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $total_pages,
            'totalRecords' => (int)$total_records
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    // 在生产环境中，应该记录错误而不是直接显示
    sendResponse(false, '获取物品列表时发生错误: ' . $e->getMessage());
}

?>
