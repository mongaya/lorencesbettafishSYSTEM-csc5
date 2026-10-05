<?php
require_once "db.php";require_once "stock_reservations.php";require_once "couriers.php";
ensure_order_flow_columns($conn);release_expired_reservations($conn);
$username=trim($_POST["username"]??"");$orderId=trim($_POST["orderId"]??"");$reference=trim($_POST["reference"]??"");$file=$_FILES["screenshot"]??null;
if($username===""||$orderId===""||$reference==="")respond(false,"Reference number is required.",[],400);
if(!$file||$file["error"]===UPLOAD_ERR_NO_FILE)respond(false,"Please upload your payment receipt.",[],400);
if($file["error"]!==UPLOAD_ERR_OK||$file["size"]>5*1024*1024)respond(false,"Payment receipt upload failed or exceeds 5MB.",[],400);

$check=$conn->prepare("SELECT o.id,o.status,o.payment_status,o.reservation_expires_at,COALESCE(c.requires_shipping_fee,0) requires_shipping_fee
 FROM orders o JOIN users u ON u.id=o.user_id LEFT JOIN couriers c ON c.name=o.courier
 WHERE o.order_code=? AND u.username=? LIMIT 1");
$check->bind_param("ss",$orderId,$username);$check->execute();$order=stmt_fetch_assoc_compat($check);$check->close();
if(!$order||$order["status"]!=="Awaiting Payment"||!in_array($order["payment_status"],["Unpaid","Payment Needs Correction"],true))
    respond(false,"Payment cannot be submitted for this order right now.",[],409);
if((int)$order["requires_shipping_fee"]===1){
    if(empty($order["reservation_expires_at"])||strtotime($order["reservation_expires_at"])<=time())
        respond(false,"The 1-hour payment window has expired. The order reservation was released.",[],409);
}

$finfo=finfo_open(FILEINFO_MIME_TYPE);$mime=finfo_file($finfo,$file["tmp_name"]);finfo_close($finfo);
$allowed=["image/jpeg"=>"jpg","image/png"=>"png","image/webp"=>"webp"];
if(!isset($allowed[$mime]))respond(false,"Only JPG, PNG and WEBP receipt images are allowed.",[],400);
$folder=dirname(__DIR__).DIRECTORY_SEPARATOR."images".DIRECTORY_SEPARATOR."payments";
if(!is_dir($folder))mkdir($folder,0755,true);
$filename="payment_".date("Ymd_His")."_".bin2hex(random_bytes(4)).".".$allowed[$mime];
if(!move_uploaded_file($file["tmp_name"],$folder.DIRECTORY_SEPARATOR.$filename))respond(false,"Could not save the payment receipt.",[],500);
$path="/images/payments/".$filename;

$stmt=$conn->prepare("UPDATE orders o JOIN users u ON u.id=o.user_id
 SET o.payment_reference=?,o.payment_screenshot=?,o.payment_status='Payment Submitted',
     o.payment_rejection_reason=NULL,o.reservation_expires_at=NULL,o.updated_at=NOW()
 WHERE o.order_code=? AND u.username=? AND o.payment_method IN ('GCash','MariBank','AUB')
   AND o.shipping_confirmed_at IS NOT NULL AND o.status='Awaiting Payment' AND o.stock_deducted=0
   AND o.payment_status IN ('Unpaid','Payment Needs Correction')");
$stmt->bind_param("ssss",$reference,$path,$orderId,$username);$stmt->execute();
if($stmt->affected_rows<1)respond(false,"Payment cannot be submitted for this order right now.",[],409);
log_order_status($conn,(int)$order["id"],"Payment Submitted");
respond(true,"Payment submitted for admin verification. Stock is held while the admin checks it.");
?>