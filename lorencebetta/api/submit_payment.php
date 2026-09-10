<?php
require_once "db.php";
$data=json_input();$username=trim($data["username"]??"");$orderId=trim($data["orderId"]??"");$reference=trim($data["reference"]??"");
if($username===""||$orderId===""||$reference==="")respond(false,"Payment reference is required.",[],400);
$stmt=$conn->prepare("UPDATE orders o JOIN users u ON u.id=o.user_id SET o.payment_reference=?,o.payment_status='Payment Submitted',o.updated_at=NOW() WHERE o.order_code=? AND u.username=? AND o.payment_method='GCash' AND o.shipping_confirmed_at IS NOT NULL");$stmt->bind_param("sss",$reference,$orderId,$username);$stmt->execute();if($stmt->affected_rows<1)respond(false,"Payment cannot be submitted yet. Make sure shipping has been confirmed.",[],400);respond(true,"GCash payment submitted for admin verification.");
?>