<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/session.php';

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$uri = strtok($uri, '?');
$uri = preg_replace('#^/api/auth#', '', $uri);

if ($uri === '/session' && $method === 'GET') {
    // session.php 已自动输出
    exit;
}

if ($uri === '/login' && $method === 'POST') {
    require_once __DIR__ . '/login.php';
    exit;
}

// 这里可以根据 $_SERVER['REQUEST_METHOD'] 和 $_SERVER['REQUEST_URI'] 进一步细分 login、logout、register、check_session 等 