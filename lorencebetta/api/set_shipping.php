<?php
require_once "db.php";$data=json_input();$orderId=trim($data["orderId"]??"");$shipping=(float)($data["shippingFee"]??-1);
if($orderId===""||$shipping<0)respond(false,"Enter a valid shipping fee.",[],400);
$stmt=$conn->prepare("SELECT subtotal,courier,status FROM orders WHERE order_code=? LIMIT 1");$stmt->bind_param("s",$orderId);$stmt->execute();$o=stmt_fetch_assoc_compat($stmt);if(!$o)respond(false,"Order not found.",[],404);
if($o["courier"]==="Pick Up")respond(false,"Pick Up orders do not need a shipping fee.",[],409);
if($o["status"]!=="Pending Shipping Fee")respond(false,"Shipping fee can only be set at the beginning of the order.",[],409);
$total=(float)$o["subtotal"]+$shipping;$status="Awaiting Payment";$paymentStatus="Unpaid";
$up=$conn->prepare("UPDATE orders SET shipping=?,total=?,shipping_confirmed_at=NOW(),payment_status=?,status=?,updated_at=NOW() WHERE order_code=?");$up->bind_param("ddsss",$shipping,$total,$paymentStatus,$status,$orderId);$up->execute();respond(true,"Shipping fee confirmed. Customer can now submit payment.",["total"=>$total,"status"=>$status]);
?>