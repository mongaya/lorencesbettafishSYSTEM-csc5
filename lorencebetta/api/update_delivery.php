<?php
require_once "db.php";require_once "stock_reservations.php";require_once "couriers.php";
ensure_order_flow_columns($conn);
$data=json_input();$orderId=trim($data["orderId"]??"");
$trackingUrl=trim($data["trackingUrl"]??"");$trackingNumber=trim($data["trackingNumber"]??"");
if($orderId==="") respond(false,"Order is required.",[],400);
if($trackingUrl!=="" && !filter_var($trackingUrl,FILTER_VALIDATE_URL)) respond(false,"Enter a valid tracking link.",[],400);
$stmt=$conn->prepare("SELECT o.courier,COALESCE(c.courier_type,'delivery') courier_type,COALESCE(c.tracking_enabled,0) tracking_enabled FROM orders o LEFT JOIN couriers c ON c.name=o.courier WHERE o.order_code=? LIMIT 1");
$stmt->bind_param("s",$orderId);$stmt->execute();$order=stmt_fetch_assoc_compat($stmt);
if(!$order) respond(false,"Order not found.",[],404);
if(strtolower((string)$order["courier_type"])==="pickup") respond(false,"Pick Up orders do not use tracking.",[],409);
if($trackingUrl===""&&$trackingNumber==="") respond(false,"Enter a tracking number or tracking link.",[],400);
$up=$conn->prepare("UPDATE orders SET tracking_number=?,tracking_url=?,updated_at=NOW() WHERE order_code=?");
$up->bind_param("sss",$trackingNumber,$trackingUrl,$orderId);$up->execute();
respond(true,"Tracking information saved.");
?>