<?php
require_once "db.php";
$data=json_input();
$username=trim($data["username"]??""); $password=$data["password"]??"";
if($username===""||$password==="") respond(false,"Username and password are required.",[],400);
$stmt=$conn->prepare("SELECT id,name,username,email,phone,address,password,role,status FROM users WHERE username=? LIMIT 1");
$stmt->bind_param("s",$username); $stmt->execute(); $user=stmt_fetch_assoc_compat($stmt);
if(!$user||!password_verify($password,$user["password"])) respond(false,"Invalid username or password.",[],401);
if($user["status"]!=="active") respond(false,"This account is not active.",[],403);
respond(true,"Login successful.",["user"=>["id"=>(int)$user["id"],"type"=>$user["role"]==="admin"?"admin":"customer","username"=>$user["username"],"name"=>$user["name"],"email"=>$user["email"],"phone"=>$user["phone"],"address"=>$user["address"]]]);
?>