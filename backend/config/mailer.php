<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 确保正确引入了 PHPMailer 的自动加载文件
// 根据您的项目结构，这个路径可能需要调整
require_once __DIR__ . '/../../vendor/autoload.php';

if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->safeLoad();
}

function send_notification_email($to_email, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // 服务器配置
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.exmple.com';  // 您的 SMTP 服务器地址
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? 'YOUR_USERNAME@exmple.com';  // 您的 SMTP 用户名，下面还有一处要修改
        $mail->Password   = $_ENV['SMTP_PASS'] ?? 'YOUR_PASSWORD';     // 您的 SMTP 密码,绝对不是明文密码，而是邮件服务商提供的授权码
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;// 使用 SSL 加密，请根据实际进行配置
        $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 465);// SMTP 端口号，通常是 465 或 587，请根据实际进行配置
        $mail->CharSet    = 'UTF-8';

        // 发件人
        $mail->setFrom($_ENV['SMTP_USER'] ?? 'YOUR_USERNAME@exmple.com', '失物招领平台');//您的 SMTP 用户名

        // 收件人
        $mail->addAddress($to_email);

        // 内容
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // 非 HTML 邮件客户端的纯文本内容

        $mail->send();
        return true;
    } catch (Exception $e) {
        // 记录错误，但不要暴露给前端用户
        error_log("邮件发送失败: {$mail->ErrorInfo}");
        return false;
    }
} 