<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/smtp_mail.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(false,'Method not allowed.',[],405);
$user=require_user_session($conn);
if ($user['role']!=='customer') respond(false,'Customer account required.',[],403);
$id=(int)$_SESSION['user_id'];
$q=$conn->prepare("SELECT id,username,email,password FROM users WHERE id=? AND role='customer' AND status='active' LIMIT 1");
$q->bind_param('i',$id);$q->execute();$customer=stmt_fetch_assoc_compat($q);$q->close();
if (!$customer) respond(false,'Customer account unavailable.',[],401);
$action=(string)($_POST['action']??'');
if ($action==='profile') respond(true,'Profile loaded.',['username'=>$customer['username'],'email'=>$customer['email']]);
function customer_account_mail($to,$code,$kind){
 send_gmail($to,'BettaTrack Customer Account Verification','<p>Your '.htmlspecialchars($kind,ENT_QUOTES,'UTF-8').' verification code is <strong>'.htmlspecialchars($code,ENT_QUOTES,'UTF-8').'</strong>.</p><p>Valid for 5 minutes. If you did not request this change, ignore this email.</p>');
}
function customer_account_mask_email(string $email): string {
 $parts=explode('@',$email,2);
 if(count($parts)!==2)return 'registered email';
 $local=$parts[0];$visible=mb_substr($local,0,1);
 return $visible.str_repeat('*',min(max(mb_strlen($local)-1,2),8)).'@'.$parts[1];
}
function customer_account_code(){return (string)random_int(100000,999999);}
function customer_account_check_code($pending,$code){
 if(time()>(int)($pending['expires']??0)||(int)($pending['attempts']??0)>5) respond(false,'Code expired or too many attempts. Start again.',[],401);
 if(!preg_match('/^[0-9]{6}$/D',$code)||!password_verify($code,(string)$pending['code_hash'])) respond(false,'Incorrect verification code.',[],401);
}
if ($action==='start'){
 $type=(string)($_POST['type']??'');
 $value=trim((string)($_POST['value']??''));
 $password=(string)($_POST['current_password']??'');
 if(!password_verify($password,$customer['password'])) respond(false,'Current password is incorrect.',[],403);
 if(!filter_var($customer['email'],FILTER_VALIDATE_EMAIL)) respond(false,'A valid registered email is required.',[],400);
 if(time()-(int)($_SESSION['customer_account_last_otp']??0)<60) respond(false,'Wait 60 seconds before requesting another code.',[],429);
 if($type==='username'){
  if(!preg_match('/^[A-Za-z0-9_.-]{3,50}$/D',$value)||$value===$customer['username']) respond(false,'Enter a different username (3–50 letters, numbers, dots, underscores or hyphens).',[],400);
  $check=$conn->prepare('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1');$check->bind_param('si',$value,$id);$check->execute();$taken=stmt_fetch_assoc_compat($check);$check->close();
  if($taken) respond(false,'Username is already taken.',[],409);
  $hash=null;
 }elseif($type==='password'){
  if(strlen($value)<8||strlen($value)>128||password_verify($value,$customer['password'])) respond(false,'Choose a different password containing 8–128 characters.',[],400);
  $hash=password_hash($value,PASSWORD_DEFAULT);$value='';
 }elseif($type==='email'){
  $value=strtolower($value);
  if(!filter_var($value,FILTER_VALIDATE_EMAIL)||strcasecmp($value,$customer['email'])===0) respond(false,'Enter a different valid email address.',[],400);
  $check=$conn->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');$check->bind_param('si',$value,$id);$check->execute();$taken=stmt_fetch_assoc_compat($check);$check->close();
  if($taken) respond(false,'Email is already registered.',[],409);
  $hash=null;
 }else respond(false,'Invalid account change.',[],400);
 $code=customer_account_code();
 $_SESSION['customer_account_pending']=['id'=>$id,'email'=>$customer['email'],'type'=>$type,'value'=>$value,'hash'=>$hash,'original_password'=>$customer['password'],'stage'=>'old','code_hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'attempts'=>0];
 $_SESSION['customer_account_last_otp']=time();
 session_write_close();
 try{customer_account_mail($customer['email'],$code,$type.' change');}
 catch(Throwable $e){error_log('Customer account mail: '.$e->getMessage());respond(false,'Unable to send verification email. No changes saved.',[],503);}
 respond(true,'Verification request accepted by the mail server for '.customer_account_mask_email($customer['email']).'. Check Inbox, Spam, and All Mail. Valid for 5 minutes.',['stage'=>'old','destination'=>customer_account_mask_email($customer['email'])]);
}
if($action==='verify'){
 $pending=$_SESSION['customer_account_pending']??null;
 if(!is_array($pending)||(int)($pending['id']??0)!==$id||!hash_equals((string)($pending['email']??''),(string)$customer['email'])) respond(false,'Start verification again.',[],401);
 $code=(string)($_POST['code']??'');
 $_SESSION['customer_account_pending']['attempts']++;
 customer_account_check_code($_SESSION['customer_account_pending'],$code);
 if($pending['type']==='email'&&$pending['stage']==='old'){
  $newCode=customer_account_code();
  $_SESSION['customer_account_pending']['stage']='new';
  $_SESSION['customer_account_pending']['code_hash']=password_hash($newCode,PASSWORD_DEFAULT);
  $_SESSION['customer_account_pending']['expires']=time()+300;
  $_SESSION['customer_account_pending']['attempts']=0;
  session_write_close();
  try{customer_account_mail($pending['value'],$newCode,'new email address');}
  catch(Throwable $e){error_log('Customer new email verification: '.$e->getMessage());respond(false,'Unable to send code to the new email. No changes saved.',[],503);}
  respond(true,'Verification request accepted for your new email '.customer_account_mask_email($pending['value']).'. Check Inbox and Spam.',['stage'=>'new','done'=>false,'destination'=>customer_account_mask_email($pending['value'])]);
 }
 if(!hash_equals((string)$customer['password'],(string)$pending['original_password'])) respond(false,'Password changed during verification. Start again.',[],409);
 try{
  if($pending['type']==='username'){
   $q=$conn->prepare("UPDATE users SET username=? WHERE id=? AND role='customer' AND status='active' AND username=?");
   $q->bind_param('sis',$pending['value'],$id,$customer['username']);
  }elseif($pending['type']==='password'){
   $q=$conn->prepare("UPDATE users SET password=? WHERE id=? AND role='customer' AND status='active' AND password=?");
   $q->bind_param('sis',$pending['hash'],$id,$customer['password']);
  }elseif($pending['type']==='email'&&$pending['stage']==='new'){
   $q=$conn->prepare("UPDATE users SET email=? WHERE id=? AND role='customer' AND status='active' AND email=?");
   $q->bind_param('sis',$pending['value'],$id,$customer['email']);
  }else respond(false,'Invalid verification state.',[],400);
  $q->execute();$updated=$q->affected_rows;$q->close();
  if($updated!==1) respond(false,'Could not update account. Try again.',[],409);
  unset($_SESSION['customer_account_pending']);
  $_SESSION=[];session_regenerate_id(true);
  respond(true,'Account updated successfully. Please sign in again.',['done'=>true,'requires_login'=>true]);
 }catch(Throwable $e){error_log('Customer account update: '.$e->getMessage());respond(false,'Unable to update account.',[],409);}
}
respond(false,'Unknown action.',[],400);
