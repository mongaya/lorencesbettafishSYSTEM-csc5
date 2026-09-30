<?php
require_once "db.php";
$data=json_input();$orderId=trim($data["orderId"]??"");
if($orderId==="") respond(false,"Order ID is required.",[],400);
$q=$conn->prepare("SELECT payment_status,payment_reference,payment_screenshot,status FROM orders WHERE order_code=? LIMIT 1");
$q->bind_param("s",$orderId);$q->execute();$order=stmt_fetch_assoc_compat($q);
if(!$order) respond(false,"Order not found.",[],404);
if($order["payment_status"]==="Paid") respond(true,"Payment is already verified.");
if($order["payment_status"]!=="Payment Submitted"||trim((string)$order["payment_reference"])===""||trim((string)$order["payment_screenshot"])===""){
 respond(false,"Payment cannot be confirmed until the customer submits a reference number and payment receipt.",[],409);
}
$stmt=$conn->prepare("UPDATE orders SET payment_status='Paid',status='Confirmed',updated_at=NOW() WHERE order_code=?");
$stmt->bind_param("s",$orderId);$stmt->execute();
respond(true,"Payment verified and order confirmed.");
?>