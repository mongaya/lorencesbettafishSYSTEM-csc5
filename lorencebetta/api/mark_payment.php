<?php
require_once "db.php";
$data=json_input();$orderId=trim($data["orderId"]??"");
if($orderId==="")respond(false,"Order ID is required.",[],400);
$stmt=$conn->prepare("UPDATE orders SET payment_status='Paid',status='Confirmed',updated_at=NOW() WHERE order_code=? AND payment_method='GCash' AND shipping_confirmed_at IS NOT NULL");$stmt->bind_param("s",$orderId);$stmt->execute();respond(true,"Payment marked as paid.");
?>