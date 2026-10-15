<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once "db.php";require_once "stock_reservations.php";require_once "couriers.php";require_once "shipping_rates.php";ensure_order_flow_columns($conn);release_expired_reservations($conn);
$data=json_input();$username=trim($data["username"]??"");
require_customer_owner($conn,$username);$customerName=trim($data["customerName"]??"");$phone=trim($data["phone"]??"");$address=trim($data["address"]??"");$payment=trim($data["payment"]??"");$mode=trim($data["deliveryMode"]??"");$courier=$mode==="pickup"?"Pick Up":"J&T Express";$items=$data["items"]??[];
if($username===""||$customerName===""||$phone===""||$address===""||!in_array($payment,["GCash","MariBank","AUB"],true)||!in_array($mode,["delivery","pickup"],true)||!is_array($items)||!count($items))respond(false,"Please complete your order information.",[],400);
$u=$conn->prepare("SELECT id FROM users WHERE username=? AND role='customer' AND status='active' LIMIT 1");$u->bind_param("s",$username);$u->execute();$user=stmt_fetch_assoc_compat($u);if(!$user)respond(false,"Customer account not found.",[],404);
$cq=$conn->prepare("SELECT requires_shipping_fee,courier_type FROM couriers WHERE name=? AND status='active' LIMIT 1");$cq->bind_param("s",$courier);$cq->execute();$courierRow=stmt_fetch_assoc_compat($cq);if(!$courierRow)respond(false,"Selected courier is not available.",[],409);$requiresFee=(int)$courierRow["requires_shipping_fee"]===1;
$conn->begin_transaction();
try{$subtotal=0;$validated=[];
foreach($items as $item){$code=trim((string)($item["productId"]??""));$qty=(int)($item["quantity"]??0);if($code===""||$qty<=0)throw new Exception("Invalid order item.");
$p=$conn->prepare("SELECT id,product_code,name,price,stock FROM products WHERE product_code=? AND status<>'deleted' FOR UPDATE");$p->bind_param("s",$code);$p->execute();$product=stmt_fetch_assoc_compat($p);if(!$product)throw new Exception("A selected product no longer exists.");
$reserved=reserved_quantity($conn,(int)$product["id"]);$available=max(0,(int)$product["stock"]-$reserved);if($available<$qty)throw new Exception($product["name"]." is currently reserved or out of stock.");
$subtotal+=(float)$product["price"]*$qty;$validated[]=["id"=>(int)$product["id"],"name"=>$product["name"],"price"=>(float)$product["price"],"quantity"=>$qty];}
$orderCode="LF-".date("YmdHis")."-".strtoupper(bin2hex(random_bytes(2)));$shipping=$mode==="pickup"?0:regional_shipping_fee($address);if($shipping===null)throw new Exception("Please include a recognizable Philippine province or Metro Manila city in your complete address.");$total=$subtotal+$shipping;
{$status="Awaiting Payment";$paymentStatus="Unpaid";$shipConfirmed=1;$expirySql="DATE_ADD(NOW(),INTERVAL 1 HOUR)";}
$sql="INSERT INTO orders(order_code,user_id,customer_name,phone,address,subtotal,shipping,total,payment_method,payment_status,status,courier,shipping_confirmed_at,reservation_expires_at,stock_deducted,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,CASE WHEN ?=1 THEN NOW() ELSE NULL END,$expirySql,0,NOW(),NOW())";
$stmt=$conn->prepare($sql);$stmt->bind_param("sisssdddssssi",$orderCode,$user["id"],$customerName,$phone,$address,$subtotal,$shipping,$total,$payment,$paymentStatus,$status,$courier,$shipConfirmed);$stmt->execute();$orderId=$conn->insert_id;
$it=$conn->prepare("INSERT INTO order_items(order_id,product_id,product_name,price,quantity) VALUES(?,?,?,?,?)");
foreach($validated as $v){$it->bind_param("iisdi",$orderId,$v["id"],$v["name"],$v["price"],$v["quantity"]);$it->execute();}
log_order_status($conn,$orderId,$status);$conn->commit();
respond(true,"Order placed. Please submit payment.",["order_id"=>$orderCode,"shipping"=>$shipping,"total"=>$total]);
}catch(Throwable $e){$conn->rollback();respond(false,$e->getMessage(),[],409);}
?>