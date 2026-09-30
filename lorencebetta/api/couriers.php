<?php
require_once "db.php";
function ensure_couriers($conn){
 $conn->query("CREATE TABLE IF NOT EXISTS couriers(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE,requires_shipping_fee TINYINT(1) NOT NULL DEFAULT 1,courier_type ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery',tracking_enabled TINYINT(1) NOT NULL DEFAULT 0,status ENUM('active','disabled') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $n=$conn->query("SELECT COUNT(*) c FROM couriers");$r=$n?$n->fetch_assoc():["c"=>0];
 if((int)$r["c"]===0)$conn->query("INSERT IGNORE INTO couriers(name,requires_shipping_fee,courier_type,tracking_enabled,status) VALUES('Lalamove',1,'delivery',1,'active'),('J&T Express',1,'delivery',0,'active'),('Pick Up',0,'pickup',0,'active')");
}
ensure_couriers($conn);
if($_SERVER["REQUEST_METHOD"]==="GET"){$r=$conn->query("SELECT id,name,requires_shipping_fee,courier_type,tracking_enabled,status FROM couriers ORDER BY status='active' DESC,name");$rows=[];while($x=$r->fetch_assoc()){$x["id"]=(int)$x["id"];$x["requires_shipping_fee"]=(bool)$x["requires_shipping_fee"];$x["tracking_enabled"]=(bool)$x["tracking_enabled"];$rows[]=$x;}respond(true,"",["couriers"=>$rows]);}
$d=json_input();$action=trim($d["action"]??"save");
if($action==="disable"){$id=(int)($d["id"]??0);$s=$conn->prepare("UPDATE couriers SET status='disabled' WHERE id=?");$s->bind_param("i",$id);$s->execute();respond(true,"Courier disabled.");}
$id=(int)($d["id"]??0);$name=trim($d["name"]??"");$fee=!empty($d["requires_shipping_fee"])?1:0;$type=($d["courier_type"]??"delivery")==="pickup"?"pickup":"delivery";$track=!empty($d["tracking_enabled"])?1:0;$status=($d["status"]??"active")==="disabled"?"disabled":"active";
if($name==="")respond(false,"Courier name is required.",[],400);
if($id>0){$s=$conn->prepare("UPDATE couriers SET name=?,requires_shipping_fee=?,courier_type=?,tracking_enabled=?,status=? WHERE id=?");$s->bind_param("sisisi",$name,$fee,$type,$track,$status,$id);}
else{$s=$conn->prepare("INSERT INTO couriers(name,requires_shipping_fee,courier_type,tracking_enabled,status) VALUES(?,?,?,?,?)");$s->bind_param("sisis",$name,$fee,$type,$track,$status);}
if(!$s->execute())respond(false,"Unable to save courier. The name may already exist.",[],409);respond(true,"Courier saved.");
?>