<?php
require_once "db.php";

$conn->query("CREATE TABLE IF NOT EXISTS shop_settings (setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function read_gcash_settings($conn) {
    $settings = ["merchant_name" => "", "gcash_number" => "", "qr_image" => ""];
    $result = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE setting_key IN ('merchant_name','gcash_number','qr_image')");
    while ($row = $result->fetch_assoc()) $settings[$row["setting_key"]] = $row["setting_value"];
    return $settings;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") respond(true, "", ["settings" => read_gcash_settings($conn)]);

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
