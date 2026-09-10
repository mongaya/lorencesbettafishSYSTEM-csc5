<?php
require_once "db.php";
$data=json_input(); $name=trim($data["name"]??""); $username=trim($data["username"]??""); $email=trim($data["email"]??""); $password=$data["password"]??"";
if($name===""||$username===""||$email===""||$password==="") respond(false,"Please complete all fields.",[],400);
if(strtolower($username)==="admin") respond(false,"This username is reserved.",[],400);
if(!filter_var($email,FILTER_VALIDATE_EMAIL)) respond(false,"Please enter a valid existing email address.",[],400);
if(strlen($password)<6) respond(false,"Password must be at least 6 characters.",[],400);
$check=$conn->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1"); $check->bind_param("ss",$username,$email); $check->execute(); if(stmt_has_rows_compat($check)) respond(false,"Username or email already exists.",[],409);
$hash=password_hash($password,PASSWORD_DEFAULT); $stmt=$conn->prepare("INSERT INTO users(name,username,email,password,role,status,created_at) VALUES(?,?,?,?, 'customer','active',NOW())"); $stmt->bind_param("ssss",$name,$username,$email,$hash); $stmt->execute(); respond(true,"Account created successfully.");
?>