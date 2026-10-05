<?php
require_once "db.php";
require_once "stock_reservations.php";
ensure_order_flow_columns($conn);

$data=json_input();
$orderId=trim($data["orderId"]??"");
$shipping=(float)($data["shippingFee"]??-1);
if($orderId===""||$shipping<0)respond(false,"Enter a valid shipping fee.",[],400);

/* Do not JOIN orders.courier to couriers.name here: older hosted tables may use
   different utf8mb4 collations. Fetch the order first, then look up courier by
   an explicitly normalized comparison. */
$stmt=$conn->prepare("SELECT id,subtotal,courier,status FROM orders WHERE order_code=? LIMIT 1");
if(!$stmt)respond(false,"Unable to prepare shipping fee update.",[],500);
$stmt->bind_param("s",$orderId);
if(!$stmt->execute())respond(false,"Unable to load order.",[],500);
$o=stmt_fetch_assoc_compat($stmt);
$stmt->close();
if(!$o)respond(false,"Order not found.",[],404);

$cq=$conn->prepare("SELECT requires_shipping_fee FROM couriers WHERE CONVERT(name USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1");
if(!$cq)respond(false,"Unable to check courier settings.",[],500);
$courierName=(string)$o["courier"];
$cq->bind_param("s",$courierName);
if(!$cq->execute())respond(false,"Unable to check courier settings.",[],500);
$courier=stmt_fetch_assoc_compat($cq);
$cq->close();

if(!$courier || (int)$courier["requires_shipping_fee"]!==1)respond(false,"This courier does not require a shipping fee.",[],409);
if($o["status"]!=="Pending Shipping Fee")respond(false,"Shipping fee can only be set at the beginning of the order.",[],409);

$total=(float)$o["subtotal"]+$shipping;
$status="Awaiting Payment";
$paymentStatus="Unpaid";
$up=$conn->prepare("UPDATE orders SET shipping=?,total=?,shipping_confirmed_at=NOW(),payment_status=?,status=?,reservation_expires_at=DATE_ADD(NOW(),INTERVAL 1 HOUR),updated_at=NOW() WHERE order_code=?");
if(!$up)respond(false,"Unable to prepare shipping fee save.",[],500);
$up->bind_param("ddsss",$shipping,$total,$paymentStatus,$status,$orderId);
if(!$up->execute())respond(false,"Unable to save shipping fee.",[],500);
$up->close();

log_order_status($conn,(int)$o["id"],"Shipping Fee Set");
log_order_status($conn,(int)$o["id"],$status);
respond(true,"Shipping fee confirmed. Customer has 1 hour to submit payment.",["total"=>$total,"status"=>$status]);
?>