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
    return ["GCash"=>read_gcash_settings($conn),"MariBank"=>read_bank_settings($conn,"MariBank"),"AUB"=>read_bank_settings($conn,"AUB")];
}

if ($_SERVER["REQUEST_METHOD"] === "GET") respond(true, "", ["settings" => read_gcash_settings($conn),"methods"=>all_payment_settings($conn)]);
$method=trim($_POST["method"]??"GCash");
if(!in_array($method,["GCash","MariBank","AUB"],true))respond(false,"Unsupported payment method.",[],400);
if($method!=="GCash") {
    $name=trim($_POST["account_name"]??"");
    $number=trim($_POST["account_number"]??"");
    if($name===""||!preg_match('/^[0-9]{6,25}$/',$number))respond(false,"Enter an account name and valid bank account number (6-25 digits).",[],400);
    $prefix=strtolower($method)."_";
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
respond(true, "GCash settings saved.", ["settings" => read_gcash_settings($conn)]);
?>
