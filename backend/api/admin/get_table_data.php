<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';

require_admin_login(); // 安全检查

$table_name = $_GET['table'] ?? '';

if (empty($table_name)) {
    sendResponse(false, '未指定表名。');
    exit;
}

$conn = get_db_connection();

// 安全性检查：白名单验证表名，防止SQL注入
// 首先获取所有合法的表名
$db_name = 'lost_and_found'; // 直接使用数据库名
$allowed_tables = [];
$result = $conn->query("SHOW TABLES FROM `$db_name`");
while ($row = $result->fetch_row()) {
    $allowed_tables[] = $row[0];
}

if (!in_array($table_name, $allowed_tables)) {
    sendResponse(false, '无效或不允许的表名。');
    exit;
}

try {
    // 1. 获取主键列名
    $pk_stmt = $conn->prepare("
        SELECT k.COLUMN_NAME
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS AS t
        JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS k
        USING(CONSTRAINT_NAME, TABLE_SCHEMA, TABLE_NAME)
        WHERE t.CONSTRAINT_TYPE = 'PRIMARY KEY'
          AND t.TABLE_SCHEMA = ?
          AND t.TABLE_NAME = ?
    ");
    $pk_stmt->bind_param("ss", $db_name, $table_name);
    $pk_stmt->execute();
    $pk_result = $pk_stmt->get_result()->fetch_assoc();
    $primary_key = $pk_result ? $pk_result['COLUMN_NAME'] : null;
    $pk_stmt->close();

    // 2. 使用反引号安全地包裹表名
    $result = $conn->query("SELECT * FROM `$table_name`");
    $data = $result->fetch_all(MYSQLI_ASSOC);

    sendResponse(true, "成功获取表 {$table_name} 的数据", [
        'data' => $data,
        'primary_key' => $primary_key
    ]);

} catch (Exception $e) {
    sendResponse(false, "获取表 {$table_name} 数据失败", ['error' => $e->getMessage()]);
} finally {
    $conn->close();
} 