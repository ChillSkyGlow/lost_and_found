<?php
// backend/config/helpers.php

// 全局辅助函数：统一的JSON响应格式
function sendResponse($success, $message = '', $data = [], $debug = null) {
    // 在脚本退出前，确保没有其他输出
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    // 如果是失败响应且HTTP状态码仍为200 (OK), 则改为 400 (Bad Request)
    if (!$success && http_response_code() === 200) {
        http_response_code(400);
    }

    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    
    // 如果提供了调试信息，则添加到响应中
    if ($debug !== null) {
        $response['debug'] = $debug;
    }

    echo json_encode($response);
    exit();
}

// 辅助函数：要求用户必须登录
function requireLogin() {
    // 确保会话已经启动
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401); // Unauthorized
        sendResponse(false, '请先登录');
    }
}

// 辅助函数：检查用户是否登录
function isLoggedIn() {
    // 确保会话已经启动
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

// 辅助函数：获取当前登录用户的ID
function getCurrentUserId() {
    // 假定会话已启动且用户已登录
    // 在调用此函数前，应先调用 isLoggedIn() 或 requireLogin()
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    return null;
}

// 辅助函数：验证资源所有权
function validateOwnership($conn, $table, $column, $id, $user_id_column = 'user_id') {
    $stmt = $conn->prepare("SELECT $user_id_column FROM $table WHERE $column = ?");
    if (!$stmt) {
        http_response_code(500);
        sendResponse(false, '数据库查询准备失败');
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row[$user_id_column] != $_SESSION['user_id']) {
            http_response_code(403); // Forbidden
            sendResponse(false, '您没有权限执行此操作');
        }
        // 有权限，什么都不做
    } else {
        http_response_code(404); // Not Found
        sendResponse(false, '请求的资源不存在');
    }
    $stmt->close();
}

function require_admin_login() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        sendResponse(false, '管理员未登录或会话已过期。');
        exit;
    }
} 