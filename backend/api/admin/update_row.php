<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';

require_admin_login(); // 安全检查

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, '无效的请求方法');
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$table_name = $data['table'] ?? '';
$pk_name = $data['pk_name'] ?? '';
$pk_value = $data['pk_value'] ?? '';
$row_data = $data['row_data'] ?? [];

if (empty($table_name) || empty($pk_name) || empty($pk_value) || empty($row_data)) {
    sendResponse(false, '缺少必要的参数。');
    exit;
}

$conn = get_db_connection();

// 安全性检查：白名单验证表名
$db_name = 'lost_and_found';
$allowed_tables = [];
$table_result = $conn->query("SHOW TABLES FROM `$db_name`");
while ($row = $table_result->fetch_row()) {
    $allowed_tables[] = $row[0];
}
if (!in_array($table_name, $allowed_tables)) {
    sendResponse(false, '无效或不允许的表名。');
    exit;
}

try {
    // 安全性检查：白名单验证所有列名
    $allowed_columns = [];
    $column_result = $conn->query("SHOW COLUMNS FROM `$table_name`");
    while ($col = $column_result->fetch_assoc()) {
        $allowed_columns[] = $col['Field'];
    }

    $set_clause_parts = [];
    $bind_values = [];
    $types = '';

    foreach ($row_data as $key => $value) {
        if (!in_array($key, $allowed_columns)) {
            throw new Exception("无效的列名: $key");
        }
        if ($key === $pk_name) continue; // 不更新主键
        $set_clause_parts[] = "`$key` = ?";
        $bind_values[] = $value;
        $types .= 's'; // 全部当作字符串处理以简化
    }

    if (empty($set_clause_parts)) {
        throw new Exception("没有要更新的数据。");
    }

    $sql = "UPDATE `$table_name` SET " . implode(', ', $set_clause_parts) . " WHERE `$pk_name` = ?";
    $bind_values[] = $pk_value;
    $types .= 's';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$bind_values);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendResponse(true, '记录已成功更新。');
        } else {
            sendResponse(true, '数据未发生变化。'); // 没有行受影响也可能是因为提交了相同的数据
        }
    } else {
        throw new Exception("数据库执行更新失败: " . $stmt->error);
    }

} catch (Exception $e) {
    sendResponse(false, '更新操作失败。', ['error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
} 