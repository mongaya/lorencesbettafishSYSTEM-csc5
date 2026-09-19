<?php
require_once "db.php";
$column=$conn->query("SHOW COLUMNS FROM orders LIKE 'payment_screenshot'");
if($column->num_rows===0)$conn->query("ALTER TABLE orders ADD COLUMN payment_screenshot VARCHAR(255) NULL AFTER payment_reference");
$username=trim($_POST["username"]??"");$orderId=trim($_POST["orderId"]??"");$reference=trim($_POST["reference"]??"");$screenshotPath="";
$file=$_FILES["screenshot"]??null;
if($file&&$file["error"]!==UPLOAD_ERR_NO_FILE){
 if($file["error"]!==UPLOAD_ERR_OK||$file["size"]>5*1024*1024)respond(false,"Screenshot upload failed or exceeds 5MB.",[],400);
 $finfo=finfo_open(FILEINFO_MIME_TYPE);$mime=finfo_file($finfo,$file["tmp_name"]);finfo_close($finfo);$allowed=["image/jpeg"=>"jpg","image/png"=>"png","image/webp"=>"webp"];
 if(!isset($allowed[$mime]))respond(false,"Only JPG, PNG and WEBP screenshots are allowed.",[],400);
 $folder=dirname(__DIR__).DIRECTORY_SEPARATOR."images".DIRECTORY_SEPARATOR."payments";if(!is_dir($folder))mkdir($folder,0755,true);
 $filename="payment_".date("Ymd_His")."_".bin2hex(random_bytes(4)).".".$allowed[$mime];
 if(!move_uploaded_file($file["tmp_name"],$folder.DIRECTORY_SEPARATOR.$filename))respond(false,"Could not save the payment screenshot.",[],500);
 $screenshotPath="images/payments/".$filename;
}
if($username===""||$orderId===""||($reference===""&&$screenshotPath===""))respond(false,"Enter a reference number or upload a payment screenshot.",[],400);
$stmt=$conn->prepare("UPDATE orders o JOIN users u ON u.id=o.user_id SET o.payment_reference=?,o.payment_screenshot=IF(?='',o.payment_screenshot,?),o.payment_status='Payment Submitted',o.updated_at=NOW() WHERE o.order_code=? AND u.username=? AND o.payment_method='GCash' AND o.shipping_confirmed_at IS NOT NULL");
$stmt->bind_param("sssss",$reference,$screenshotPath,$screenshotPath,$orderId,$username);$stmt->execute();
if($stmt->affected_rows<1)respond(false,"Payment cannot be submitted yet. Make sure shipping has been confirmed.",[],400);
respond(true,"GCash payment proof submitted for admin verification.");
?>
