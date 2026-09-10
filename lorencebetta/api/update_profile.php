<?php
require_once "db.php";
$data=json_input(); $username=trim($data["username"]??""); $name=trim($data["name"]??""); $email=trim($data["email"]??""); $phone=trim($data["phone"]??""); $address=trim($data["address"]??"");
if($username===""||$name===""||!filter_var($email,FILTER_VALIDATE_EMAIL)) respond(false,"Please provide a valid name and email.",[],400);
$check=$conn->prepare("SELECT id FROM users WHERE email=? AND username<>? LIMIT 1"); $check->bind_param("ss",$email,$username); $check->execute(); if(stmt_has_rows_compat($check)) respond(false,"That email is already used by another account.",[],409);
$stmt=$conn->prepare("UPDATE users SET name=?,email=?,phone=?,address=? WHERE username=? AND role='customer'"); $stmt->bind_param("sssss",$name,$email,$phone,$address,$username); $stmt->execute(); respond(true,"Profile updated.");
?>