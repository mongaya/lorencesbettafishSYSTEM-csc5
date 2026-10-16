<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin_session($conn);
require_once __DIR__ . "/stock_reservations.php";
ensure_order_flow_columns($conn);
$d=json_input();
$code=trim((string)($d["orderId"]??""));
$reason=trim((string)($d["reason"]??""));
if($code===""||$reason===""||mb_strlen($reason)>500) respond(false,"Order ID and rejection reason (up to 500 characters) are required.",[],400);
$conn->begin_transaction();
try {
  $q=$conn->prepare("SELECT id,status,payment_status,stock_deducted FROM orders WHERE order_code=? FOR UPDATE");
  $q->bind_param("s",$code);$q->execute();$o=stmt_fetch_assoc_compat($q);$q->close();
  if(!$o||$o["status"]==="Cancelled"||$o["payment_status"]!=="Payment Submitted") throw new Exception("Only submitted, unverified payments can be rejected.");
  if((int)$o["stock_deducted"]!==0) throw new Exception("This order has already had stock deducted and cannot be rejected here.");
  $id=(int)$o["id"];
  $u=$conn->prepare("UPDATE orders SET status='Awaiting Payment',payment_status='Payment Needs Correction',payment_rejection_reason=?,reservation_expires_at=NULL,updated_at=NOW() WHERE id=? AND stock_deducted=0 AND payment_status='Payment Submitted'");
  $u->bind_param("si",$reason,$id);$u->execute();
  if($u->affected_rows!==1) throw new Exception("Order state changed. Refresh and retry.");
  $u->close();
  // Keep the order available for correction without deducting stock.
  log_order_status($conn,$id,"Payment Needs Correction");
  $conn->commit();
  respond(true,"Payment needs correction. The customer can resubmit payment proof for this order.");
} catch(Throwable $e) {
  $conn->rollback();respond(false,$e->getMessage(),[],409);
}
?>