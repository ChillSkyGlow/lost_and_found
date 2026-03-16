<?php
// 主路由分发
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$uri = strtok($uri, '?');

if (preg_match('#^/api/listings#', $uri)) {
    require_once __DIR__ . '/listings/index.php';
    exit;
}
if (preg_match('#^/api/users#', $uri)) {
    require_once __DIR__ . '/users/index.php';
    exit;
}
if (preg_match('#^/api/comments#', $uri)) {
    require_once __DIR__ . '/comments/index.php';
    exit;
}
if (preg_match('#^/api/auth#', $uri)) {
    require_once __DIR__ . '/auth/index.php';
    exit;
}
// 404 fallback
http_response_code(404);
echo json_encode(['error' => 'Not Found']); 