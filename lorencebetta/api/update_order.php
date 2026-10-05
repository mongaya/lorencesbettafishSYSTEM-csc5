<?php
require_once "db.php";require_once "stock_reservations.php";ensure_order_flow_columns($conn);
$data=json_input();$orderId=trim($data["orderId"]??"");$status=trim($data["status"]??"");
if($orderId===""||$status==="")respond(false,"Order and next status are required.",[],400);
$q=$conn->prepare("SELECT id,status,courier,payment_status,tracking_url,tracking_number FROM orders WHERE order_code=? LIMIT 1");
$q->bind_param("s",$orderId);$q->execute();$o=stmt_fetch_assoc_compat($q);if(!$o)respond(false,"Order not found.",[],404);
$flows=[
 "Pick Up"=>["Awaiting Payment","Confirmed","Preparing","Ready for Pickup","Picked Up"],
 "Lalamove"=>["Pending Shipping Fee","Awaiting Payment","Confirmed","Preparing","Ready for Delivery","Lalamove Booked","Out for Delivery","Delivered","Order Received"],
 "J&T Express"=>["Pending Shipping Fee","Awaiting Payment","Confirmed","Preparing","Ready to Ship","Shipped","Out for Delivery","Delivered","Order Received"]
];
$flow=$flows[$o["courier"]]??null;if(!$flow)respond(false,"Unsupported courier.",[],400);
$current=array_search($o["status"],$flow,true);$next=array_search($status,$flow,true);
if($current===false||$next===false||$next!==$current+1)respond(false,"Status must follow the order process one step at a time.",[],409);
if($o["status"]==="Awaiting Payment")respond(false,"Use Verify Payment after the customer submits payment.",[],409);
if($o["payment_status"]!=="Paid"&&$next>=array_search("Confirmed",$flow,true))respond(false,"Payment must be verified before fulfillment can continue.",[],409);
$hasTracking=trim((string)$o["tracking_url"])!==""||trim((string)$o["tracking_number"])!=="";
if($o["courier"]==="Lalamove"&&$status==="Lalamove Booked"&&!$hasTracking)respond(false,"Add a tracking number or tracking link before marking the Lalamove booking complete.",[],409);
if($o["courier"]==="J&T Express"&&$status==="Shipped"&&!$hasTracking)respond(false,"Add a tracking number or tracking link before marking the order as shipped.",[],409);
$stmt=$conn->prepare("UPDATE orders SET status=?,updated_at=NOW() WHERE order_code=?");$stmt->bind_param("ss",$status,$orderId);$stmt->execute();
log_order_status($conn,(int)$o["id"],$status);respond(true,"Order moved to ".$status.".");
?>