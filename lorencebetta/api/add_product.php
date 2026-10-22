<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin_session($conn);
require_once "db.php";
require_once "product_helpers.php";

// Ensure appearance is available on existing installations without resetting products.
$colorsColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'colors'");
if ($colorsColumn && $colorsColumn->num_rows === 0) {
    if (!$conn->query("ALTER TABLE products ADD COLUMN colors VARCHAR(255) NOT NULL DEFAULT ''")) {
        respond(false, "Unable to initialize product colors.", [], 500);
    }
}
// Keep existing fish records; gender can be assigned when an admin edits a product.
$genderColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'gender'");
if ($genderColumn && $genderColumn->num_rows === 0) {
    if (!$conn->query("ALTER TABLE products ADD COLUMN gender VARCHAR(12) NOT NULL DEFAULT ''")) {
        respond(false, "Unable to initialize fish gender.", [], 500);
    }
}
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
$gender = trim((string)($_POST["gender"] ?? ""));
if (!in_array($gender, ["", "Male", "Female"], true)) respond(false, "Invalid fish gender.", [], 400);
$allowedColors = ["Red","Blue","White","Black","Yellow","Orange","Purple","Green","Pink","Gold","Silver","Multicolor"];
$colorsInput = json_decode($_POST["colors"] ?? "[]", true);
if (!is_array($colorsInput) || count($colorsInput) > count($allowedColors)) respond(false, "Invalid colors.", [], 400);
$colorsInput = array_values(array_unique($colorsInput));
foreach ($colorsInput as $color) {
    if (!is_string($color) || !in_array($color, $allowedColors, true)) respond(false, "Invalid color selection.", [], 400);
}
$colors = implode(",", $colorsInput);
$allowedAppearances = ["", "Solid Color", "Multicolor", "Marble", "Koi", "Galaxy", "Metallic"];
if (!in_array($appearance, $allowedAppearances, true)) respond(false, "Invalid appearance.", [], 400);


if ($name === "" || $category === "" || $price < 0 || $stock < 0) {
    respond(false, "Please enter valid product information.", [], 400);
}

$imagePath = isset($_POST["image_base64"]) ? save_admin_base64_image($_POST["image_base64"], "products", "betta") : save_product_image($_FILES["image"] ?? null);

if (!$imagePath) {
    respond(false, "Please choose a product picture.", [], 400);
}

$code = "P" . date("ymdHis") . strtoupper(bin2hex(random_bytes(2)));
$status = $stock > 0 ? "available" : "out_of_stock";

$stmt = $conn->prepare(
    "INSERT INTO products
     (product_code, name, category, appearance, colors, gender, price, stock, description, image, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
);

$stmt->bind_param(
    "ssssssdisss",
    $code,
    $name,
    $category,
    $appearance,
    $colors,
    $gender,
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
