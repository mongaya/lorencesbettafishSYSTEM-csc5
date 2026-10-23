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
  $ack="[Automated Reply] Thank you for contacting BettaTrack Support! Your inquiry has been received. Please wait for an admin to respond. We will assist you as soon as possible.";
  // If the most recent message is from a human admin, start a fresh response cycle.
  // Otherwise acknowledge only when no previous auto-response exists since that admin reply.
  $shouldAck=true;
  $history=$conn->prepare("SELECT sender_role,message FROM chat_messages WHERE user_id=? ORDER BY id DESC");
  $history->bind_param('i',$user['id']);$history->execute();
  foreach(stmt_fetch_all_assoc_compat($history) as $row){
   if($row['sender_role']==='admin' && $row['message']!==$ack)break;
   if($row['sender_role']==='admin' && $row['message']===$ack){$shouldAck=false;break;}
  }
  $st=$conn->prepare("INSERT INTO chat_messages(user_id,username,sender_role,message,created_at) VALUES(?,?,?,?,NOW())");
  $st->bind_param('isss',$user['id'],$username,$sender,$message);$st->execute();
  if($shouldAck){$auto='admin';$st->bind_param('isss',$user['id'],$username,$auto,$ack);$st->execute();}
  $conn->commit();
  respond(true,'Message sent.', ['automatedReplySent'=>$shouldAck]);
 }catch(Throwable $e){$conn->rollback();respond(false,'Unable to send message.',[],500);}
}
$st=$conn->prepare("INSERT INTO chat_messages(user_id,username,sender_role,message,created_at) VALUES(?,?,?,?,NOW())");$st->bind_param('isss',$user['id'],$username,$sender,$message);$st->execute();respond(true,'Message sent.');
?>
