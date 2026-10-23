<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin_session($conn);
require_once "db.php";require_once "stock_reservations.php";ensure_order_flow_columns($conn);require_once __DIR__."/cancellation_schema.php";ensure_cancellation_requests($conn);
$data=json_input();$orderId=trim($data["orderId"]??"");$status=trim($data["status"]??"");
$trackingNumber=trim((string)($data["trackingNumber"]??""));
$trackingUrl=trim((string)($data["trackingUrl"]??""));
if($trackingUrl!==""&&(!filter_var($trackingUrl,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($trackingUrl,PHP_URL_SCHEME)),["https","http"],true)))respond(false,"Enter a valid http(s) tracking link.",[],400);
if($orderId===""||$status==="")respond(false,"Order and next status are required.",[],400);
$q=$conn->prepare("SELECT id,status,courier,payment_status,tracking_url,tracking_number FROM orders WHERE order_code=? LIMIT 1");
$q->bind_param("s",$orderId);$q->execute();$o=stmt_fetch_assoc_compat($q);if(!$o)respond(false,"Order not found.",[],404);
$flows=[
 "Pick Up"=>["Awaiting Payment","Confirmed","Preparing","Ready for Pickup","Picked Up","Order Received"],
 "Lalamove"=>["Pending Shipping Fee","Awaiting Payment","Confirmed","Preparing","Ready for Delivery","Lalamove Booked","Out for Delivery","Delivered","Order Received"],
 "J&T Express"=>["Pending Shipping Fee","Awaiting Payment","Confirmed","Preparing","Ready to Ship","Shipped","Out for Delivery","Delivered","Order Received"]
];
$flow=$flows[$o["courier"]]??null;if(!$flow)respond(false,"Unsupported courier.",[],400);
// For store pickup, the customer alone completes the order after admin handover.
if($o["courier"]==="Pick Up"&&in_array($status,["Order Received","Picked Up"],true))respond(false,"The customer confirms receipt of pickup orders; admin should mark Ready for Pickup only.",[],403);
if($o["courier"]==="Pick Up"&&($trackingNumber!==""||$trackingUrl!==""))respond(false,"Pickup orders do not use tracking details.",[],400);
$hold=$conn->prepare("SELECT id FROM cancellation_requests WHERE order_id=? AND status='Pending Review' LIMIT 1");$hold->bind_param("i",$o["id"]);$hold->execute();$pending=stmt_fetch_assoc_compat($hold);$hold->close();if($pending)respond(false,"Cancellation request is pending admin review.",[],409);
// Payment-first orders become Confirmed immediately after verification.
 // The existing courier-specific fulfillment flow continues from Confirmed.
$current=array_search($o["status"],$flow,true);$next=array_search($status,$flow,true);
if($current===false||$next===false||!($next===$current+1||($o["courier"]==="Pick Up"&&$status==="Ready for Pickup"&&in_array($o["status"],["Confirmed","Preparing"],true))||($o["courier"]==="J&T Express"&&$status==="Ready to Ship"&&in_array($o["status"],["Confirmed","Preparing"],true))))respond(false,"Status must follow the order process one step at a time.",[],409);
if($o["status"]==="Cancelled")respond(false,"Cancelled orders cannot be processed.",[],409);
if($o["status"]==="Awaiting Payment")respond(false,"Use Verify Payment after the customer submits payment.",[],409);
if($o["payment_status"]!=="Paid"&&$next>=array_search("Confirmed",$flow,true))respond(false,"Payment must be verified before fulfillment can continue.",[],409);
$hasTracking=trim((string)$o["tracking_url"])!==""||trim((string)$o["tracking_number"])!=="";
if($o["courier"]==="Lalamove"&&$status==="Lalamove Booked"&&!$hasTracking)respond(false,"Add a tracking number or tracking link before marking the Lalamove booking complete.",[],409);
if($o["courier"]==="J&T Express"&&$status==="Out for Delivery"&&!$hasTracking)respond(false,"Add a J&T tracking number or tracking link before marking the order Out for Delivery.",[],409);
if($o["courier"]==="J&T Express"&&$status==="Shipped"){
 $number=$trackingNumber!==""?$trackingNumber:trim((string)$o["tracking_number"]);
 $url=$trackingUrl!==""?$trackingUrl:trim((string)$o["tracking_url"]);
 if($number===""&&$url==="")respond(false,"Add a tracking number or link before handing the parcel to the courier.",[],409);
 $stmt=$conn->prepare("UPDATE orders SET status=?,tracking_number=?,tracking_url=?,updated_at=NOW() WHERE order_code=?");
 $stmt->bind_param("ssss",$status,$number,$url,$orderId);
}else{
 $stmt=$conn->prepare("UPDATE orders SET status=?,updated_at=NOW() WHERE order_code=?");$stmt->bind_param("ss",$status,$orderId);
}
$stmt->execute();
if(($o["courier"]==="Pick Up"&&$status==="Ready for Pickup")||
   ($o["courier"]==="J&T Express"&&$status==="Shipped")||
   ($o["courier"]==="Lalamove"&&$status==="Lalamove Booked")){
  // Recognize only once, when a paid order first becomes non-cancellable / To Receive.
  $salesTime=(new DateTimeImmutable("now",new DateTimeZone("Asia/Manila")))->format("Y-m-d H:i:s");
  $sale=$conn->prepare("UPDATE orders SET sales_recognized_at=? WHERE id=? AND payment_status='Paid' AND sales_recognized_at IS NULL");
  $sale->bind_param("si",$salesTime,$o["id"]);$sale->execute();$sale->close();
}
log_order_status($conn,(int)$o["id"],$status);respond(true,"Order moved to ".$status.".");
?>