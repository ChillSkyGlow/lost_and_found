<?php
require_once __DIR__ . '/user_info.php';

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$uri = strtok($uri, '?');
$uri = preg_replace('#^/api/users#', '', $uri);

if (preg_match('#^/user_info/?(\\d+)?$#', $uri, $matches)) {
    $user_id = $matches[1] ?? null;
    if ($method === 'GET' && $user_id) {
        get_user_info($user_id);
    } elseif ($method === 'PUT' && $user_id) {
        $data = json_decode(file_get_contents('php://input'), true);
        update_user_info($user_id, $data);
    }
    exit;
}

// 这里可以根据 $_SERVER['REQUEST_METHOD'] 和 $_SERVER['REQUEST_URI'] 进一步细分用户操作 