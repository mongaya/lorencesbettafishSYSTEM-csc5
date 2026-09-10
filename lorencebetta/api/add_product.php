<?php
require_once "db.php";
require_once "product_helpers.php";

$name = trim($_POST["name"] ?? "");
$category = trim($_POST["category"] ?? "");
$price = (float)($_POST["price"] ?? 0);
$stock = (int)($_POST["stock"] ?? 0);
$description = trim($_POST["description"] ?? "");

if ($name === "" || $price < 0 || $stock < 0) {
    respond(false, "Please enter valid product information.", [], 400);
}

$imagePath = save_product_image($_FILES["image"] ?? null);

if (!$imagePath) {
    respond(false, "Please choose a product picture.", [], 400);
}

$code = "P" . date("ymdHis") . strtoupper(bin2hex(random_bytes(2)));
$status = $stock > 0 ? "available" : "out_of_stock";

$stmt = $conn->prepare(
    "INSERT INTO products
     (product_code, name, category, price, stock, description, image, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
);

$stmt->bind_param(
    "sssdisss",
    $code,
    $name,
    $category,
    $price,
    $stock,
    $description,
    $imagePath,
    $status
);

$stmt->execute();

respond(true, "Product added successfully.", [
    "id" => $code,
    "image" => $imagePath
]);
?>