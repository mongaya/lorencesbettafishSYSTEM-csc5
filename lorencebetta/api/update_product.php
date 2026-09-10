<?php
require_once "db.php";
require_once "product_helpers.php";

$id = trim($_POST["id"] ?? "");
$name = trim($_POST["name"] ?? "");
$category = trim($_POST["category"] ?? "");
$price = (float)($_POST["price"] ?? 0);
$stock = (int)($_POST["stock"] ?? 0);
$description = trim($_POST["description"] ?? "");

if ($id === "" || $name === "" || $price < 0 || $stock < 0) {
    respond(false, "Please enter valid product information.", [], 400);
}

$check = $conn->prepare(
    "SELECT image FROM products WHERE product_code = ? LIMIT 1"
);
$check->bind_param("s", $id);
$check->execute();

$row = stmt_fetch_assoc_compat($check);

if (!$row) {
    respond(false, "Product not found.", [], 404);
}

$newImage = save_product_image($_FILES["image"] ?? null);

$imagePath = $newImage ?: $row["image"];
$status = $stock > 0 ? "available" : "out_of_stock";

$stmt = $conn->prepare(
    "UPDATE products
     SET name = ?, category = ?, price = ?, stock = ?,
         description = ?, image = ?, status = ?, updated_at = NOW()
     WHERE product_code = ?"
);

$stmt->bind_param(
    "ssdissss",
    $name,
    $category,
    $price,
    $stock,
    $description,
    $imagePath,
    $status,
    $id
);

$stmt->execute();

respond(true, "Product updated successfully.", [
    "image" => $imagePath
]);
?>