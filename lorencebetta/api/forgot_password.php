<?php
require_once "db.php";require_once "smtp_mail.php";
$data=json_input();$email=strtolower(trim($data['email']??''));
if(!filter_var($email,FILTER_VALIDATE_EMAIL))respond(false,'Enter a valid email address.',[],400);
$stmt=$conn->prepare("SELECT id,name FROM users WHERE email=? AND role='customer' AND status='active' LIMIT 1");$stmt->bind_param('s',$email);$stmt->execute();$user=stmt_fetch_assoc_compat($stmt);
if(!$user)respond(true,'If the email exists, a reset link has been sent.');
$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$expires=date('Y-m-d H:i:s',time()+3600);
$up=$conn->prepare("UPDATE users SET reset_token_hash=?,reset_expires_at=? WHERE id=?");$up->bind_param('ssi',$hash,$expires,$user['id']);$up->execute();
$link=GMAIL_RESET_BASE_URL.'?reset='.urlencode($token);$safeName=htmlspecialchars($user['name'],ENT_QUOTES,'UTF-8');
$html="<div style='font-family:Arial,sans-serif;line-height:1.6'><h2>Lorence's Betta Fish</h2><p>Hello {$safeName},</p><p>We received a password reset request for your account.</p><p><a href='{$link}' style='display:inline-block;background:#008fbd;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none'>Reset Password</a></p><p>This link expires in 1 hour.</p><p>If you did not request this, you can ignore this email.</p></div>";
try{send_gmail($email,"Lorence's Betta Fish - Password Reset",$html);}catch(Throwable $e){respond(false,$e->getMessage(),[],500);}
respond(true,'If the email exists, a reset link has been sent.');
?>