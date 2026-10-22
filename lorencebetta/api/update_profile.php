<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST') respond(false,'Method not allowed.',[],405);
$user=require_user_session($conn);
if($user['role']!=='customer') respond(false,'Customer login required.',[],403);
$data=json_input();
$name=trim((string)($data['name']??''));
if($name===''||mb_strlen($name)>150) respond(false,'Please provide a valid name (up to 150 characters).',[],400);
$id=(int)$_SESSION['user_id'];
$stmt=$conn->prepare("UPDATE users SET name=? WHERE id=? AND role='customer' AND status='active'");
$stmt->bind_param('si',$name,$id);
$stmt->execute();
$stmt->close();
respond(true,'Profile updated.');
?>