<?php
require_once "db.php";
if($_SERVER['REQUEST_METHOD']==='GET'){
 $role=trim($_GET['role']??'');$username=trim($_GET['username']??'');
 if($role==='admin'){$r=$conn->query("SELECT id,username,sender_role,message,DATE_FORMAT(created_at,'%m/%d/%Y %h:%i %p') AS created_at, GREATEST(0,TIMESTAMPDIFF(SECOND,created_at,NOW())) AS age_seconds FROM chat_messages ORDER BY id ASC");}
 else {if($username==='')respond(false,"Username is required.",[],400);$st=$conn->prepare("SELECT id,username,sender_role,message,DATE_FORMAT(created_at,'%m/%d/%Y %h:%i %p') AS created_at, GREATEST(0,TIMESTAMPDIFF(SECOND,created_at,NOW())) AS age_seconds FROM chat_messages WHERE username=? ORDER BY id ASC");$st->bind_param('s',$username);$st->execute();$r=stmt_fetch_all_assoc_compat($st);}
 $messages=[];if(is_array($r)){$messages=$r;}else{while($row=$r->fetch_assoc())$messages[]=$row;}respond(true,"",["messages"=>$messages]);
}
$data=json_input();$username=trim($data['username']??'');$sender=trim($data['senderRole']??'');$message=trim($data['message']??'');
if($username===''||!in_array($sender,['customer','admin'],true)||$message==='')respond(false,'Message is required.',[],400);
$u=$conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");$u->bind_param('s',$username);$u->execute();$user=stmt_fetch_assoc_compat($u);if(!$user)respond(false,'Customer not found.',[],404);
// Quick-reply acknowledgments are persisted once per unanswered admin cycle.
// Lock the customer's user row to serialize simultaneous quick-reply requests.
$quickReply=($sender==='customer' && ($data['quickReply']??false)===true);
if($quickReply){
 $conn->begin_transaction();
 try{
  $lock=$conn->prepare("SELECT id FROM users WHERE id=? FOR UPDATE");
  $lock->bind_param('i',$user['id']);$lock->execute();
  // Consume the SELECT result before preparing another statement (mysqli without mysqlnd).
  $lock->store_result();
  $lock->free_result();
  $lock->close();
  // Each selected quick-reply topic receives its own relevant answer.
  // Never invent payment verification, shipping, or product stock status.
  $orderConcern=preg_match('/^\\[Order Concern: ([A-Za-z0-9_-]{1,80})\\]/', $message, $orderMatches)===1;
  $text=strtolower($message);
  if($orderConcern){
    $ack="[Automated Reply] We received your concern about order ".$orderMatches[1].". Our admin will review your question and respond here. Check My Orders for the latest order status.";
  }elseif(strpos($text,'payment')!==false){
    $ack="[Automated Reply] For payment concerns, please provide your order number and describe the issue. Payment proof is submitted during checkout and must be verified by our admin. We will review your concern.";
  }elseif(strpos($text,'shipping')!==false || strpos($text,'delivery')!==false){
    $ack="[Automated Reply] We use J&T Express for deliveries. The fixed shipping fee is PHP 80 for Luzon and PHP 170 for Visayas and Mindanao. Check My Orders for your current order status. For a specific delivery issue, our admin will assist you.";
  }elseif(strpos($text,'available')!==false || strpos($text,'availability')!==false || strpos($text,'betta fish')!==false || strpos($text,'fish')!==false){
    $ack="[Automated Reply] You can check the current In Stock or Sold Out label on our product listings. For questions about a specific Betta fish, tell us the product name so our admin can assist you.";
  }elseif(strpos($text,'order')!==false){
    $ack="[Automated Reply] To ask about a specific order, select Choose an Order in Chat, or Contact Admin from My Orders > View Details. The latest order status is shown under My Orders.";
  }else{
    $ack="[Automated Reply] Thank you for contacting BettaTrack Support! Your message was received. Please wait for our admin to assist you.";
  }
  // Prevent only consecutive, identical automatic answers; let distinct topics respond.
  $shouldAck=true;
  $history=$conn->prepare("SELECT sender_role,message FROM chat_messages WHERE user_id=? ORDER BY id DESC LIMIT 1");
  $history->bind_param('i',$user['id']);$history->execute();
  $latest=stmt_fetch_assoc_compat($history);
  if($latest && $latest['sender_role']==='admin' && $latest['message']===$ack){$shouldAck=false;}
  $st=$conn->prepare("INSERT INTO chat_messages(user_id,username,sender_role,message,created_at) VALUES(?,?,?,?,NOW())");
  $st->bind_param('isss',$user['id'],$username,$sender,$message);$st->execute();
  if($shouldAck){$auto='admin';$st->bind_param('isss',$user['id'],$username,$auto,$ack);$st->execute();}
  $conn->commit();
  respond(true,'Message sent.', ['automatedReplySent'=>$shouldAck]);
 }catch(Throwable $e){$conn->rollback();respond(false,'Unable to send message.',[],500);}
}
$st=$conn->prepare("INSERT INTO chat_messages(user_id,username,sender_role,message,created_at) VALUES(?,?,?,?,NOW())");$st->bind_param('isss',$user['id'],$username,$sender,$message);$st->execute();respond(true,'Message sent.');
?>
