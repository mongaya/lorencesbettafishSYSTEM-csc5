<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/smtp_mail.php';
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')respond(false,'Method not allowed.',[],405);
if(session_status()!==PHP_SESSION_ACTIVE){
 ini_set('session.use_strict_mode','1');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
try{
 $conn->query("CREATE TABLE IF NOT EXISTS password_reset_otps (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 code_hash VARCHAR(255) NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 expires_at DATETIME NOT NULL,
 consumed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_reset_user (user_id,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}catch(Throwable $e){error_log('Password reset OTP storage: '.$e->getMessage());respond(false,'Password recovery is temporarily unavailable.',[],503);}
$data=json_input();$action=(string)($data['action']??'request');$email=strtolower(trim((string)($data['email']??'')));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)respond(false,'Enter a valid email address.',[],400);
if($action==='request'){
 if(time()-(int)($_SESSION['reset_otp_last_request']??0)<60)respond(false,'Please wait a minute before requesting another code.',[],429);
 $_SESSION['reset_otp_last_request']=time();
 $q=$conn->prepare("SELECT id,name FROM users WHERE email=? AND role='customer' AND status='active' LIMIT 1");
 $q->bind_param('s',$email);$q->execute();$user=stmt_fetch_assoc_compat($q);$q->close();
 if($user){
  $uid=(int)$user['id'];
  $q=$conn->prepare("SELECT id FROM password_reset_otps WHERE user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 60 SECOND) LIMIT 1");
  $q->bind_param('i',$uid);$q->execute();$recent=stmt_fetch_assoc_compat($q);$q->close();
  if(!$recent){
   $code=(string)random_int(100000,999999);$hash=password_hash($code,PASSWORD_DEFAULT);
   $q=$conn->prepare('UPDATE password_reset_otps SET consumed_at=NOW() WHERE user_id=? AND consumed_at IS NULL');
   $q->bind_param('i',$uid);$q->execute();$q->close();
   $q=$conn->prepare('INSERT INTO password_reset_otps(user_id,code_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))');
   $q->bind_param('is',$uid,$hash);$q->execute();$otpId=(int)$conn->insert_id;$q->close();
   $body='<p>Your Lorence\'s Betta Fish password reset code is <strong>'.htmlspecialchars($code,ENT_QUOTES,'UTF-8').'</strong>.</p><p>This code expires in 5 minutes. If you did not request this, ignore this email.</p>';
   session_write_close();
   try{send_gmail($email,"Lorence's Betta Fish - Password Reset Code",$body);}
   catch(Throwable $e){
    error_log('Password reset mail: '.$e->getMessage());
    $q=$conn->prepare('UPDATE password_reset_otps SET consumed_at=NOW() WHERE id=?');
    $q->bind_param('i',$otpId);$q->execute();$q->close();
    respond(false,'Unable to send the verification code right now. Please try again later.',[],503);
   }
  }
 }
 respond(true,'If an active customer account exists, a verification code has been sent.');
}
if($action==='verify'){
 $code=trim((string)($data['code']??''));
 if(!preg_match('/^[0-9]{6}$/D',$code))respond(false,'Enter the 6-digit verification code.',[],400);
 $q=$conn->prepare("SELECT id FROM users WHERE email=? AND role='customer' AND status='active' LIMIT 1");
 $q->bind_param('s',$email);$q->execute();$user=stmt_fetch_assoc_compat($q);$q->close();
 if(!$user)respond(false,'Invalid or expired verification code.',[],400);
 $uid=(int)$user['id'];
 $q=$conn->prepare("SELECT id,code_hash FROM password_reset_otps WHERE user_id=? AND consumed_at IS NULL AND expires_at>NOW() AND attempts<5 ORDER BY id DESC LIMIT 1");
 $q->bind_param('i',$uid);$q->execute();$otp=stmt_fetch_assoc_compat($q);$q->close();
 if(!$otp)respond(false,'Invalid or expired verification code.',[],400);
 $otpId=(int)$otp['id'];
 $q=$conn->prepare('UPDATE password_reset_otps SET attempts=attempts+1 WHERE id=? AND consumed_at IS NULL AND attempts<5 AND expires_at>NOW()');
 $q->bind_param('i',$otpId);$q->execute();$changed=$q->affected_rows;$q->close();
 if($changed!==1||!password_verify($code,$otp['code_hash']))respond(false,'Invalid or expired verification code.',[],400);
 $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
 $conn->begin_transaction();
 try{
  $q=$conn->prepare('UPDATE password_reset_otps SET consumed_at=NOW() WHERE id=? AND consumed_at IS NULL AND expires_at>NOW()');
  $q->bind_param('i',$otpId);$q->execute();$ok=$q->affected_rows;$q->close();
  if($ok!==1)throw new RuntimeException('Verification used');
  $q=$conn->prepare('UPDATE users SET reset_token_hash=?,reset_expires_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=?');
  $q->bind_param('si',$hash,$uid);$q->execute();$q->close();
  $conn->commit();
 }catch(Throwable $e){$conn->rollback();respond(false,'Unable to verify this code.',[],409);}
 respond(true,'Email verified. Create a new password.',['resetToken'=>$token]);
}
respond(false,'Invalid request.',[],400);
?>