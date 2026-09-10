<?php
require_once "db.php";

$data = json_input();
$id = trim($data["id"] ?? "");

if ($id === "") {
    respond(false, "Product ID is required.", [], 400);
}

$stmt = $conn->prepare(
    "UPDATE products
     SET status = 'deleted', updated_at = NOW()
     WHERE product_code = ?"
);
$stmt->bind_param("s", $id);
$stmt->execute();

respond(true, "Product deleted.");
?>