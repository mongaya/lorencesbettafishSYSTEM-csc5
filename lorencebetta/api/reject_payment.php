<?php
require_once "db.php";require_once "stock_reservations.php";
ensure_order_flow_columns($conn);
$d=json_input();$orderId=trim($d["orderId"]??"");$reason=trim($d["reason"]??"");
if($orderId===""||$reason==="")respond(false,"Order and correction reason are required.",[],400);
$s=$conn->prepare("UPDATE orders SET payment_status='Payment Needs Correction',
 payment_rejection_reason=?,payment_reference=NULL,payment_screenshot=NULL,
 reservation_expires_at=DATE_ADD(NOW(),INTERVAL 15 MINUTE),updated_at=NOW()
 WHERE order_code=? AND payment_status='Payment Submitted' AND stock_deducted=0");
$s->bind_param("ss",$reason,$orderId);$s->execute();
if($s->affected_rows<1)respond(false,"This payment cannot be returned for correction.",[],409);
respond(true,"Customer can now correct and resubmit the payment.");
?>