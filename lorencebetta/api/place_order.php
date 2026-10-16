<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once "db.php";require_once "stock_reservations.php";require_once "couriers.php";require_once "shipping_rates.php";ensure_order_flow_columns($conn);release_expired_reservations($conn);
$data=$_POST;$data["items"]=json_decode((string)($_POST["items"]??"[]"),true);$username=trim($data["username"]??"");
require_customer_owner($conn,$username);$customerName=trim($data["customerName"]??"");$phone=trim($data["phone"]??"");$address=trim($data["address"]??"");$payment=trim($data["payment"]??"");$mode=trim($data["deliveryMode"]??"");$courier=$mode==="pickup"?"Pick Up":"J&T Express";$items=$data["items"]??[];$reference=trim((string)($data["paymentReference"]??""));
if($username===""||$customerName===""||$phone===""||$address===""||!in_array($payment,["GCash","MariBank","AUB"],true)||!in_array($mode,["delivery","pickup"],true)||!is_array($items)||!count($items)||$reference==="")respond(false,"Please complete your order information.",[],400);
if(strlen($reference)>120)respond(false,"Reference number is too long.",[],400);
$file=$_FILES["screenshot"]??null;
if(!$file||$file["error"]!==UPLOAD_ERR_OK||$file["size"]<1||$file["size"]>5*1024*1024)respond(false,"Upload a receipt image up to 5MB.",[],400);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
$allowed=["image/jpeg"=>"jpg","image/png"=>"png","image/webp"=>"webp"];
if(!isset($allowed[$mime])||@getimagesize($file["tmp_name"])===false)respond(false,"Receipt must be a valid JPG, PNG or WEBP image.",[],400);
$folder=dirname(__DIR__)."/images/payments";
if(!is_dir($folder)&&!mkdir($folder,0755,true))respond(false,"Receipt storage unavailable.",[],500);
$filename="proof_".bin2hex(random_bytes(16)).".".$allowed[$mime];
$diskPath=$folder."/".$filename;
if(!move_uploaded_file($file["tmp_name"],$diskPath))respond(false,"Unable to save payment proof.",[],500);
$proofPath="/images/payments/".$filename;
$u=$conn->prepare("SELECT id FROM users WHERE username=? AND role='customer' AND status='active' LIMIT 1");$u->bind_param("s",$username);$u->execute();$user=stmt_fetch_assoc_compat($u);if(!$user)respond(false,"Customer account not found.",[],404);
$cq=$conn->prepare("SELECT requires_shipping_fee,courier_type FROM couriers WHERE name=? AND status='active' LIMIT 1");$cq->bind_param("s",$courier);$cq->execute();$courierRow=stmt_fetch_assoc_compat($cq);if(!$courierRow)respond(false,"Selected courier is not available.",[],409);$requiresFee=(int)$courierRow["requires_shipping_fee"]===1;
$conn->begin_transaction();
try{$subtotal=0;$validated=[];
// Aggregate duplicate product lines to prevent overselling.
$requested=[];
foreach($items as $item){
    if(!is_array($item))throw new Exception("Invalid order item.");
    $code=trim((string)($item["productId"]??""));
    $rawQty=$item["quantity"]??null;
    if($code==="" || !is_scalar($rawQty) || !preg_match('/^[1-9][0-9]*$/',(string)$rawQty))throw new Exception("Invalid order item.");
    $qty=(int)$rawQty;
    if($qty<=0 || $qty>100000 || ($requested[$code]??0)>100000-$qty)throw new Exception("Invalid order quantity.");
    $requested[$code]=($requested[$code]??0)+$qty;
}
ksort($requested,SORT_STRING);
foreach($requested as $code=>$qty){
$p=$conn->prepare("SELECT id,product_code,name,price,stock FROM products WHERE product_code=? AND status<>'deleted' FOR UPDATE");$p->bind_param("s",$code);$p->execute();$product=stmt_fetch_assoc_compat($p);if(!$product)throw new Exception("A selected product no longer exists.");
$reserved=reserved_quantity($conn,(int)$product["id"]);$available=max(0,(int)$product["stock"]-$reserved);if($available<$qty)throw new Exception($product["name"]." is currently reserved or out of stock.");
$subtotal+=(float)$product["price"]*$qty;$validated[]=["id"=>(int)$product["id"],"name"=>$product["name"],"price"=>(float)$product["price"],"quantity"=>$qty];}
$orderCode="LF-".date("YmdHis")."-".strtoupper(bin2hex(random_bytes(2)));$shipping=$mode==="pickup"?0:regional_shipping_fee($address);if($shipping===null)throw new Exception("Please include a recognizable Philippine province or Metro Manila city in your complete address.");$total=$subtotal+$shipping;
{$status="Pending";$paymentStatus="Payment Submitted";$shipConfirmed=1;$expirySql="NULL";}
$sql="INSERT INTO orders(order_code,user_id,customer_name,phone,address,subtotal,shipping,total,payment_method,payment_status,payment_reference,payment_screenshot,status,courier,shipping_confirmed_at,reservation_expires_at,stock_deducted,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,CASE WHEN ?=1 THEN NOW() ELSE NULL END,$expirySql,0,NOW(),NOW())";
$stmt=$conn->prepare($sql);$stmt->bind_param("sisssdddssssssi",$orderCode,$user["id"],$customerName,$phone,$address,$subtotal,$shipping,$total,$payment,$paymentStatus,$reference,$proofPath,$status,$courier,$shipConfirmed);$stmt->execute();$orderId=$conn->insert_id;
$it=$conn->prepare("INSERT INTO order_items(order_id,product_id,product_name,price,quantity) VALUES(?,?,?,?,?)");
foreach($validated as $v){$it->bind_param("iisdi",$orderId,$v["id"],$v["name"],$v["price"],$v["quantity"]);$it->execute();}
log_order_status($conn,$orderId,"Payment Submitted");$conn->commit();
respond(true,"Order placed. Please submit payment.",["order_id"=>$orderCode,"shipping"=>$shipping,"total"=>$total]);
}catch(Throwable $e){$conn->rollback();if(isset($diskPath)&&is_file($diskPath))unlink($diskPath);respond(false,$e->getMessage(),[],409);}
?>