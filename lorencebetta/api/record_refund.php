<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/cancellation_schema.php';
require_admin_session($conn);ensure_cancellation_requests($conn);
$code=trim((string)($_POST['orderId']??''));
$ref=trim((string)($_POST['reference']??''));
$file=$_FILES['proof']??null;
if($code===''||strlen($ref)<6||strlen($ref)>150) respond(false,'Provide refund details.',[],400);
if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']>5242880) respond(false,'Upload a screenshot under 5 MB.',[],400);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if(!isset($allowed[$mime])||!getimagesize($file['tmp_name'])) respond(false,'Invalid image.',[],400);
$folder=dirname(__DIR__).'/images/refunds';
if(!is_dir($folder)) mkdir($folder,0755,true);
$name='refund_'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
if(!move_uploaded_file($file['tmp_name'],$folder.'/'.$name)) respond(false,'Upload failed.',[],500);
$path='images/refunds/'.$name;
$conn->begin_transaction();
try{
 $q=$conn->prepare('SELECT cr.id,cr.status,o.total FROM cancellation_requests cr JOIN orders o ON o.id=cr.order_id WHERE o.order_code=? FOR UPDATE');
 $q->bind_param('s',$code);$q->execute();$r=stmt_fetch_assoc_compat($q);$q->close();
 if(!$r||$r['status']!=='Refund Pending') throw new Exception('Refund not pending.');
 $q=$conn->prepare("UPDATE cancellation_requests SET status='Refunded',refund_reference=?,refund_proof=?,refund_amount=?,refunded_at=NOW() WHERE id=?");
 $q->bind_param('ssdi',$ref,$path,$r['total'],$r['id']);$q->execute();$q->close();
 $conn->commit();respond(true,'Refund recorded.');
}catch(Throwable $e){$conn->rollback();unlink($folder.'/'.$name);respond(false,$e->getMessage(),[],409);}
?>