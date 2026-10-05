<?php
require_once "db.php";require_once "stock_reservations.php";require_once "couriers.php";ensure_order_flow_columns($conn);
$data=json_input();$orderId=trim($data["orderId"]??"");$shipping=(float)($data["shippingFee"]??-1);
if($orderId===""||$shipping<0)respond(false,"Enter a valid shipping fee.",[],400);
$stmt=$conn->prepare("SELECT o.id,o.subtotal,o.courier,o.status,c.requires_shipping_fee FROM orders o LEFT JOIN couriers c ON c.name=o.courier WHERE o.order_code=? LIMIT 1");
$stmt->bind_param("s",$orderId);$stmt->execute();$o=stmt_fetch_assoc_compat($stmt);
if(!$o)respond(false,"Order not found.",[],404);
if((int)($o["requires_shipping_fee"]??0)!==1)respond(false,"This courier does not require a shipping fee.",[],409);
if($o["status"]!=="Pending Shipping Fee")respond(false,"Shipping fee can only be set at the beginning of the order.",[],409);
$total=(float)$o["subtotal"]+$shipping;$status="Awaiting Payment";$paymentStatus="Unpaid";
$up=$conn->prepare("UPDATE orders SET shipping=?,total=?,shipping_confirmed_at=NOW(),payment_status=?,status=?,reservation_expires_at=DATE_ADD(NOW(),INTERVAL 1 HOUR),updated_at=NOW() WHERE order_code=?");
$up->bind_param("ddsss",$shipping,$total,$paymentStatus,$status,$orderId);$up->execute();
log_order_status($conn,(int)$o["id"],"Shipping Fee Set");
log_order_status($conn,(int)$o["id"],$status);
respond(true,"Shipping fee confirmed. Customer has 1 hour to submit payment.",["total"=>$total,"status"=>$status]);
?>