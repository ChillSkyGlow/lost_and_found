<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    sendResponse(false, '只允许GET请求');
}

requireLogin();
$found_user_id = (int)$_SESSION['user_id'];

$found_listing_id = isset($_GET['found_listing_id']) ? (int)$_GET['found_listing_id'] : 0;

$conn = get_db_connection();

$sql = "SELECT
            s.solve_id,
            s.lost_listing_id,
            s.found_listing_id,
            s.lost_user_id,
            s.found_user_id,
            s.claim_features,
            s.lost_story,
            s.verification_info,
            s.status AS solve_status,
            s.created_at,
            s.updated_at,
            lu.username  AS lost_username,
            lu.email     AS lost_email,
            ll.item_name AS lost_title,
            ll.description AS lost_description,
            ll.image_file_path AS lost_image,
            fl.item_name AS found_title,
            fl.status    AS found_status
        FROM solve s
        JOIN users lu        ON s.lost_user_id = lu.user_id
        JOIN lost_listings ll ON s.lost_listing_id = ll.lost_listing_id
        JOIN found_listings fl ON s.found_listing_id = fl.found_listing_id
        WHERE s.found_user_id = ?
          AND fl.user_id     = ?";
$params = [$found_user_id, $found_user_id];
$types = 'ii';

if ($found_listing_id > 0) {
    $sql .= " AND s.found_listing_id = ?";
    $params[] = $found_listing_id;
    $types .= 'i';
}
$sql .= " ORDER BY s.created_at DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    sendResponse(false, '查询准备失败: ' . $conn->error);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'solve_id'          => (int)$row['solve_id'],
        'lost_listing_id'   => (int)$row['lost_listing_id'],
        'found_listing_id'  => (int)$row['found_listing_id'],
        'lost_user_id'      => (int)$row['lost_user_id'],
        'found_user_id'     => (int)$row['found_user_id'],
        'claim_features'    => $row['claim_features'],
        'lost_story'        => $row['lost_story'],
        'verification_info' => $row['verification_info'],
        'status'            => $row['solve_status'],
        'created_at'        => $row['created_at'],
        'updated_at'        => $row['updated_at'],
        'lost_username'     => $row['lost_username'],
        'lost_email'        => $row['lost_email'],
        'lost_title'        => $row['lost_title'],
        'lost_description'  => $row['lost_description'],
        'lost_image'        => $row['lost_image'],
        'found_title'       => $row['found_title'],
        'found_status'      => $row['found_status'],
    ];
}
$stmt->close();
$conn->close();

sendResponse(true, '查询成功', [
    'total' => count($data),
    'items' => $data,
]);
