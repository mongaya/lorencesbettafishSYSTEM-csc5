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
$data=json_input();$action=(string)($data['action']??'start');
if($action==='start'){
 $name=trim((string)($data['name']??''));$username=trim((string)($data['username']??''));
 $email=strtolower(trim((string)($data['email']??'')));$password=(string)($data['password']??'');
 if(mb_strlen($name)<2||mb_strlen($name)>150||!preg_match('/^[A-Za-z0-9_.-]{3,50}$/D',$username)||strtolower($username)==='admin'||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8||strlen($password)>128)respond(false,'Enter a valid name, username, email and password (8–128 characters).',[],400);
 $check=$conn->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
 $check->bind_param('ss',$username,$email);$check->execute();$taken=stmt_has_rows_compat($check);$check->close();
 if($taken)respond(false,'Username or email already exists.',[],409);
 if(time()-(int)($_SESSION['signup_otp_last_sent']??0)<60)respond(false,'Wait 60 seconds before sending another code.',[],429);
 $code=(string)random_int(100000,999999);
 $_SESSION['manual_signup_pending']=['name'=>$name,'username'=>$username,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'code_hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'attempts'=>0];
 $_SESSION['signup_otp_last_sent']=time();
 session_write_close();
 try{send_gmail($email,"Lorence's Betta Fish - Registration Verification",'<p>Your registration verification code is <strong>'.htmlspecialchars($code,ENT_QUOTES,'UTF-8').'</strong>.</p><p>The code expires in 5 minutes. Ignore this message if you did not request an account.</p>');}
 catch(Throwable $e){error_log('Signup verification mail: '.$e->getMessage());respond(false,'Unable to send the verification code. Please try again.',[],503);}
 respond(true,'Verification code sent. Check your email. It expires in 5 minutes.');
}
if($action==='verify'){
 $pending=$_SESSION['manual_signup_pending']??null;
 if(!is_array($pending))respond(false,'Verification expired. Start signup again.',[],401);
 $code=(string)($data['code']??'');
 $_SESSION['manual_signup_pending']['attempts']=(int)($pending['attempts']??0)+1;
 if(time()>(int)$pending['expires']||(int)$pending['attempts']>=5){unset($_SESSION['manual_signup_pending']);respond(false,'Verification expired or too many attempts.',[],401);}
 if(!preg_match('/^[0-9]{6}$/D',$code)||!password_verify($code,$pending['code_hash']))respond(false,'Incorrect verification code.',[],401);
 $check=$conn->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
 $check->bind_param('ss',$pending['username'],$pending['email']);$check->execute();$taken=stmt_has_rows_compat($check);$check->close();
 if($taken)respond(false,'Username or email already registered.',[],409);
 try{
  $q=$conn->prepare("INSERT INTO users(name,username,email,password,role,status,created_at) VALUES(?,?,?,?,'customer','active',NOW())");
  $q->bind_param('ssss',$pending['name'],$pending['username'],$pending['email'],$pending['password_hash']);
  $q->execute();$q->close();
 }catch(Throwable $e){error_log('Signup insert: '.$e->getMessage());respond(false,'Unable to create account.',[],409);}
 unset($_SESSION['manual_signup_pending']);
 respond(true,'Email verified. Account created successfully.');
}
respond(false,'Invalid signup action.',[],400);
?>