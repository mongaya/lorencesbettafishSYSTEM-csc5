<?php
require_once "db.php";
$result=$conn->query("SELECT id,name,username,email,phone,address,DATE_FORMAT(created_at,'%m/%d/%Y') AS registered FROM users WHERE role='customer' ORDER BY id DESC");
$customers=[]; while($row=$result->fetch_assoc()) $customers[]=["id"=>(int)$row["id"],"name"=>$row["name"],"username"=>$row["username"],"email"=>$row["email"],"phone"=>$row["phone"],"address"=>$row["address"],"registered"=>$row["registered"]];
respond(true,"",["customers"=>$customers]);
?>