<?php
/**
 * Gmail OTP delivery helper. Not a directly callable API endpoint.
 * Deploy only after installing PHPMailer and configuring credentials outside /htdocs.
 */
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function send_google_login_otp(string $recipient, string $code): void
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{6}$/D', $code)) {
        throw new InvalidArgumentException('Invalid verification request.');
    }

    // In InfinityFree, /htdocs/api -> parent of /htdocs is the hosting account root.
    // The credentials file MUST NOT be uploaded to /htdocs or committed to GitHub.
    $configPath = dirname(__DIR__, 2) . '/private/google_mail_config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('Mail configuration is not installed.');
    }
    $config = require $configPath;
    if (!is_array($config) || empty($config['email']) || empty($config['app_password'])) {
        throw new RuntimeException('Mail configuration is incomplete.');
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('PHPMailer dependency is not installed.');
    }
    require_once $autoload;

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $config['email'];
    $mail->Password = $config['app_password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['email'], "Lorence's Betta Fish");
    $mail->addAddress($recipient);
    $mail->isHTML(false);
    $mail->Subject = "Lorence's Betta Fish verification code";
    $mail->Body = "Your verification code is: {$code}\n\nThis code expires in 5 minutes. If you did not request it, ignore this email.";
    $mail->send();
}
