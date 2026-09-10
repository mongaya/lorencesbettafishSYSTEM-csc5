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
    $products[] = [
        "id" => $row["product_code"],
        "db_id" => (int)$row["id"],
        "name" => $row["name"],
        "category" => $row["category"],
        "price" => (float)$row["price"],
        "stock" => (int)$row["stock"],
        "description" => $row["description"],
        "image" => $row["image"],
        "status" => $row["status"]
    ];
}

respond(true, "", ["products" => $products]);
?>