<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once "db.php";require_once "stock_reservations.php";
ensure_order_flow_columns($conn);require_once __DIR__."/cancellation_schema.php";ensure_cancellation_requests($conn);release_expired_reservations($conn);
$username=trim($_POST["username"]??"");
require_customer_owner($conn,$username);$orderId=trim($_POST["orderId"]??"");$reference=trim($_POST["reference"]??"");$file=$_FILES["screenshot"]??null;
if($username===""||$orderId===""||$reference==="")respond(false,"Reference number is required.",[],400);
if(!$file||$file["error"]===UPLOAD_ERR_NO_FILE)respond(false,"Please upload your payment receipt.",[],400);
if($file["error"]!==UPLOAD_ERR_OK||$file["size"]<1||$file["size"]>5*1024*1024)respond(false,"Payment receipt upload failed or exceeds 5MB.",[],400);

$check=$conn->prepare("SELECT o.id,o.status,o.payment_status,o.reservation_expires_at,o.courier,o.payment_attempt_count,o.payment_correction_requested
 FROM orders o JOIN users u ON u.id=o.user_id
 WHERE o.order_code=? AND u.username=? LIMIT 1");
if(!$check)respond(false,"Unable to prepare payment submission.",[],500);
$check->bind_param("ss",$orderId,$username);
if(!$check->execute())respond(false,"Unable to load order for payment.",[],500);
$order=stmt_fetch_assoc_compat($check);$check->close();
if($order){$hold=$conn->prepare("SELECT id FROM cancellation_requests WHERE order_id=? AND status='Pending Review' LIMIT 1");$hold->bind_param("i",$order["id"]);$hold->execute();$pending=stmt_fetch_assoc_compat($hold);$hold->close();if($pending)respond(false,"Cancellation request is pending admin review.",[],409);}
if($order&&(int)$order["payment_attempt_count"]>=2)respond(false,"Maximum two payment attempts reached. Contact the shop.",[],409);
if(!$order||$order["status"]!=="Awaiting Payment"||!in_array($order["payment_status"],["Unpaid","Payment Needs Correction"],true))
    respond(false,"Payment cannot be submitted for this order right now.",[],409);

$requiresFee=0;
$cq=$conn->prepare("SELECT requires_shipping_fee FROM couriers WHERE CONVERT(name USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1");
if($cq){$courierName=(string)$order["courier"];$cq->bind_param("s",$courierName);if($cq->execute()){$cr=stmt_fetch_assoc_compat($cq);$requiresFee=(int)($cr["requires_shipping_fee"]??0);}$cq->close();}
if($order["payment_status"]!=="Payment Needs Correction" && $requiresFee===1 && (empty($order["reservation_expires_at"])||strtotime($order["reservation_expires_at"])<=time()))
    respond(false,"The 1-hour payment window has expired. The order reservation was released.",[],409);

$finfo=finfo_open(FILEINFO_MIME_TYPE);$mime=finfo_file($finfo,$file["tmp_name"]);finfo_close($finfo);
$allowed=["image/jpeg"=>"jpg","image/png"=>"png","image/webp"=>"webp"];
if(!isset($allowed[$mime])||@getimagesize($file["tmp_name"])===false)respond(false,"Only JPG, PNG and WEBP receipt images are allowed.",[],400);
$folder=dirname(__DIR__).DIRECTORY_SEPARATOR."images".DIRECTORY_SEPARATOR."payments";
if(!is_dir($folder)&&!mkdir($folder,0755,true))respond(false,"Could not create payment receipt folder.",[],500);
$filename="payment_".date("Ymd_His")."_".bin2hex(random_bytes(4)).".".$allowed[$mime];
if(!move_uploaded_file($file["tmp_name"],$folder.DIRECTORY_SEPARATOR.$filename))respond(false,"Could not save the payment receipt.",[],500);
$path="/images/payments/".$filename;

// Serialize reference reuse checks across checkout and payment corrections.
$sharedPaymentLock="payment_global_".substr(hash("sha256",$reference),0,48);
$sharedLockStmt=$conn->prepare("SELECT GET_LOCK(?,10) AS acquired");
if(!$sharedLockStmt)respond(false,"Unable to validate payment reference.",[],500);
$sharedLockStmt->bind_param("s",$sharedPaymentLock);
if(!$sharedLockStmt->execute()){$sharedLockStmt->close();respond(false,"Unable to validate payment reference.",[],500);}
$sharedLockResult=stmt_fetch_assoc_compat($sharedLockStmt);$sharedLockStmt->close();
if((int)($sharedLockResult["acquired"]??0)!==1)respond(false,"Payment reference is being processed. Please retry.",[],409);
register_shutdown_function(static function()use($conn,$sharedPaymentLock){
    $release=$conn->prepare("SELECT RELEASE_LOCK(?)");
    if($release){$release->bind_param("s",$sharedPaymentLock);$release->execute();$release->close();}
});
// Prevent legacy payment resubmissions from reusing another order's reference.
$duplicate=$conn->prepare("SELECT id FROM orders WHERE payment_reference=? AND order_code<>? LIMIT 1");
if(!$duplicate){@unlink($folder.DIRECTORY_SEPARATOR.$filename);respond(false,"Unable to validate payment reference.",[],500);}
$duplicate->bind_param("ss",$reference,$orderId);
if(!$duplicate->execute()){$duplicate->close();@unlink($folder.DIRECTORY_SEPARATOR.$filename);respond(false,"Unable to validate payment reference.",[],500);}
$usedReference=stmt_fetch_assoc_compat($duplicate);$duplicate->close();
if($usedReference){@unlink($folder.DIRECTORY_SEPARATOR.$filename);respond(false,"Payment Already Used! This transaction reference belongs to another order. Check My Orders or contact the shop; do not pay again.",[],409);}
// Reject exact duplicate receipt files before replacing an existing payment proof.
$incomingHash=hash_file("sha256",$folder.DIRECTORY_SEPARATOR.$filename);
$receiptCheck=$conn->prepare("SELECT payment_screenshot FROM orders WHERE payment_screenshot IS NOT NULL AND payment_screenshot<>''");
if(!$receiptCheck || !$receiptCheck->execute()){
    if($receiptCheck)$receiptCheck->close();
    @unlink($folder.DIRECTORY_SEPARATOR.$filename);
    respond(false,"Unable to validate payment receipt.",[],500);
}
$receiptCheck->bind_result($priorReceipt);
$receiptUsed=false;
while($receiptCheck->fetch()){
    $priorFile=dirname(__DIR__)."/".ltrim((string)$priorReceipt,"/");
    if(is_file($priorFile) && hash_file("sha256",$priorFile)===$incomingHash){$receiptUsed=true;break;}
}
$receiptCheck->close();
if($receiptUsed){
    @unlink($folder.DIRECTORY_SEPARATOR.$filename);
    respond(false,"Payment Already Used! This receipt image has already been submitted. Please contact the shop if you need help.",[],409);
}
$nextAttempt=(int)$order["payment_attempt_count"]+1;
$conn->begin_transaction();
$stmt=$conn->prepare("UPDATE orders o JOIN users u ON u.id=o.user_id
 SET o.payment_reference=?,o.payment_screenshot=?,o.payment_status='Payment Submitted',
     o.payment_rejection_reason=NULL,o.payment_attempt_count=o.payment_attempt_count+1,o.reservation_expires_at=NULL,o.updated_at=NOW()
 WHERE o.order_code=? AND u.username=? AND o.payment_method IN ('GCash','MariBank','AUB')
   AND o.shipping_confirmed_at IS NOT NULL AND o.status='Awaiting Payment' AND o.stock_deducted=0
   AND o.payment_status IN ('Unpaid','Payment Needs Correction') AND o.payment_attempt_count<2");
if(!$stmt){$conn->rollback();@unlink($folder.DIRECTORY_SEPARATOR.$filename);respond(false,"Unable to prepare payment save.",[],500);}
$stmt->bind_param("ssss",$reference,$path,$orderId,$username);
if(!$stmt->execute()){$conn->rollback();@unlink($folder.DIRECTORY_SEPARATOR.$filename);respond(false,"Unable to save payment submission.",[],500);}
if($stmt->affected_rows<1){$conn->rollback();@unlink($folder.DIRECTORY_SEPARATOR.$filename);respond(false,"Payment cannot be submitted for this order right now.",[],409);}
$stmt->close();
$history=$conn->prepare("INSERT INTO payment_attempts(order_id,attempt_number,payment_reference,payment_screenshot,status) VALUES(?,?,?,?,'Submitted')");
if(!$history){$conn->rollback();@unlink($folder.DIRECTORY_SEPARATOR.$filename);respond(false,"Unable to save payment history.",[],500);}
$history->bind_param("iiss",$order["id"],$nextAttempt,$reference,$path);
if(!$history->execute()){$conn->rollback();@unlink($folder.DIRECTORY_SEPARATOR.$filename);respond(false,"Unable to save payment history.",[],500);}
$history->close();log_order_status($conn,(int)$order["id"],"Payment Submitted");$conn->commit();
respond(true,"Payment submitted for admin verification. Stock is held while the admin checks it.");
?>