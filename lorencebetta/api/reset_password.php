<?php
require_once "db.php";
$data=json_input();$token=trim($data['token']??'');$password=$data['password']??'';
if($token===''||strlen($password)<6)respond(false,'Invalid reset request.',[],400);
$hash=hash('sha256',$token);$st=$conn->prepare("SELECT id FROM users WHERE reset_token_hash=? AND reset_expires_at>NOW() AND status='active' LIMIT 1");$st->bind_param('s',$hash);$st->execute();$user=stmt_fetch_assoc_compat($st);if(!$user)respond(false,'Reset link is invalid or expired.',[],400);
$newHash=password_hash($password,PASSWORD_DEFAULT);$up=$conn->prepare("UPDATE users SET password=?,reset_token_hash=NULL,reset_expires_at=NULL WHERE id=?");$up->bind_param('si',$newHash,$user['id']);$up->execute();respond(true,'Password updated successfully.');
?>