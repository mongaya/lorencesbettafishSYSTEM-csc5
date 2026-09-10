<?php
require_once "db.php";
$orderResult=$conn->query("SELECT o.id,o.order_code,u.username,o.customer_name,o.phone,o.address,o.subtotal,o.shipping,o.total,o.payment_method,o.payment_status,o.payment_reference,o.shipping_confirmed_at,o.status,DATE_FORMAT(o.created_at,'%Y-%m-%d %H:%i:%s') AS order_date FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.id DESC");
$orders=[];while($row=$orderResult->fetch_assoc()){
 $orderId=(int)$row["id"]; $stmt=$conn->prepare("SELECT oi.product_id,oi.product_name AS name,oi.price,oi.quantity,p.image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC");$stmt->bind_param("i",$orderId);$stmt->execute();$items=[];foreach(stmt_fetch_all_assoc_compat($stmt) as $it)$items[]=["productId"=>(int)$it["product_id"],"name"=>$it["name"],"price"=>(float)$it["price"],"quantity"=>(int)$it["quantity"],"image"=>$it["image"]];
 $orders[]=["id"=>$row["order_code"],"username"=>$row["username"],"customerName"=>$row["customer_name"],"phone"=>$row["phone"],"address"=>$row["address"],"items"=>$items,"subtotal"=>(float)$row["subtotal"],"shipping"=>(float)$row["shipping"],"total"=>(float)$row["total"],"payment"=>$row["payment_method"],"payment_status"=>$row["payment_status"],"payment_reference"=>$row["payment_reference"],"shipping_confirmed"=>!empty($row["shipping_confirmed_at"]),"status"=>$row["status"],"date"=>$row["order_date"]];
}
respond(true,"",["orders"=>$orders]);
?>