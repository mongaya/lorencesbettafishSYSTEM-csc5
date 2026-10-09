<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo 'DIAGNOSTIC VERSION: 2' . PHP_EOL;
echo 'PHP version: ' . PHP_VERSION . PHP_EOL;
$root = dirname(__DIR__);
echo 'Mailer file: ' . (is_file(__DIR__ . '/google_otp_mailer.php') ? 'FOUND' : 'MISSING') . PHP_EOL;
echo 'PHPMailer autoload: ' . (is_file($root . '/vendor/autoload.php') ? 'FOUND' : 'MISSING') . PHP_EOL;
echo 'Mail config: ' . (is_file($root . '/mail_secure/google_mail_config.php') ? 'FOUND' : 'MISSING') . PHP_EOL;
if (is_file(__DIR__ . '/google_otp_mailer.php')) {
    require_once __DIR__ . '/google_otp_mailer.php';
    echo 'OTP mailer function: ' . (function_exists('send_google_login_otp') ? 'LOADED' : 'MISSING') . PHP_EOL;
}
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
    echo 'PHPMailer class: ' . (class_exists(\PHPMailer\PHPMailer\PHPMailer::class) ? 'LOADED' : 'MISSING') . PHP_EOL;
}
