<?php
require_once "db.php";
$data=json_input();$orderId=trim($data["orderId"]??"");$status=trim($data["status"]??"");
$allowed=["Pending Shipping Fee","Awaiting Payment","Pending","Confirmed","Preparing","Shipped","Delivered","Cancelled"];
if($orderId===""||!in_array($status,$allowed,true))respond(false,"Invalid order status.",[],400);
$stmt=$conn->prepare("UPDATE orders SET status=?,updated_at=NOW() WHERE order_code=?");$stmt->bind_param("ss",$status,$orderId);$stmt->execute();respond(true,"Order status updated.");
?>