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
$lost_user_id = (int)$_SESSION['user_id'];

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
            fu.username  AS found_username,
            fu.email     AS found_email,
            fl.item_name AS found_title,
            fl.description AS found_description,
            fl.image_file_path AS found_image,
            ll.item_name AS lost_title,
            ll.status    AS lost_status
        FROM solve s
        JOIN users fu        ON s.found_user_id = fu.user_id
        JOIN found_listings fl ON s.found_listing_id = fl.found_listing_id
        JOIN lost_listings ll  ON s.lost_listing_id = ll.lost_listing_id
        WHERE s.lost_user_id = ?
          AND ll.user_id     = ?
        ORDER BY s.created_at DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    sendResponse(false, '查询准备失败: ' . $conn->error);
}
$stmt->bind_param('ii', $lost_user_id, $lost_user_id);
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
        'found_username'    => $row['found_username'],
        'found_email'       => $row['found_email'],
        'found_title'       => $row['found_title'],
        'found_description' => $row['found_description'],
        'found_image'       => $row['found_image'],
        'lost_title'        => $row['lost_title'],
        'lost_status'       => $row['lost_status'],
    ];
}
$stmt->close();
$conn->close();

sendResponse(true, '查询成功', [
    'total' => count($data),
    'items' => $data,
]);
