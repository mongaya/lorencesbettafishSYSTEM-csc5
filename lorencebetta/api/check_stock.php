<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/stock_reservations.php';
header('Cache-Control: no-store');
require_user_session($conn);
$requested=json_input();
$items=$requested['items']??[];
if(!is_array($items)||count($items)<1||count($items)>50)respond(false,'Select products to check.',[],400);
$unavailable=[];
foreach($items as $item){
  $code=trim((string)($item['productId']??''));
  $quantity=(int)($item['quantity']??0);
  if($code===''||$quantity<1||$quantity>999)respond(false,'Enter a valid product quantity.',[],400);
  $stmt=$conn->prepare("SELECT id,name,stock,status FROM products WHERE product_code=? LIMIT 1");
  $stmt->bind_param('s',$code);$stmt->execute();$p=stmt_fetch_assoc_compat($stmt);$stmt->close();
  $available=$p&&$p['status']==='available'?max(0,(int)$p['stock']-reserved_quantity($conn,(int)$p['id'])):0;
  if($available<$quantity)$unavailable[]=['productId'=>$code,'name'=>$p['name']??'Product','reason'=>$available<1?'Sold out':'Requested quantity is unavailable'];
}
respond(true,'', ['available'=>count($unavailable)===0,'unavailable'=>$unavailable]);
?>