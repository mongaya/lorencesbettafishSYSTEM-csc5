<?php
require_once "db.php";
$data=json_input(); $username=trim($data["username"]??""); $customerName=trim($data["customerName"]??""); $phone=trim($data["phone"]??""); $address=trim($data["address"]??""); $payment=trim($data["payment"]??""); $items=$data["items"]??[];
if($username===""||$customerName===""||$phone===""||$address===""||!in_array($payment,["GCash","Cash on Delivery"],true)||!is_array($items)||!count($items)) respond(false,"Please complete your order information.",[],400);
$u=$conn->prepare("SELECT id FROM users WHERE username=? AND role='customer' AND status='active' LIMIT 1"); $u->bind_param("s",$username); $u->execute(); $user=stmt_fetch_assoc_compat($u); if(!$user) respond(false,"Customer account not found.",[],404);
$conn->begin_transaction();
try{
 $subtotal=0; $validated=[];
 foreach($items as $item){$code=trim((string)($item["productId"]??""));$qty=(int)($item["quantity"]??0);if($code===""||$qty<=0)throw new Exception("Invalid order item.");$p=$conn->prepare("SELECT id,product_code,name,price,stock FROM products WHERE product_code=? AND status<>'deleted' FOR UPDATE");$p->bind_param("s",$code);$p->execute();$product=stmt_fetch_assoc_compat($p);if(!$product)throw new Exception("A selected product no longer exists.");if((int)$product["stock"]<$qty)throw new Exception("Not enough stock for ".$product["name"].".");$line=(float)$product["price"]*$qty;$subtotal+=$line;$validated[]=["id"=>(int)$product["id"],"name"=>$product["name"],"price"=>(float)$product["price"],"quantity"=>$qty];}
 $orderCode="LF-".date("YmdHis")."-".strtoupper(bin2hex(random_bytes(2)));
 $shipping=0; $total=$subtotal; $status="Pending Shipping Fee"; $paymentStatus="Waiting for shipping fee";
 $stmt=$conn->prepare("INSERT INTO orders(order_code,user_id,customer_name,phone,address,subtotal,shipping,total,payment_method,payment_status,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
 $stmt->bind_param("sisssdddsss",$orderCode,$user["id"],$customerName,$phone,$address,$subtotal,$shipping,$total,$payment,$paymentStatus,$status);$stmt->execute();$orderId=$conn->insert_id;
 $it=$conn->prepare("INSERT INTO order_items(order_id,product_id,product_name,price,quantity) VALUES(?,?,?,?,?)");
 $st=$conn->prepare("UPDATE products SET stock=stock-?,status=CASE WHEN stock-?>0 THEN 'available' ELSE 'out_of_stock' END,updated_at=NOW() WHERE id=?");
 foreach($validated as $v){$it->bind_param("iisdi",$orderId,$v["id"],$v["name"],$v["price"],$v["quantity"]);$it->execute();$q=$v["quantity"];$st->bind_param("iii",$q,$q,$v["id"]);$st->execute();}
 $conn->commit(); respond(true,"Order placed. Waiting for admin shipping fee confirmation.",["order_id"=>$orderCode]);
}catch(Throwable $e){$conn->rollback();respond(false,$e->getMessage(),[],400);}
?>