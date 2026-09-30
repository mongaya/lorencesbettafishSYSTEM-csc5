<?php
require_once "db.php";require_once "stock_reservations.php";ensure_order_flow_columns($conn);
$data=json_input();$orderId=trim($data["orderId"]??"");if($orderId==="")respond(false,"Order ID is required.",[],400);
$conn->begin_transaction();
try{$q=$conn->prepare("SELECT id,payment_status,payment_reference,payment_screenshot,stock_deducted FROM orders WHERE order_code=? FOR UPDATE");$q->bind_param("s",$orderId);$q->execute();$o=stmt_fetch_assoc_compat($q);if(!$o)throw new Exception("Order not found.");
if($o["payment_status"]==="Paid"){$conn->commit();respond(true,"Payment is already verified.");}
if($o["payment_status"]!=="Payment Submitted"||trim((string)$o["payment_reference"])===""||trim((string)$o["payment_screenshot"])==="")throw new Exception("Payment cannot be confirmed until the customer submits a reference number and payment receipt.");
if(!(int)$o["stock_deducted"]){$items=$conn->prepare("SELECT product_id,quantity FROM order_items WHERE order_id=?");$items->bind_param("i",$o["id"]);$items->execute();$rows=stmt_fetch_all_assoc_compat($items);
foreach($rows as $it){$p=$conn->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE");$p->bind_param("i",$it["product_id"]);$p->execute();$pr=stmt_fetch_assoc_compat($p);if(!$pr||(int)$pr["stock"]<(int)$it["quantity"])throw new Exception("Stock is no longer sufficient. Do not confirm this payment; contact the customer.");
$u=$conn->prepare("UPDATE products SET stock=stock-?,status=CASE WHEN stock-?>0 THEN 'available' ELSE 'out_of_stock' END,updated_at=NOW() WHERE id=?");$qty=(int)$it["quantity"];$pid=(int)$it["product_id"];$u->bind_param("iii",$qty,$qty,$pid);$u->execute();}}
$st=$conn->prepare("UPDATE orders SET payment_status='Paid',payment_rejection_reason=NULL,status='Confirmed',stock_deducted=1,reservation_expires_at=NULL,updated_at=NOW() WHERE id=?");$st->bind_param("i",$o["id"]);$st->execute();log_order_status($conn,(int)$o["id"],"Confirmed");
$conn->commit();respond(true,"Payment verified. Stock has now been deducted and the order is confirmed.");
}catch(Throwable $e){$conn->rollback();respond(false,$e->getMessage(),[],409);}
?>