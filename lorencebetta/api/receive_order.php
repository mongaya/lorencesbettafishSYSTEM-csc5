<?php
require_once "db.php";
$data=json_input();$username=trim($data["username"]??"");$orderId=trim($data["orderId"]??"");
if($username===""||$orderId==="") respond(false,"Order information is required.",[],400);
$stmt=$conn->prepare("UPDATE orders o JOIN users u ON u.id=o.user_id SET o.status='Order Received',o.updated_at=NOW() WHERE o.order_code=? AND u.username=? AND o.status='Delivered' AND o.payment_status='Paid'");
$stmt->bind_param("ss",$orderId,$username);$stmt->execute();
if($stmt->affected_rows<1) respond(false,"This order cannot be marked as received yet.",[],409);
respond(true,"Order marked as received. You may now rate the products.");
?>