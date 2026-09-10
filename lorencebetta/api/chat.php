<?php
require_once "db.php";
if($_SERVER['REQUEST_METHOD']==='GET'){
 $role=trim($_GET['role']??'');$username=trim($_GET['username']??'');
 if($role==='admin'){$r=$conn->query("SELECT id,username,sender_role,message,DATE_FORMAT(created_at,'%m/%d/%Y %h:%i %p') AS created_at FROM chat_messages ORDER BY id ASC");}
 else {if($username==='')respond(false,"Username is required.",[],400);$st=$conn->prepare("SELECT id,username,sender_role,message,DATE_FORMAT(created_at,'%m/%d/%Y %h:%i %p') AS created_at FROM chat_messages WHERE username=? ORDER BY id ASC");$st->bind_param('s',$username);$st->execute();$r=stmt_fetch_all_assoc_compat($st);}
 $messages=[];if(is_array($r)){$messages=$r;}else{while($row=$r->fetch_assoc())$messages[]=$row;}respond(true,"",["messages"=>$messages]);
}
$data=json_input();$username=trim($data['username']??'');$sender=trim($data['senderRole']??'');$message=trim($data['message']??'');
if($username===''||!in_array($sender,['customer','admin'],true)||$message==='')respond(false,'Message is required.',[],400);
$u=$conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");$u->bind_param('s',$username);$u->execute();$user=stmt_fetch_assoc_compat($u);if(!$user)respond(false,'Customer not found.',[],404);
$st=$conn->prepare("INSERT INTO chat_messages(user_id,username,sender_role,message,created_at) VALUES(?,?,?,?,NOW())");$st->bind_param('isss',$user['id'],$username,$sender,$message);$st->execute();respond(true,'Message sent.');
?>