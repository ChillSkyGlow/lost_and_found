<?php
error_reporting(0);
ini_set('display_errors', 0);

function exception_handler($exception) {
    header('Content-Type: application/json');
    if (http_response_code() === 200) http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器异常: ' . $exception->getMessage()]);
    exit;
}
function error_handler($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    if (http_response_code() === 200) http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $errstr]);
    exit;
}
set_exception_handler('exception_handler');
set_error_handler('error_handler');
while (ob_get_level()) { ob_end_clean(); }
ob_start();

try {
    require_once '../../config/database.php';
    require_once '../../config/helpers.php';
    require_once '../../config/mailer.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        sendResponse(false, '只允许POST请求');
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    requireLogin();
    $current_user_id = (int)$_SESSION['user_id'];
    $current_username = $_SESSION['username'] ?? '招领发布者';

    $solve_id       = isset($_POST['solve_id']) ? (int)$_POST['solve_id'] : 0;
    $decision       = isset($_POST['decision'])  ? trim((string)$_POST['decision']) : '';

    if ($solve_id <= 0 || !in_array($decision, ['approve','reject'], true)) {
        http_response_code(400);
        sendResponse(false, '参数错误：solve_id 必须为正整数，decision 必须为 approve 或 reject');
    }

    $conn = get_db_connection();

    $conn->begin_transaction();

    $sql_sel = "SELECT s.*,
                       f.user_id   AS found_user_id, f.item_name AS found_title, f.status AS found_status,
                       l.user_id   AS lost_user_id,  l.item_name AS lost_title,  l.status  AS lost_status
                  FROM solve s
             LEFT JOIN found_listings f ON f.found_listing_id = s.found_listing_id
             LEFT JOIN lost_listings  l ON l.lost_listing_id  = s.lost_listing_id
                 WHERE s.solve_id = ? FOR UPDATE";
    $stmt = $conn->prepare($sql_sel);
    if (!$stmt) {
        throw new Exception('solve 查询准备失败: ' . $conn->error);
    }
    $stmt->bind_param('i', $solve_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row || !$row['found_user_id']) {
        $conn->rollback();
        http_response_code(404);
        sendResponse(false, '认领申请记录不存在');
    }

    if ((int)$row['found_user_id'] !== $current_user_id) {
        $conn->rollback();
        http_response_code(403);
        sendResponse(false, '仅招领信息发布者本人可以审核该认领申请');
    }

    if ($row['status'] === 'completed') {
        $conn->rollback();
        http_response_code(409);
        sendResponse(false, '该认领申请已审核通过，不可再操作');
    }
    if ($row['status'] === 'rejected') {
        $conn->rollback();
        http_response_code(409);
        sendResponse(false, '该认领申请已被拒绝，不可再操作');
    }
    if ($row['status'] !== 'processing') {
        $conn->rollback();
        http_response_code(409);
        sendResponse(false, '该认领申请当前状态不允许审核');
    }

    if ($decision === 'approve') {
        if ($row['found_status'] !== 'unclaimed') {
            $conn->rollback();
            http_response_code(409);
            sendResponse(false, '该招领信息状态不是 unclaimed，无法通过申请通过');
        }
        if ($row['lost_status'] !== 'pending') {
            $conn->rollback();
            http_response_code(409);
            sendResponse(false, '该失物信息状态不是 pending，无法申请通过');
        }

        $up_found = $conn->prepare("UPDATE found_listings SET status = 'claimed'   WHERE found_listing_id = ? AND user_id = ?");
        $up_found->bind_param('ii', $row['found_listing_id'], $current_user_id);
        if (!$up_found->execute()) { throw new Exception('招领状态更新失败: ' . $up_found->error); }
        $up_found->close();

        $up_lost = $conn->prepare("UPDATE lost_listings  SET status = 'solved'    WHERE lost_listing_id  = ? AND user_id = ?");
        $up_lost->bind_param('ii', $row['lost_listing_id'], $row['lost_user_id']);
        if (!$up_lost->execute()) { throw new Exception('失物状态更新失败: ' . $up_lost->error); }
        $up_lost->close();

        $new_status = 'completed';
        $up_solve = $conn->prepare("UPDATE solve SET status = ?, solved_at = CURRENT_TIMESTAMP WHERE solve_id = ?");
        $up_solve->bind_param('si', $new_status, $solve_id);
        if (!$up_solve->execute()) { throw new Exception('solve 状态更新失败: ' . $up_solve->error); }
        $up_solve->close();

        $notify_type = 'claim_approved';
        $notify_msg_title = $row['found_title'];
        $notify_msg_body = "招领「{$row['found_title']}」的认领申请已审核通过";
    } else {
        $new_status = 'rejected';
        $up_solve = $conn->prepare("UPDATE solve SET status = ? WHERE solve_id = ?");
        $up_solve->bind_param('si', $new_status, $solve_id);
        if (!$up_solve->execute()) { throw new Exception('solve 状态更新失败: ' . $up_solve->error); }
        $up_solve->close();
        $notify_type = 'claim_rejected';
        $notify_msg_title = $row['found_title'];
        $notify_msg_body = "招领「{$row['found_title']}」的认领申请未通过审核";
    }

    $check_notify = $conn->prepare("SELECT id FROM matched_notifications
                                     WHERE user_id = ? AND listing_id = ? AND listing_type = 'found'
                                       AND source_listing_type = 'lost' AND source_listing_id = ? AND type = ? LIMIT 1");
    $check_notify->bind_param('iiis', $row['lost_user_id'], $row['found_listing_id'], $row['lost_listing_id'], $notify_type);
    $check_notify->execute();
    $cres = $check_notify->get_result();
    $notify_inserted = false;
    if ($cres->num_rows === 0) {
        $check_notify->close();
        $ins_notify = $conn->prepare("INSERT INTO matched_notifications
            (user_id, listing_id, listing_type, source_listing_type, source_listing_id, type, is_read)
            VALUES (?, ?, 'found', 'lost', ?, ?, 0)");
        $ins_notify->bind_param('iiis', $row['lost_user_id'], $row['found_listing_id'], $row['lost_listing_id'], $notify_type);
        if ($ins_notify->execute()) { $notify_inserted = true; }
        $ins_notify->close();
    } else {
        $check_notify->close();
    }

    $conn->commit();

    try {
        $stmt_em = $conn->prepare("SELECT u.email, u.username FROM users u WHERE u.user_id = ? LIMIT 1");
        $stmt_em->bind_param('i', $row['lost_user_id']);
        $stmt_em->execute();
        $owner = $stmt_em->get_result()->fetch_assoc();
        $stmt_em->close();
        if ($owner) {
            if ($decision === 'approve') {
                $subject = "恭喜：您对招领「{$row['found_title']}」的认领申请已通过审核";
                $body    = "尊敬的 {$owner['username']}：<br><br>您提交的认领申请（对应招领：<strong>「{$row['found_title']}」</strong>，关联失物：<strong>「{$row['lost_title']}」</strong>）已被招领发布者 <strong>{$current_username}</strong> 审核通过。<br><br>请及时联系领取您的失物；如需要，请通过个人中心查看招领发布者联系方式协商具体领取方式。";
            } else {
                $subject = "您对招领「{$row['found_title']}」的认领申请未通过审核";
                $body    = "尊敬的 {$owner['username']}：<br><br>很遗憾通知您，您提交的认领申请（对应招领：<strong>「{$row['found_title']}」</strong>，关联失物：<strong>「{$row['lost_title']}」</strong>）经发布者审核后未被拒绝。<br><br>您可以重新发布失物信息、或提交更详细的认领信息后再次尝试。";
            }
            send_notification_email($owner['email'], $subject, $body);
        }
    } catch (Exception $e_mail) {
        error_log('review_claim 邮件发送失败: ' . $e_mail->getMessage());
    }

    sendResponse(true, $notify_msg_body, [
        'solve_id'         => $solve_id,
        'status'           => $new_status,
        'decision'         => $decision,
        'notify_inserted'  => $notify_inserted,
        'found_listing_id' => (int)$row['found_listing_id'],
        'lost_listing_id'  => (int)$row['lost_listing_id'],
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn && !$conn->connect_errno) {
        try { $conn->rollback(); } catch (Exception $_) { }
    }
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    sendResponse(false, $e->getMessage());
} finally {
    if (isset($conn) && $conn && !$conn->connect_errno) {
        $conn->close();
    }
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}
