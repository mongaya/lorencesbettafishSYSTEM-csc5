<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin_session($conn);
require_once "db.php";
require_once "product_helpers.php";

$id = trim($_POST["id"] ?? "");
// Ensure appearance is available on existing installations without resetting products.
$appearanceColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'appearance'");
if ($appearanceColumn && $appearanceColumn->num_rows === 0) {
    if (!$conn->query("ALTER TABLE products ADD COLUMN appearance VARCHAR(60) NOT NULL DEFAULT ''")) {
        respond(false, "Unable to initialize appearance filter.", [], 500);
    }
}
$name = trim($_POST["name"] ?? "");
$category = trim($_POST["category"] ?? "");
$price = (float)($_POST["price"] ?? 0);
$stock = (int)($_POST["stock"] ?? 0);
$description = trim($_POST["description"] ?? "");
$appearance = trim($_POST["appearance"] ?? "");
$allowedAppearances = ["", "Solid Color", "Multicolor", "Marble", "Koi", "Galaxy", "Metallic"];
if (!in_array($appearance, $allowedAppearances, true)) respond(false, "Invalid appearance.", [], 400);


if ($id === "" || $name === "" || $category === "" || $price < 0 || $stock < 0) {
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
     SET name = ?, category = ?, appearance = ?, price = ?, stock = ?,
         description = ?, image = ?, status = ?, updated_at = NOW()
     WHERE product_code = ?"
);

$stmt->bind_param(
    "sssdissss",
    $name,
    $category,
    $appearance,
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
