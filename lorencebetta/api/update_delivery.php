<?php
require_once "db.php";
$data=json_input();
$orderId=trim($data["orderId"]??"");
$trackingUrl=trim($data["trackingUrl"]??"");
if($orderId==="") respond(false,"Order is required.",[],400);
if($trackingUrl!=="" && !filter_var($trackingUrl,FILTER_VALIDATE_URL)) respond(false,"Enter a valid tracking link.",[],400);
$stmt=$conn->prepare("SELECT courier FROM orders WHERE order_code=? LIMIT 1");
$stmt->bind_param("s",$orderId);$stmt->execute();$order=stmt_fetch_assoc_compat($stmt);
if(!$order) respond(false,"Order not found.",[],404);
if($order["courier"]!=="Lalamove") respond(false,"Tracking links are only available for Lalamove orders.",[],400);
$up=$conn->prepare("UPDATE orders SET tracking_url=?,updated_at=NOW() WHERE order_code=?");
$up->bind_param("ss",$trackingUrl,$orderId);$up->execute();
respond(true,"Tracking link saved.");
?>