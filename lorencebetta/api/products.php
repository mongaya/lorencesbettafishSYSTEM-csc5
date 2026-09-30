<?php
require_once "db.php";

$result = $conn->query(
    "SELECT id, product_code, name, category, price, stock,
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
        "price" => (float)$row["price"],
        "stock" => (int)$row["stock"],
        "description" => $row["description"],
        "image" => $image,
        "status" => $row["status"]
    ];
}

respond(true, "", ["products" => $products]);
?>