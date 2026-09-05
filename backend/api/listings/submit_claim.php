<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../config/helpers.php';
require_once '../../config/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, '只允许POST请求');
}

requireLogin();
$lost_user_id = (int)$_SESSION['user_id'];

$found_listing_id = isset($_POST['found_listing_id']) ? (int)$_POST['found_listing_id'] : 0;
$lost_listing_id  = isset($_POST['lost_listing_id'])  ? (int)$_POST['lost_listing_id']  : 0;
$claim_features   = isset($_POST['claim_features'])   ? trim($_POST['claim_features'])   : '';
$lost_story       = isset($_POST['lost_story'])       ? trim($_POST['lost_story'])       : '';
$verification_info = isset($_POST['verification_info']) ? trim($_POST['verification_info']) : null;

if ($found_listing_id <= 0 || $lost_listing_id <= 0 || empty($claim_features) || empty($lost_story)) {
    http_response_code(400);
    sendResponse(false, '参数不完整：found_listing_id、lost_listing_id、claim_features（物品特征）、lost_story（丢失经过）均为必填');
}
if (mb_strlen($claim_features, 'UTF-8') > 500) {
    http_response_code(400);
    sendResponse(false, '物品特征不得超过 500 字');
}
if ($lost_listing_id === $found_listing_id) {
    http_response_code(400);
    sendResponse(false, '失物与招领不能为同一条记录');
}

$conn = get_db_connection();

$conn->begin_transaction();

try {
    $stmt_f = $conn->prepare("SELECT found_listing_id, user_id, item_name, status FROM found_listings WHERE found_listing_id = ? FOR UPDATE");
    $stmt_f->bind_param('i', $found_listing_id);
    $stmt_f->execute();
    $res_f = $stmt_f->get_result();
    if ($res_f->num_rows === 0) {
        throw new Exception('招领信息不存在');
    }
    $found = $res_f->fetch_assoc();
    $stmt_f->close();
    if ($found['status'] !== 'unclaimed') {
        throw new Exception('该招领信息已被认领或已解决，不再接受新申请');
    }
    $found_user_id = (int)$found['user_id'];

    $stmt_l = $conn->prepare("SELECT lost_listing_id, user_id, item_name, status FROM lost_listings WHERE lost_listing_id = ? FOR UPDATE");
    $stmt_l->bind_param('i', $lost_listing_id);
    $stmt_l->execute();
    $res_l = $stmt_l->get_result();
    if ($res_l->num_rows === 0) {
        throw new Exception('失物信息不存在');
    }
    $lost = $res_l->fetch_assoc();
    $stmt_l->close();
    if ((int)$lost['user_id'] !== $lost_user_id) {
        http_response_code(403);
        throw new Exception('仅失物发布者本人可以使用该失物记录提交认领申请');
    }
    if ($lost['status'] !== 'pending') {
        throw new Exception('该失物记录已标记为已解决，不得再提交认领申请');
    }
    if ($found_user_id === $lost_user_id) {
        throw new Exception('您不能认领自己发布的招领信息');
    }

    $vi_for_bind = ($verification_info === '' || $verification_info === null) ? null : $verification_info;

    $dup_stmt = $conn->prepare("SELECT solve_id FROM solve WHERE lost_listing_id = ? AND found_listing_id = ? AND lost_user_id = ? LIMIT 1 FOR UPDATE");
    if (!$dup_stmt) {
        throw new Exception('UNIQUE 冲突检查 prepare 失败: ' . $conn->error);
    }
    $dup_stmt->bind_param('iii', $lost_listing_id, $found_listing_id, $lost_user_id);
    $dup_stmt->execute();
    $dup_res = $dup_stmt->get_result();
    if ($dup_res->num_rows > 0) {
        $dup_stmt->close();
        if (isset($conn) && $conn->connect_errno === 0) {
            try { $conn->rollback(); } catch (Exception $_) {}
        }
        http_response_code(409);
        sendResponse(false, '您已对该失物-招领对提交过认领申请，请勿重复提交');
    }
    $dup_stmt->close();

    $stmt_ins = $conn->prepare("INSERT INTO solve (lost_listing_id, found_listing_id, lost_user_id, found_user_id, claim_features, lost_story, verification_info, status) VALUES (?,?,?,?,?,?,?,'processing')");
    if (!$stmt_ins) {
        throw new Exception('INSERT prepare 失败: ' . $conn->error);
    }
    $stmt_ins->bind_param('iiiisss',
        $lost_listing_id,
        $found_listing_id,
        $lost_user_id,
        $found_user_id,
        $claim_features,
        $lost_story,
        $vi_for_bind
    );
    if (!$stmt_ins->execute()) {
        $c_err = isset($conn->error) ? (string)$conn->error : '';
        $s_err = isset($stmt_ins->error) ? (string)$stmt_ins->error : '';
        $raw_msg = (!empty($s_err) ? $s_err : $c_err);
        $is_dup = (stripos($raw_msg, 'Duplicate entry') !== false) || (stripos($raw_msg, '重复') !== false) || (stripos($raw_msg, 'UNIQUE') !== false);
        if ($is_dup) {
            if (isset($conn) && $conn->connect_errno === 0) {
                try { $conn->rollback(); } catch (Exception $_) {}
            }
            http_response_code(409);
            sendResponse(false, '您已对该失物-招领对提交过认领申请，请勿重复提交');
        }
        throw new Exception('认领申请写入失败: ' . (empty($raw_msg) ? '未知数据库错误' : $raw_msg));
    }
    $solve_id = (int)$stmt_ins->insert_id;
    $stmt_ins->close();

    $listing_type_val = 'found';
    $source_listing_type_val = 'lost';
    $check_exist = $conn->prepare("SELECT id FROM matched_notifications WHERE user_id = ? AND listing_id = ? AND listing_type = ? AND source_listing_id = ? AND source_listing_type = ? AND type = 'claim' LIMIT 1");
    $check_exist->bind_param('iisis', $found_user_id, $found_listing_id, $listing_type_val, $lost_listing_id, $source_listing_type_val);
    $check_exist->execute();
    $res_chk = $check_exist->get_result();
    $notify_inserted = false;
    if ($res_chk->num_rows === 0) {
        $check_exist->close();
        $ins_notify = $conn->prepare("INSERT INTO matched_notifications (user_id, listing_id, listing_type, source_listing_id, source_listing_type, type, is_read) VALUES (?,?,?,?,?,'claim',0)");
        if ($ins_notify) {
            $ins_notify->bind_param('iisis', $found_user_id, $found_listing_id, $listing_type_val, $lost_listing_id, $source_listing_type_val);
            if ($ins_notify->execute()) {
                $notify_inserted = true;
            }
            $ins_notify->close();
        }
    } else {
        $check_exist->close();
    }

    $conn->commit();

    try {
        $stmt_em = $conn->prepare("SELECT u.email, u.username FROM users u WHERE u.user_id = ? LIMIT 1");
        $stmt_em->bind_param('i', $found_user_id);
        $stmt_em->execute();
        $res_em = $stmt_em->get_result();
        $owner = $res_em->fetch_assoc();
        $stmt_em->close();
        if ($owner) {
            $feats = htmlspecialchars(mb_substr($claim_features, 0, 120, 'UTF-8'));
            $subject = "您发布的招领「{$found['item_name']}」收到新的认领申请";
            $body = "尊敬的 {$owner['username']}：<br><br>您发布的招领信息 <strong>「{$found['item_name']}」</strong> 收到失主 <strong>{$_SESSION['username']}</strong> 提交的认领申请。<br><br><strong>物品特征（摘要）：</strong><br>{$feats}<br><br>请登录系统查看完整申请内容，并在「物品详情」中进行审核。";
            send_notification_email($owner['email'], $subject, $body);
        }
    } catch (Exception $e_mail) {
        error_log('submit_claim 邮件发送失败: ' . $e_mail->getMessage());
    }

    sendResponse(true, '认领申请已成功提交，等待招领发布者审核', [
        'solve_id' => $solve_id,
        'status'   => 'processing',
        'notify_inserted' => $notify_inserted,
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
        try { $conn->rollback(); } catch (Exception $_) {}
    }
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    sendResponse(false, $e->getMessage());
} finally {
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->close();
    }
}
