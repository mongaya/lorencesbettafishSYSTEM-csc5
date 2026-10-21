<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/cancellation_schema.php';
$viewer=require_user_session($conn);
if($viewer['role']!=='customer') respond(false,'Customer login required.',[],403);
ensure_cancellation_requests($conn);
$data=json_input();
$code=trim((string)($data['orderId']??''));
$reason=trim((string)($data['reason']??''));
$explanation=trim((string)($data['explanation']??''));
$allowed=['Changed my mind','Ordered wrong item','Incorrect delivery details','Processing delay','Other'];
if($code===''||!in_array($reason,$allowed,true)||strlen($explanation)<10||strlen($explanation)>1000) respond(false,'Select a reason and write at least 10 characters.',[],400);
$conn->begin_transaction();
try {
 $q=$conn->prepare('SELECT id,status,tracking_number,tracking_url FROM orders WHERE order_code=? AND user_id=? FOR UPDATE');
 $q->bind_param('si',$code,$viewer['id']);$q->execute();$o=stmt_fetch_assoc_compat($q);$q->close();
 if(!$o) throw new Exception('Order not found.');
 if(in_array($o['status'],['Cancelled','Order Received','Picked Up','Shipped','Out for Delivery','Delivered','Lalamove Booked'],true)||$o['tracking_number']||$o['tracking_url']) throw new Exception('Cancellation is no longer available.');
 $q=$conn->prepare('SELECT id FROM cancellation_requests WHERE order_id=?');$q->bind_param('i',$o['id']);$q->execute();$existing=stmt_fetch_assoc_compat($q);$q->close();
 if($existing) throw new Exception('A cancellation request already exists.');
 $q=$conn->prepare('INSERT INTO cancellation_requests(order_id,customer_id,reason,explanation) VALUES(?,?,?,?)');
 $q->bind_param('iiss',$o['id'],$viewer['id'],$reason,$explanation);$q->execute();$q->close();
 $conn->commit();respond(true,'Cancellation request submitted.');
}catch(Throwable $e){$conn->rollback();respond(false,$e->getMessage(),[],409);}
?>