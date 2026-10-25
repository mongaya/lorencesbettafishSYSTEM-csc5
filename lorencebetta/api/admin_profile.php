<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/smtp_mail.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(false,'Method not allowed.',[],405);
require_admin_session($conn);
$adminId=(int)$_SESSION['user_id'];
$action=(string)($_POST['action'] ?? '');
$st=$conn->prepare("SELECT id,name,username,email,password FROM users WHERE id=? AND role='admin' AND status='active' LIMIT 1");
$st->bind_param('i',$adminId);$st->execute();$admin=stmt_fetch_assoc_compat($st);$st->close();
if(!$admin) respond(false,'Admin account unavailable.',[],401);
if($action==='profile') respond(true,'Profile loaded.',['user'=>['name'=>$admin['name'],'username'=>$admin['username'],'email'=>$admin['email']]]);
if($action==='start'){
 $password=(string)($_POST['current_password']??'');$type=(string)($_POST['type']??'');$value=(string)($_POST['value']??'');
 if(!password_verify($password,$admin['password']))respond(false,'Current password is incorrect.',[],403);
 if(!filter_var($admin['email'],FILTER_VALIDATE_EMAIL))respond(false,'A valid registered email is required.',[],400);
 if($type==='username'){
  if(!preg_match('/^[A-Za-z0-9_.-]{3,50}$/D',$value))respond(false,'Username must be 3–50 characters using letters, numbers, dots, underscores or hyphens.',[],400);
  if($value===$admin['username'])respond(false,'Enter a different username.',[],400);
  $q=$conn->prepare('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1');$q->bind_param('si',$value,$adminId);$q->execute();$duplicate=stmt_fetch_assoc_compat($q);$q->close();if($duplicate)respond(false,'Username is already taken.',[],409);
  $newHash=null;
 }elseif($type==='password'){
  if(strlen($value)<8||strlen($value)>10||!preg_match('/[0-9]/',$value)||!preg_match('/[^A-Za-z0-9]/',$value))respond(false,'New password must contain 8–10 characters, one number and one special character.',[],400);
  if(password_verify($value,$admin['password']))respond(false,'Choose a different password.',[],400);
  $newHash=password_hash($value,PASSWORD_DEFAULT);$value='';
 }else respond(false,'Invalid account change.',[],400);
 if(time()-(int)($_SESSION['admin_profile_last_otp']??0)<60)respond(false,'Wait 60 seconds before requesting another code.',[],429);
 $code=(string)random_int(100000,999999);
 $_SESSION['admin_profile_pending']=['admin_id'=>$adminId,'email'=>$admin['email'],'type'=>$type,'value'=>$value,'password_hash'=>$newHash,'code_hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'attempts'=>0];
 $_SESSION['admin_profile_last_otp']=time();
 session_write_close();
 try{send_gmail($admin['email'],"BettaTrack Admin Profile Verification",'<p>Your Admin Profile verification code is <strong>'.htmlspecialchars($code,ENT_QUOTES,'UTF-8').'</strong>.</p><p>Expires in 5 minutes. If you did not request this change, ignore this email.</p>');}
 catch(Throwable $e){error_log('Admin profile OTP mail failure: '.$e->getMessage());respond(false,'Unable to send verification email. No changes were saved.',[],503);}
 respond(true,'Verification code sent to your registered email. It expires in 5 minutes.');
}
if($action==='verify'){
 $p=$_SESSION['admin_profile_pending']??null;
 if(!is_array($p)||(int)($p['admin_id']??0)!==$adminId||!hash_equals((string)($p['email']??''),(string)$admin['email']))respond(false,'Start verification again.',[],401);
 if(time()>(int)$p['expires']||(int)$p['attempts']>=5){unset($_SESSION['admin_profile_pending']);respond(false,'Code expired or attempts exceeded.',[],401);}
 $code=(string)($_POST['code']??'');$_SESSION['admin_profile_pending']['attempts']++;
 if(!preg_match('/^[0-9]{6}$/D',$code)||!password_verify($code,$p['code_hash']))respond(false,'Incorrect verification code.',[],401);
 unset($_SESSION['admin_profile_pending']);
 try{
  if($p['type']==='username'){
   $q=$conn->prepare('UPDATE users SET username=? WHERE id=? AND role=\'admin\' AND status=\'active\'');$q->bind_param('si',$p['value'],$adminId);
  }elseif($p['type']==='password'){
   $q=$conn->prepare('UPDATE users SET password=? WHERE id=? AND role=\'admin\' AND status=\'active\' AND password=?');$q->bind_param('sis',$p['password_hash'],$adminId,$admin['password']);
  }else respond(false,'Invalid verification.',[],400);
  $q->execute();$updated=$q->affected_rows;$q->close();
  if($updated!==1)respond(false,'Unable to update account. Please try again.',[],409);
  if($p['type']==='password'){$_SESSION=[];session_regenerate_id(true);}
  respond(true,$p['type']==='password'?'Password changed. Please log in again.':'Username changed successfully.',['requires_login'=>$p['type']==='password','username'=>$p['type']==='username'?$p['value']:$admin['username']]);
 }catch(Throwable $e){error_log('Admin profile update: '.$e->getMessage());respond(false,'Could not save changes. Please try again.',[],409);}
}
respond(false,'Unknown action.',[],400);
