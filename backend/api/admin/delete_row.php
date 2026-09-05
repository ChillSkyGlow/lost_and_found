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

if (empty($table_name) || empty($pk_name) || empty($pk_value)) {
    sendResponse(false, '缺少必要的参数（表名、主键名或主键值）。');
    exit;
}

$conn = get_db_connection();

// 安全性检查：白名单验证表名
$db_name = 'lost_and_found';
$allowed_tables = [];
$result = $conn->query("SHOW TABLES FROM `$db_name`");
while ($row = $result->fetch_row()) {
    $allowed_tables[] = $row[0];
}
if (!in_array($table_name, $allowed_tables)) {
    sendResponse(false, '无效或不允许的表名。');
    exit;
}

// 校验 pk_name 是否为该表真实主键（ INFORMATION_SCHEMA ）
$db_name = 'lost_and_found';
$pk_check_sql = "SELECT k.COLUMN_NAME
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS AS t
    JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS k
    USING(CONSTRAINT_NAME, TABLE_SCHEMA, TABLE_NAME)
    WHERE t.CONSTRAINT_TYPE = 'PRIMARY KEY'
      AND t.TABLE_SCHEMA = ?
      AND t.TABLE_NAME = ?
      AND k.COLUMN_NAME = ?
    LIMIT 1";
$pk_stmt = $conn->prepare($pk_check_sql);
if (!$pk_stmt) {
    throw new Exception("主键校验查询准备失败: " . $conn->error);
}
$pk_stmt->bind_param('sss', $db_name, $table_name, $pk_name);
$pk_stmt->execute();
$pk_stmt->store_result();
if ($pk_stmt->num_rows === 0) {
    $pk_stmt->close();
    sendResponse(false, '提供的主键名与表实际主键不匹配，禁止删除。');
    exit;
}
$pk_stmt->close();

// 保护措施：禁止删除 users 表中当前登录管理员自身的账号
if ($table_name === 'users' && isset($_SESSION['admin_user_id'])) {
    if ((string)$pk_value === (string)$_SESSION['admin_user_id']) {
        sendResponse(false, '禁止删除当前登录的管理员账号。');
        exit;
    }
}

try {
    // 使用预处理语句防止SQL注入
    $stmt = $conn->prepare("DELETE FROM `$table_name` WHERE `$pk_name` = ?");
    $stmt->bind_param("s", $pk_value); // 绑定为主键值，类型设为字符串通用性更强
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendResponse(true, '记录已成功删除。');
        } else {
            sendResponse(false, '未找到匹配的记录，或记录已被删除。');
        }
    } else {
        throw new Exception("数据库执行删除失败: " . $stmt->error);
    }

} catch (Exception $e) {
    sendResponse(false, '删除操作失败。', ['error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
} 