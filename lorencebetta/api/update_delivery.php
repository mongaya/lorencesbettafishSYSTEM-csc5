<?php
require_once "db.php";
require_once "stock_reservations.php";
ensure_order_flow_columns($conn);

$data=json_input();
$orderId=trim($data["orderId"]??"");
$trackingUrl=trim($data["trackingUrl"]??"");
$trackingNumber=trim($data["trackingNumber"]??"");

if($orderId==="") respond(false,"Order is required.",[],400);
if($trackingUrl!=="" && !filter_var($trackingUrl,FILTER_VALIDATE_URL)) respond(false,"Enter a valid tracking link.",[],400);

$stmt=$conn->prepare("SELECT courier FROM orders WHERE order_code=? LIMIT 1");
if(!$stmt) respond(false,"Unable to prepare tracking update.",[],500);
$stmt->bind_param("s",$orderId);
if(!$stmt->execute()) respond(false,"Unable to load order.",[],500);
$order=stmt_fetch_assoc_compat($stmt);
$stmt->close();

if(!$order) respond(false,"Order not found.",[],404);
if(strtolower(trim((string)$order["courier"]))==="pick up") respond(false,"Pick Up orders do not use tracking.",[],409);
if($trackingUrl===""&&$trackingNumber==="") respond(false,"Enter a tracking number or tracking link.",[],400);

$up=$conn->prepare("UPDATE orders SET tracking_number=?,tracking_url=?,updated_at=NOW() WHERE order_code=?");
if(!$up) respond(false,"Unable to prepare tracking save.",[],500);
$up->bind_param("sss",$trackingNumber,$trackingUrl,$orderId);
if(!$up->execute()){ $up->close(); respond(false,"Unable to save tracking information.",[],500); }
$up->close();

respond(true,"Tracking information saved.");
?>