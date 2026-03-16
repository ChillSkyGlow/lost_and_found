<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../config/helpers.php';

require_admin_login(); // 安全检查

$conn = get_db_connection();
$db_name = 'lost_and_found'; // 直接使用数据库名

try {
    $result = $conn->query("SHOW TABLES FROM `$db_name`");

    $tables = [];
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    sendResponse(true, '成功获取所有表名', $tables);

} catch (Exception $e) {
    sendResponse(false, '获取表名失败', ['error' => $e->getMessage()]);
} finally {
    $conn->close();
} 