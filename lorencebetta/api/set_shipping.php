<?php
require_once "db.php";
$data=json_input();$orderId=trim($data["orderId"]??"");$shipping=(float)($data["shippingFee"]??-1);
if($orderId===""||$shipping<0)respond(false,"Enter a valid shipping fee.",[],400);
$stmt=$conn->prepare("SELECT subtotal,payment_method FROM orders WHERE order_code=? LIMIT 1");$stmt->bind_param("s",$orderId);$stmt->execute();$order=stmt_fetch_assoc_compat($stmt);if(!$order)respond(false,"Order not found.",[],404);
$total=(float)$order["subtotal"]+$shipping;
if($order["payment_method"]==="GCash"){$status="Awaiting Payment";$paymentStatus="Unpaid";}else{$status="Confirmed";$paymentStatus="COD - Pay on Delivery";}
$up=$conn->prepare("UPDATE orders SET shipping=?,total=?,shipping_confirmed_at=NOW(),payment_status=?,status=?,updated_at=NOW() WHERE order_code=?");$up->bind_param("ddsss",$shipping,$total,$paymentStatus,$status,$orderId);$up->execute();respond(true,"Shipping fee confirmed.",["total"=>$total,"status"=>$status]);
?>