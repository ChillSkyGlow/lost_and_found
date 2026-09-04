<?php
// backend/config/database.php

// 引入 Composer 自动加载文件并加载 .env 环境变量
require_once __DIR__ . '/../../vendor/autoload.php';

if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->safeLoad();
}

// 设置错误报告 - 在生产环境中关闭显示错误
error_reporting(E_ERROR); // 只记录致命错误
ini_set('display_errors', 0); // 关闭错误显示

// JSON响应函数，避免循环引用问题
if (!function_exists('db_send_json_response')) {
    function db_send_json_response($success, $message = '', $data = []) {
        // 清除之前的输出
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json');
        
        if (!$success && http_response_code() === 200) {
            http_response_code(500);
        }

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }
}

/**
 * 获取数据库连接
 * @return mysqli 数据库连接对象
 */
function get_db_connection() {
    $host = $_ENV['DB_HOST'] ?? 'localhost';// 替换为你的数据库主机
    $db_name = $_ENV['DB_NAME'] ?? 'DATABASE_NAME'; // 替换为你的数据库名称
    $username = $_ENV['DB_USER'] ?? 'DATABASE_USERNAME'; // 替换为你的数据库用户名
    $password = $_ENV['DB_PASS'] ?? 'DATABASE_PASSWORD'; // 替换为你的数据库密码
    
    try {
        // 创建新的数据库连接
        $conn = new mysqli($host, $username, $password, $db_name);
        
        // 检查连接错误
        if ($conn->connect_error) {
            // 如果连接失败，则发送一个500服务器错误响应
            db_send_json_response(false, '数据库连接失败');
        }
        
        // 设置字符集
        if (!$conn->set_charset("utf8mb4")) {
            // 如果设置字符集失败
            db_send_json_response(false, '设置数据库字符集失败');
        }

        return $conn;
    } catch (Exception $e) {
        // 捕获任何其他异常
        db_send_json_response(false, '数据库错误: ' . $e->getMessage());
    }
}

/**
 * 确保matched_notifications表存在
 */
function ensure_matched_notifications_table_exists() {
    $conn = get_db_connection();
    
    $table_name = 'matched_notifications';
    
    // 检查表是否存在
    $result = $conn->query("SHOW TABLES LIKE '{$table_name}'");
    if ($result->num_rows == 0) {
        // 表不存在，创建它
        $sql = "CREATE TABLE `{$table_name}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `listing_id` INT NOT NULL,
            `listing_type` ENUM('lost', 'found') NOT NULL,
            `source_listing_id` INT,
            `source_listing_type` ENUM('lost', 'found'),
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id)
        )";
        
        if (!$conn->query($sql)) {
            error_log("创建{$table_name}表失败: " . $conn->error);
        }
    }

    // 检查并添加新字段
    $columns_to_add = [
        'source_listing_id' => 'INT',
        'source_listing_type' => "ENUM('lost', 'found')"
    ];
    
    foreach ($columns_to_add as $column => $type) {
        $check_column_sql = "SHOW COLUMNS FROM `{$table_name}` LIKE '{$column}'";
        $column_result = $conn->query($check_column_sql);
        if ($column_result->num_rows == 0) {
            // 字段不存在，添加它
            $add_column_sql = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column}` {$type} AFTER `listing_type`";
            if (!$conn->query($add_column_sql)) {
                error_log("向{$table_name}表添加字段{$column}失败: " . $conn->error);
            }
        }
    }
    
    $conn->close();
}

// 确保表存在
ensure_matched_notifications_table_exists();

?>