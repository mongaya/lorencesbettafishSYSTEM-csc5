<?php
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;
function send_google_login_otp(string $recipient,string $code): void {
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{6}$/D',$code)){
        throw new InvalidArgumentException('Invalid verification request.');
    }
    $configPath=dirname(__DIR__).'/mail_secure/google_mail_config.php';
    if(!is_file($configPath)) throw new RuntimeException('Mail configuration is not installed.');
    $config=require $configPath;
    if(!is_array($config) || empty($config['email']) || empty($config['app_password']))
        throw new RuntimeException('Mail configuration is incomplete.');
    $autoload=dirname(__DIR__).'/vendor/autoload.php';
    if(!is_file($autoload)) throw new RuntimeException('PHPMailer dependency is not installed.');
    require_once $autoload;
    $mail=new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host='smtp.gmail.com';
    $mail->SMTPAuth=true;
    $mail->Username=$config['email'];
    $mail->Password=$config['app_password'];
    $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port=587;
    $mail->Timeout=10;
    $mail->Timelimit=10;
    $mail->CharSet='UTF-8';
    $mail->setFrom($config['email'],"Lorence's Betta Fish");
    $mail->addAddress($recipient);
    $mail->isHTML(false);
    $mail->Subject="Lorence's Betta Fish verification code";
    $mail->Body="Your verification code is: {$code}\n\nThis code expires in 5 minutes.\nIf you did not request it, ignore this email.";
    $mail->send();
}
