<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
require_once __DIR__.'/google_otp_mailer.php';
const GOOGLE_CLIENT_ID='821976549681-969e5u5de30gu6823tuq8s8jl33dvimn.apps.googleusercontent.com';
if(session_status()!==PHP_SESSION_ACTIVE){
 ini_set('session.use_strict_mode','1');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
function google_fail(string $message,int $status=400):void{respond(false,$message,[],$status);}
function google_lookup(mysqli $db,string $sql,string $type,string $value):?array{
 $s=$db->prepare($sql);if(!$s)throw new RuntimeException('Query unavailable');
 $s->bind_param($type,$value);$s->execute();$r=stmt_fetch_assoc_compat($s);$s->close();return $r;
}
function google_token_identity(string $token):array{
 if($token===''||strlen($token)>8192||!function_exists('curl_init'))google_fail('Google verification unavailable.',503);
 $c=curl_init('https://oauth2.googleapis.com/tokeninfo');
 curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['id_token'=>$token]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
 $body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 $d=is_string($body)?json_decode($body,true):null;
 if($status!==200||!is_array($d)||!hash_equals(GOOGLE_CLIENT_ID,(string)($d['aud']??''))||!in_array($d['iss']??'',['accounts.google.com','https://accounts.google.com'],true)|| (int)($d['exp']??0)<=time()||!in_array(strtolower((string)($d['email_verified']??'')),['true','1'],true)||!filter_var($d['email']??'',FILTER_VALIDATE_EMAIL)||empty($d['sub']))google_fail('Google verification failed.',401);
 return ['sub'=>(string)$d['sub'],'email'=>strtolower((string)$d['email']),'name'=>trim((string)($d['name']??'Google Customer'))];
}
function google_otp_send(mysqli $db,array $identity,string $purpose):void{
 $code=(string)random_int(100000,999999);$hash=password_hash($code,PASSWORD_DEFAULT);
 $s=$db->prepare('INSERT INTO google_login_otps (google_sub,email,code_hash,expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))');
 if(!$s)throw new RuntimeException('OTP storage unavailable');
 $s->bind_param('sss',$identity['sub'],$identity['email'],$hash);$s->execute();$id=(int)$db->insert_id;$s->close();
 $_SESSION['google_pending']=['otp_id'=>$id,'sub'=>$identity['sub'],'email'=>$identity['email'],'purpose'=>$purpose];
 $_SESSION['google_otp_last_start']=time();
 session_write_close();
 try{send_google_login_otp($identity['email'],$code);}
 catch(Throwable $e){
  $s=$db->prepare('UPDATE google_login_otps SET consumed_at=NOW() WHERE id=?');
  if($s){$s->bind_param('i',$id);$s->execute();$s->close();}
  error_log('Google OTP send: '.$e->getMessage());google_fail('Unable to send verification email.',503);
 }
 respond(true,'Verification code sent. It expires in 5 minutes.');
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')google_fail('Method not allowed.',405);
$data=json_input();$action=(string)($data['action']??'');
try{
 if($action==='start_signup'||$action==='start_login'){
  $identity=google_token_identity((string)($data['credential']??''));
  $existing=google_lookup($conn,'SELECT id,role,status FROM users WHERE google_sub=? LIMIT 1','s',$identity['sub']);
  if($action==='start_signup'){
   if($existing)google_fail('Google account already registered.',409);
   if(google_lookup($conn,'SELECT id FROM users WHERE email=? LIMIT 1','s',$identity['email']))google_fail('Email already registered.',409);
   $_SESSION['google_signup_identity']=$identity;
   $_SESSION['google_signup_started_at']=time();
   unset($_SESSION['google_pending'],$_SESSION['google_signup_details']);
   respond(true,'Google email confirmed. Complete your account.',['email'=>$identity['email']]);
  }
  if(!$existing||$existing['role']!=='customer'||$existing['status']!=='active')google_fail('No active Google customer account found.',404);
  $user=google_lookup($conn,"SELECT id,name,username,email,phone,address,role,status FROM users WHERE google_sub=? AND role='customer' AND status='active' LIMIT 1",'s',$identity['sub']);
  if(!$user)google_fail('Customer account unavailable.',401);
  unset($_SESSION['google_pending']);
  session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['role']='customer';session_write_close();
  respond(true,'Google sign-in successful.',['user'=>['id'=>(int)$user['id'],'type'=>'customer','username'=>$user['username'],'name'=>$user['name'],'email'=>$user['email'],'phone'=>$user['phone']??'','address'=>$user['address']??'']]);
 }
 if($action==='complete_signup'){
  $identity=$_SESSION['google_signup_identity']??null;
  if(!is_array($identity)||time()-(int)($_SESSION['google_signup_started_at']??0)>600)google_fail('Choose your Google account again.',401);
  $name=trim((string)($data['name']??''));$username=trim((string)($data['username']??''));$password=(string)($data['password']??'');
  if(mb_strlen($name)<2||mb_strlen($name)>100||!preg_match('/^[A-Za-z0-9_.-]{3,50}$/D',$username)||strlen($password)<8||strlen($password)>128)google_fail('Invalid signup details.');
  if(google_lookup($conn,'SELECT id FROM users WHERE email=? LIMIT 1','s',$identity['email'])||google_lookup($conn,'SELECT id FROM users WHERE username=? LIMIT 1','s',$username))google_fail('Email or username already registered.',409);
  $hash=password_hash($password,PASSWORD_DEFAULT);
  try{
   $s=$conn->prepare("INSERT INTO users (name,username,email,password,google_sub,role,status,created_at) VALUES (?,?,?,?,?,'customer','active',NOW())");
   $s->bind_param('sssss',$name,$username,$identity['email'],$hash,$identity['sub']);$s->execute();$s->close();
  }catch(Throwable $e){error_log('Google signup insert: '.$e->getMessage());google_fail('Account could not be created.',409);}
  $user=google_lookup($conn,"SELECT id,name,username,email,phone,address FROM users WHERE google_sub=? LIMIT 1",'s',$identity['sub']);
  unset($_SESSION['google_signup_identity'],$_SESSION['google_signup_started_at'],$_SESSION['google_pending'],$_SESSION['google_signup_details']);
  session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['role']='customer';session_write_close();
  respond(true,'Google account created successfully.',['user'=>['id'=>(int)$user['id'],'type'=>'customer','username'=>$user['username'],'name'=>$user['name'],'email'=>$user['email'],'phone'=>$user['phone']??'','address'=>$user['address']??'']]);
 }
 if($action==='verify'){
  $pending=$_SESSION['google_pending']??null;
  if(!is_array($pending))google_fail('Verification session expired.',401);
  $code=(string)($data['code']??'');if(!preg_match('/^[0-9]{6}$/D',$code))google_fail('Enter a valid 6-digit code.');
  $id=(int)$pending['otp_id'];
  $s=$conn->prepare('SELECT id,code_hash,attempts,consumed_at,(expires_at>NOW()) AS is_valid FROM google_login_otps WHERE id=? AND google_sub=? LIMIT 1');
  $s->bind_param('is',$id,$pending['sub']);$s->execute();$otp=stmt_fetch_assoc_compat($s);$s->close();
  if(!$otp||$otp['consumed_at']!==null||(int)$otp['attempts']>=5||(int)$otp['is_valid']!==1){unset($_SESSION['google_pending']);google_fail('Code expired or attempts exceeded.',401);}
  $s=$conn->prepare('UPDATE google_login_otps SET attempts=attempts+1 WHERE id=? AND consumed_at IS NULL AND attempts<5');
  $s->bind_param('i',$id);$s->execute();$changed=$s->affected_rows;$s->close();
  if($changed!==1)google_fail('Too many attempts.',429);
  if(!password_verify($code,$otp['code_hash']))google_fail('Incorrect verification code.',401);
  $conn->begin_transaction();
  try{
   $s=$conn->prepare('UPDATE google_login_otps SET consumed_at=NOW() WHERE id=? AND consumed_at IS NULL AND expires_at>NOW()');
   $s->bind_param('i',$id);$s->execute();$changed=$s->affected_rows;$s->close();
   if($changed!==1)throw new RuntimeException('OTP already used');
   if($pending['purpose']==='signup'){
    $details=$_SESSION['google_signup_details']??null;$identity=$_SESSION['google_signup_identity']??null;
    if(!is_array($details)||!is_array($identity)||$identity['sub']!==$pending['sub'])throw new RuntimeException('Signup details missing');
    if(google_lookup($conn,'SELECT id FROM users WHERE email=? LIMIT 1','s',$pending['email'])||google_lookup($conn,'SELECT id FROM users WHERE username=? LIMIT 1','s',$details['username']))throw new RuntimeException('Account already registered');
    $s=$conn->prepare("INSERT INTO users (name,username,email,password,google_sub,role,status,created_at) VALUES (?,?,?,?,?,'customer','active',NOW())");
    $s->bind_param('sssss',$details['name'],$details['username'],$pending['email'],$details['password_hash'],$pending['sub']);
    $s->execute();$s->close();
   }
   $user=google_lookup($conn,'SELECT id,name,username,email,phone,address,role,status FROM users WHERE google_sub=? LIMIT 1','s',$pending['sub']);
   if(!$user||$user['role']!=='customer'||$user['status']!=='active')throw new RuntimeException('Account unavailable');
   $conn->commit();
  }catch(Throwable $e){$conn->rollback();error_log('Google verification: '.$e->getMessage());google_fail('Could not complete verification.',409);}
  unset($_SESSION['google_pending'],$_SESSION['google_signup_identity'],$_SESSION['google_signup_details']);
  session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['role']='customer';session_write_close();
  respond(true,'Google verification successful.',['user'=>['id'=>(int)$user['id'],'type'=>'customer','username'=>$user['username'],'name'=>$user['name'],'email'=>$user['email'],'phone'=>$user['phone']??'','address'=>$user['address']??'']]);
 }
 google_fail('Unknown action.');
}catch(Throwable $e){error_log('Google authentication: '.$e->getMessage());google_fail('Google sign-in temporarily unavailable.',503);}
