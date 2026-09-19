<?php
require_once "db.php";

$conn->query("CREATE TABLE IF NOT EXISTS fish_categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("INSERT IGNORE INTO fish_categories (name) SELECT DISTINCT TRIM(category) FROM products WHERE category IS NOT NULL AND TRIM(category) <> ''");

function valid_category_name($value) {
    $name = trim((string)$value);
    if ($name === "" || strlen($name) > 80) respond(false, "Category name must contain 1 to 80 characters.", [], 400);
    return $name;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $result = $conn->query("SELECT id, name FROM fish_categories ORDER BY name");
    $categories = [];
    while ($row = $result->fetch_assoc()) $categories[] = ["id" => (int)$row["id"], "name" => $row["name"]];
    respond(true, "", ["categories" => $categories]);
}

$input = json_input();
$action = $input["action"] ?? "";

if ($action === "add") {
    $name = valid_category_name($input["name"] ?? "");
    $stmt = $conn->prepare("INSERT INTO fish_categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    if (!$stmt->execute()) respond(false, "That category already exists.", [], 409);
    respond(true, "Category added.");
}

$id = (int)($input["id"] ?? 0);
if ($id <= 0) respond(false, "Invalid category.", [], 400);
$lookup = $conn->prepare("SELECT name FROM fish_categories WHERE id = ? LIMIT 1");
$lookup->bind_param("i", $id); $lookup->execute();
$old = stmt_fetch_assoc_compat($lookup);
if (!$old) respond(false, "Category not found.", [], 404);

if ($action === "rename") {
    $name = valid_category_name($input["name"] ?? "");
    $conn->begin_transaction();
    $updateProducts = $conn->prepare("UPDATE products SET category = ? WHERE category = ?");
    $updateProducts->bind_param("ss", $name, $old["name"]);
    $updateCategory = $conn->prepare("UPDATE fish_categories SET name = ? WHERE id = ?");
    $updateCategory->bind_param("si", $name, $id);
    if (!$updateProducts->execute() || !$updateCategory->execute()) { $conn->rollback(); respond(false, "That category name already exists.", [], 409); }
    $conn->commit(); respond(true, "Category renamed.");
}

if ($action === "delete") {
    $check = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE category = ? AND status <> 'deleted'");
    $check->bind_param("s", $old["name"]); $check->execute();
    $usage = stmt_fetch_assoc_compat($check);
    if ((int)($usage["total"] ?? 0) > 0) respond(false, "Change products using this category before deleting it.", [], 409);
    $delete = $conn->prepare("DELETE FROM fish_categories WHERE id = ?");
    $delete->bind_param("i", $id); $delete->execute();
    respond(true, "Category deleted.");
}

respond(false, "Invalid category action.", [], 400);
?>
