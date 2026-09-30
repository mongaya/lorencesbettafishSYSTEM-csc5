<?php
require_once "db.php";
$data=json_input();
$orderId=trim($data["orderId"]??"");
$status=trim($data["status"]??"");
$sequenceDelivery=["Pending Shipping Fee","Awaiting Payment","Confirmed","Preparing","Shipped","Delivered"];
$sequencePickup=["Awaiting Payment","Confirmed","Preparing","Ready for Pickup","Picked Up"];
$allowed=array_merge($sequenceDelivery,$sequencePickup,["Cancelled"]);
if($orderId===""||!in_array($status,$allowed,true)) respond(false,"Invalid order status.",[],400);

$q=$conn->prepare("SELECT status,courier,payment_status FROM orders WHERE order_code=? LIMIT 1");
$q->bind_param("s",$orderId);$q->execute();$order=stmt_fetch_assoc_compat($q);
if(!$order) respond(false,"Order not found.",[],404);

$current=$order["status"];
if($current===$status) respond(true,"Order status is already up to date.");
if(in_array($current,["Delivered","Picked Up","Cancelled"],true)) respond(false,"Completed or cancelled orders can no longer be changed.",[],409);
if($status==="Cancelled"){
  respond(false,"Cancellation is not available from this status control.",[],409);
}
$seq=$order["courier"]==="Pick Up"?$sequencePickup:$sequenceDelivery;
$from=array_search($current,$seq,true);$to=array_search($status,$seq,true);
if($from===false||$to===false||$to!==$from+1) respond(false,"Order status must move forward one step at a time.",[],409);
if(in_array($status,["Confirmed","Preparing","Ready for Pickup","Shipped","Delivered","Picked Up"],true) && $order["payment_status"]!=="Paid"){
  respond(false,"Payment must be verified as Paid before this order can move forward.",[],409);
}
$stmt=$conn->prepare("UPDATE orders SET status=?,updated_at=NOW() WHERE order_code=?");
$stmt->bind_param("ss",$status,$orderId);$stmt->execute();
respond(true,"Order status updated.");
?>