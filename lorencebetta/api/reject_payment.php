<?php
require_once "db.php";require_once "stock_reservations.php";ensure_order_flow_columns($conn);
$d=json_input();$orderId=trim($d["orderId"]??"");$reason=trim($d["reason"]??"");
if($orderId===""||$reason==="")respond(false,"Order and correction reason are required.",[],400);
$q=$conn->prepare("SELECT id,courier FROM orders WHERE order_code=? AND payment_status='Payment Submitted' AND stock_deducted=0 LIMIT 1");
$q->bind_param("s",$orderId);$q->execute();$o=stmt_fetch_assoc_compat($q);$q->close();
if(!$o)respond(false,"This payment cannot be returned for correction.",[],409);
$requiresFee=0;
$cq=$conn->prepare("SELECT requires_shipping_fee FROM couriers WHERE CONVERT(name USING utf8mb4) COLLATE utf8mb4_unicode_ci=CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1");
if($cq){$cn=(string)$o["courier"];$cq->bind_param("s",$cn);$cq->execute();$cr=stmt_fetch_assoc_compat($cq);$requiresFee=(int)($cr["requires_shipping_fee"]??0);$cq->close();}
$expiry=$requiresFee===1?"DATE_ADD(NOW(),INTERVAL 1 HOUR)":"NULL";
$s=$conn->prepare("UPDATE orders SET payment_status='Payment Needs Correction',payment_rejection_reason=?,payment_reference=NULL,payment_screenshot=NULL,reservation_expires_at=$expiry,updated_at=NOW() WHERE id=?");
$s->bind_param("si",$reason,$o["id"]);$s->execute();$s->close();
log_order_status($conn,(int)$o["id"],"Payment Needs Correction");
respond(true,$requiresFee===1?"Customer has 1 hour to correct and resubmit the payment.":"Customer can now correct and resubmit the payment.");
?>