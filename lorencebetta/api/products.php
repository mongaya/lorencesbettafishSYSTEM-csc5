<?php
require_once "db.php";

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
$result = $conn->query(
    "SELECT id, product_code, name, category, appearance, colors, gender, price, stock,
            description, image, status
     FROM products
     WHERE status <> 'deleted'
     ORDER BY id DESC"
);

$products = [];

while ($row = $result->fetch_assoc()) {
    $image = trim((string)($row["image"] ?? ""));

    // Product images are served from the website root. Normalizing the
    // stored value prevents relative URLs from breaking on routed/query URLs.
    if ($image !== "" && !preg_match('~^(?:https?:)?//~i', $image)) {
        $image = "/" . ltrim($image, "/");
    }

    $products[] = [
        "id" => $row["product_code"],
        "db_id" => (int)$row["id"],
        "name" => $row["name"],
        "category" => $row["category"],
        "appearance" => $row["appearance"],
        "gender" => $row["gender"],
        "colors" => $row["colors"] === "" ? [] : explode(",", $row["colors"]),
        "price" => (float)$row["price"],
        "stock" => (int)$row["stock"],
        "description" => $row["description"],
        "image" => $image,
        "status" => $row["status"]
    ];
}

respond(true, "", ["products" => $products]);
?>