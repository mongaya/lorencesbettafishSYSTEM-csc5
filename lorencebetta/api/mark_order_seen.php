<?php
require_once "db.php";require_once "stock_reservations.php";ensure_order_flow_columns($conn);
$data=json_input();$orderId=trim($data["orderId"]??"");
if($orderId==="")respond(false,"Order is required.",[],400);
$stmt=$conn->prepare("UPDATE orders SET admin_seen_at=COALESCE(admin_seen_at,NOW()) WHERE order_code=?");
$stmt->bind_param("s",$orderId);$stmt->execute();
if($stmt->affected_rows<1){
    $q=$conn->prepare("SELECT id FROM orders WHERE order_code=? LIMIT 1");$q->bind_param("s",$orderId);$q->execute();
    if(!stmt_fetch_assoc_compat($q))respond(false,"Order not found.",[],404);
}
respond(true,"Order marked as seen.");
?>