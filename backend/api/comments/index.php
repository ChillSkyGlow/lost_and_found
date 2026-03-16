<?php
require_once __DIR__ . '/lost_comments.php';
require_once __DIR__ . '/found_comments.php';

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// 去除查询参数和前缀
$uri = strtok($uri, '?');
$uri = preg_replace('#^/api/comments#', '', $uri);

// 丢失物品评论
if (preg_match('#^/lost_comments/?$#', $uri)) {
    if ($method === 'GET' && isset($_GET['lost_listing_id'])) {
        get_lost_comments($_GET['lost_listing_id']);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        post_lost_comment($data['lost_listing_id'], $data['user_id'], $data['content']);
    }
    exit;
}
// 拾获物品评论
if (preg_match('#^/found_comments/?$#', $uri)) {
    if ($method === 'GET' && isset($_GET['found_listing_id'])) {
        get_found_comments($_GET['found_listing_id']);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        post_found_comment($data['found_listing_id'], $data['user_id'], $data['content']);
    }
    exit;
} 