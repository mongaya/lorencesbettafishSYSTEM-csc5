<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin_session($conn);
require_once "db.php";
require_once "product_helpers.php";

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


if ($name === "" || $category === "" || $price < 0 || $stock < 0) {
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
     (product_code, name, category, appearance, price, stock, description, image, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
);

$stmt->bind_param(
    "ssssdisss",
    $code,
    $name,
    $category,
    $appearance,
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
