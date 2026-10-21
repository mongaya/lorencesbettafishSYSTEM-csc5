<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/stock_reservations.php';
require_once __DIR__.'/cancellation_schema.php';
require_admin_session($conn);
ensure_order_flow_columns($conn);ensure_cancellation_requests($conn);
$data=json_input();
$code=trim((string)($data['orderId']??''));
$action=trim((string)($data['action']??''));
$note=trim((string)($data['note']??''));
if($code===''||!in_array($action,['approve','reject'],true)) respond(false,'Invalid review action.',[],400);
if($action==='reject'&&(strlen($note)<5||strlen($note)>1000)) respond(false,'Provide a rejection reason.',[],400);
$conn->begin_transaction();
try {
 $q=$conn->prepare('SELECT id,status,stock_deducted,payment_status,tracking_number,tracking_url FROM orders WHERE order_code=? FOR UPDATE');
 $q->bind_param('s',$code);$q->execute();$o=stmt_fetch_assoc_compat($q);$q->close();
 if(!$o) throw new Exception('Order not found.');
 $q=$conn->prepare('SELECT id,status FROM cancellation_requests WHERE order_id=? FOR UPDATE');
 $q->bind_param('i',$o['id']);$q->execute();$request=stmt_fetch_assoc_compat($q);$q->close();
 if(!$request||$request['status']!=='Pending Review') throw new Exception('No pending cancellation request.');
 if(in_array($o['status'],['Cancelled','Order Received','Picked Up','Shipped','Out for Delivery','Delivered','Lalamove Booked'],true)||$o['tracking_number']||$o['tracking_url']) throw new Exception('Order has already been handed over or completed.');
 if($action==='reject'){
   $q=$conn->prepare("UPDATE cancellation_requests SET status='Rejected',admin_reason=?,reviewed_at=NOW() WHERE id=?");
   $q->bind_param('si',$note,$request['id']);$q->execute();$q->close();
 } else {
   $paid=in_array($o['payment_status'],['Paid','Payment Submitted','Payment Needs Correction'],true);
   $next=$paid?'Refund Pending':'Approved';
   $q=$conn->prepare('UPDATE cancellation_requests SET status=?,admin_reason=?,reviewed_at=NOW() WHERE id=?');
   $q->bind_param('ssi',$next,$note,$request['id']);$q->execute();$q->close();
   if((int)$o['stock_deducted']===1){
     $items=$conn->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=?');
     $items->bind_param('i',$o['id']);$items->execute();$rows=stmt_fetch_all_assoc_compat($items);$items->close();
     foreach($rows as $item){
       $u=$conn->prepare("UPDATE products SET stock=stock+?,status='available',updated_at=NOW() WHERE id=?");
       $u->bind_param('ii',$item['quantity'],$item['product_id']);$u->execute();$u->close();
     }
   }
   $q=$conn->prepare("UPDATE orders SET status='Cancelled',stock_deducted=0,reservation_expires_at=NULL,updated_at=NOW() WHERE id=?");
   $q->bind_param('i',$o['id']);$q->execute();$q->close();log_order_status($conn,(int)$o['id'],'Cancelled');
 }
 $conn->commit();respond(true,$action==='approve'?'Cancellation approved. Check refund status.':'Cancellation rejected.');
}catch(Throwable $e){$conn->rollback();respond(false,$e->getMessage(),[],409);}
?>