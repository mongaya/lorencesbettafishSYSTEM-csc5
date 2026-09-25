<?php

require_once __DIR__ . "/db.php";

$column = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_screenshot'");

if (!$column) {
    respond(false, "Unable to check orders table.", [], 500);
}

if ($column->num_rows === 0) {
    $alter = $conn->query(
        "ALTER TABLE orders ADD COLUMN payment_screenshot VARCHAR(255) NULL AFTER payment_reference"
    );

    if (!$alter) {
        respond(false, "Unable to update orders table.", [], 500);
    }
}

$sql = "
    SELECT
        o.id,
        o.order_code,
        u.username,
        o.customer_name,
        o.phone,
        o.address,
        o.subtotal,
        o.shipping,
        o.total,
        o.payment_method,
        o.payment_status,
        o.payment_reference,
        o.payment_screenshot,
        o.shipping_confirmed_at,
        o.status,
        DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i:%s') AS order_date
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    ORDER BY o.id DESC
";

$orderResult = $conn->query($sql);

if (!$orderResult) {
    respond(false, "Unable to load orders.", [], 500);
}

$orders = [];

while ($row = $orderResult->fetch_assoc()) {
    $orderId = (int)$row["id"];

    $stmt = $conn->prepare(
        "SELECT
            oi.product_id,
            oi.product_name AS name,
            oi.price,
            oi.quantity,
            p.image
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.id ASC"
    );

    if (!$stmt) {
        respond(false, "Unable to prepare order items.", [], 500);
    }

    $stmt->bind_param("i", $orderId);

    if (!$stmt->execute()) {
        $stmt->close();
        respond(false, "Unable to load order items.", [], 500);
    }

    $itemRows = stmt_fetch_all_assoc_compat($stmt);
    $stmt->close();

    $items = [];

    foreach ($itemRows as $item) {
        $items[] = [
            "productId" => (int)$item["product_id"],
            "name" => $item["name"],
            "price" => (float)$item["price"],
            "quantity" => (int)$item["quantity"],
            "image" => $item["image"]
        ];
    }

    $orders[] = [
        "id" => $row["order_code"],
        "username" => $row["username"],
        "customerName" => $row["customer_name"],
        "phone" => $row["phone"],
        "address" => $row["address"],
        "items" => $items,
        "subtotal" => (float)$row["subtotal"],
        "shipping" => (float)$row["shipping"],
        "total" => (float)$row["total"],
        "payment" => $row["payment_method"],
        "payment_status" => $row["payment_status"],
        "payment_reference" => $row["payment_reference"],
        "payment_screenshot" => $row["payment_screenshot"],
        "shipping_confirmed" => !empty($row["shipping_confirmed_at"]),
        "status" => $row["status"],
        "date" => $row["order_date"]
    ];
}

respond(true, "", ["orders" => $orders]);
