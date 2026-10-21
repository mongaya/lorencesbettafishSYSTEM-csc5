<?php
require_once "db.php";

$conn->query("CREATE TABLE IF NOT EXISTS shop_settings (setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function read_gcash_settings($conn) {
    $settings = ["merchant_name" => "", "gcash_number" => "", "qr_image" => ""];
    $result = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE setting_key IN ('merchant_name','gcash_number','qr_image')");
    while ($row = $result->fetch_assoc()) $settings[$row["setting_key"]] = $row["setting_value"];
    return $settings;
}


function read_bank_settings($conn, $bank) {
    $prefix = strtolower($bank) . "_";
    $settings = ["account_name"=>"", "account_number"=>"", "qr_image"=>""];
    $stmt = $conn->prepare("SELECT setting_key,setting_value FROM shop_settings WHERE setting_key IN (?,?,?)");
    $a=$prefix."account_name";$b=$prefix."account_number";$c=$prefix."qr_image";
    $stmt->bind_param("sss",$a,$b,$c);$stmt->execute();
    $rows=stmt_fetch_all_assoc_compat($stmt);$stmt->close();
    foreach($rows as $row) $settings[substr($row["setting_key"],strlen($prefix))]=$row["setting_value"];
    return $settings;
}
function all_payment_settings($conn) {
    $methods=["GCash"=>read_gcash_settings($conn),"MariBank"=>read_bank_settings($conn,"MariBank"),"AUB"=>read_bank_settings($conn,"AUB")];
    $row=$conn->query("SELECT setting_value FROM shop_settings WHERE setting_key='custom_payment_methods' LIMIT 1");
    $names=$row&&($r=$row->fetch_assoc())?json_decode($r["setting_value"],true):[];
    if(!is_array($names))$names=[];
    foreach($names as $name){if(!is_string($name)||!preg_match('/^[A-Za-z][A-Za-z0-9 ]{1,39}$/',$name))continue;$methods[$name]=read_bank_settings($conn,str_replace(" ","_",$name));}
    $flags=$conn->query("SELECT setting_key,setting_value FROM shop_settings WHERE setting_key LIKE 'payment_enabled_%'");
    $enabled=[];if($flags)while($r=$flags->fetch_assoc())$enabled[substr($r["setting_key"],16)]=$r["setting_value"]==="1";
    foreach($methods as $name=>&$config){$key=strtolower(str_replace(" ","_",$name));$config["active"]=$enabled[$key]??(in_array($name,["GCash","MariBank","AUB"],true));}unset($config);
    return $methods;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    require_once __DIR__ . "/auth.php";
    require_admin_session($conn);
}
if ($_SERVER["REQUEST_METHOD"] === "GET") respond(true, "", ["settings" => read_gcash_settings($conn),"methods"=>all_payment_settings($conn)]);
$method=trim($_POST["method"]??"GCash");
if(!preg_match('/^[A-Za-z][A-Za-z0-9 ]{1,39}$/',$method))respond(false,"Invalid payment method name.",[],400);
$methods=all_payment_settings($conn);
if(isset($_POST["active"])){
    if(!isset($methods[$method]))respond(false,"Unknown payment method.",[],404);
    $active=$_POST["active"]==="1"?"1":"0";
    if($active==="1"){
        $x=$methods[$method];
        if(empty($x["account_name"])&&empty($x["merchant_name"]))respond(false,"Account details required.",[],400);
        if(empty($x["account_number"])&&empty($x["gcash_number"]))respond(false,"Account number required.",[],400);
        if(empty($x["qr_image"]))respond(false,"QR code required.",[],400);
    }
    $key="payment_enabled_".strtolower(str_replace(" ","_",$method));
    $stmt=$conn->prepare("INSERT INTO shop_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $stmt->bind_param("ss",$key,$active);$stmt->execute();$stmt->close();
    respond(true,"Payment method updated.",["methods"=>all_payment_settings($conn)]);
}
if(!isset($methods[$method])){
    $row=$conn->query("SELECT setting_value FROM shop_settings WHERE setting_key='custom_payment_methods' LIMIT 1");
    $names=$row&&($r=$row->fetch_assoc())?json_decode($r["setting_value"],true):[];
    if(!is_array($names))$names=[];
    if(count($names)>=20)respond(false,"Payment method limit reached.",[],400);
    foreach(array_keys($methods) as $existing){if(strcasecmp($existing,$method)===0)respond(false,"Payment method already exists.",[],409);}
    $names[]=$method;$json=json_encode($names);
    $stmt=$conn->prepare("INSERT INTO shop_settings (setting_key,setting_value) VALUES ('custom_payment_methods',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $stmt->bind_param("s",$json);$stmt->execute();$stmt->close();
}


if($method!=="GCash") {
    $name=trim($_POST["account_name"]??"");
    $number=trim($_POST["account_number"]??"");
    if($name===""||!preg_match('/^[0-9]{6,25}$/',$number))respond(false,"Enter an account name and valid bank account number (6-25 digits).",[],400);
    $prefix=strtolower(str_replace(" ","_",$method))."_";
    $values=[$prefix."account_name"=>$name,$prefix."account_number"=>$number];
    $file=$_FILES["qr_image"]??null;
    if($file && $file["error"]!==UPLOAD_ERR_NO_FILE) {
        if($file["error"]!==UPLOAD_ERR_OK||$file["size"]>5*1024*1024)respond(false,"QR image upload failed or exceeds 5MB.",[],400);
        $finfo=finfo_open(FILEINFO_MIME_TYPE);$mime=finfo_file($finfo,$file["tmp_name"]);finfo_close($finfo);
        $allowed=["image/jpeg"=>"jpg","image/png"=>"png","image/webp"=>"webp"];
        if(!isset($allowed[$mime]))respond(false,"Only JPG, PNG and WEBP images are allowed.",[],400);
        $folder=dirname(__DIR__).DIRECTORY_SEPARATOR."images".DIRECTORY_SEPARATOR."payments";
        if(!is_dir($folder)&&!mkdir($folder,0755,true))respond(false,"Could not create payment folder.",[],500);
        $filename=strtolower($method)."_".bin2hex(random_bytes(10)).".".$allowed[$mime];
        if(!move_uploaded_file($file["tmp_name"],$folder.DIRECTORY_SEPARATOR.$filename))respond(false,"Could not save QR image.",[],500);
        $values[$prefix."qr_image"]="images/payments/".$filename;
    }
    $stmt=$conn->prepare("INSERT INTO shop_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    foreach($values as $key=>$value){$stmt->bind_param("ss",$key,$value);$stmt->execute();}
    $stmt->close();
    respond(true,$method." settings saved.",["settings"=>read_gcash_settings($conn),"methods"=>all_payment_settings($conn)]);
}


$merchantName = trim($_POST["merchant_name"] ?? "");
$gcashNumber = trim($_POST["gcash_number"] ?? "");
if ($merchantName === "" || !preg_match('/^09[0-9]{9}$/', $gcashNumber)) respond(false, "Enter a valid account name and 11-digit GCash number.", [], 400);

$values = ["merchant_name" => $merchantName, "gcash_number" => $gcashNumber];
$file = $_FILES["qr_image"] ?? null;
if ($file && $file["error"] !== UPLOAD_ERR_NO_FILE) {
    if ($file["error"] !== UPLOAD_ERR_OK || $file["size"] > 5 * 1024 * 1024) respond(false, "QR image upload failed or exceeds 5MB.", [], 400);
    $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $file["tmp_name"]); finfo_close($finfo);
    $allowed = ["image/jpeg"=>"jpg", "image/png"=>"png", "image/webp"=>"webp"];
    if (!isset($allowed[$mime])) respond(false, "Only JPG, PNG and WEBP images are allowed.", [], 400);
    $folder = dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "payments";
    if (!is_dir($folder)) mkdir($folder, 0755, true);
    $filename = "gcash_" . date("Ymd_His") . "." . $allowed[$mime];
    if (!move_uploaded_file($file["tmp_name"], $folder . DIRECTORY_SEPARATOR . $filename)) respond(false, "Could not save the QR image.", [], 500);
    $values["qr_image"] = "images/payments/" . $filename;
}

$stmt = $conn->prepare("INSERT INTO shop_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
foreach ($values as $key => $value) { $stmt->bind_param("ss", $key, $value); $stmt->execute(); }
respond(true, "GCash settings saved.", ["settings" => read_gcash_settings($conn),"methods"=>all_payment_settings($conn)]);
?>
