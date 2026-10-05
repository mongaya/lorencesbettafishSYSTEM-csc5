<?php

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/stock_reservations.php";
ensure_order_flow_columns($conn);
release_expired_reservations($conn);

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

$courierColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'courier'");
if ($courierColumn && $courierColumn->num_rows === 0) {
    if (!$conn->query("ALTER TABLE orders ADD COLUMN courier VARCHAR(30) NULL AFTER address")) respond(false, "Unable to add courier field.", [], 500);
}
$trackingColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'tracking_url'");
if ($trackingColumn && $trackingColumn->num_rows === 0) {
    if (!$conn->query("ALTER TABLE orders ADD COLUMN tracking_url VARCHAR(500) NULL AFTER courier")) respond(false, "Unable to add tracking field.", [], 500);
}
$conn->query("ALTER TABLE orders MODIFY COLUMN status ENUM('Pending Shipping Fee','Awaiting Payment','Pending','Confirmed','Preparing','Ready for Pickup','Ready for Delivery','Lalamove Booked','Ready to Ship','Shipped','Out for Delivery','Delivered','Order Received','Picked Up','Cancelled') NOT NULL DEFAULT 'Pending Shipping Fee'");

$sql = "
    SELECT
        o.id,
        o.order_code,
        u.username,
        o.customer_name,
        o.phone,
        o.address,
        o.courier,
        o.tracking_url,
        o.tracking_number,
        o.admin_seen_at,
        o.subtotal,
        o.shipping,
        o.total,
        o.payment_method,
        o.payment_status,
        o.payment_reference,
        o.payment_screenshot,
        o.payment_rejection_reason,
        o.reservation_expires_at,
        o.stock_deducted,
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

    $hist=$conn->prepare("SELECT status,DATE_FORMAT(changed_at, '%Y-%m-%d %H:%i:%s') changed_at FROM order_status_history WHERE order_id=? ORDER BY id ASC");
    $hist->bind_param("i",$orderId);$hist->execute();$history=stmt_fetch_all_assoc_compat($hist);$hist->close();

    $orders[] = [
        "id" => $row["order_code"],
        "username" => $row["username"],
        "customerName" => $row["customer_name"],
        "phone" => $row["phone"],
        "address" => $row["address"],
        "courier" => $row["courier"],
        "tracking_url" => $row["tracking_url"],
        "tracking_number" => $row["tracking_number"],
        "is_new" => empty($row["admin_seen_at"]),
        "items" => $items,
        "subtotal" => (float)$row["subtotal"],
        "shipping" => (float)$row["shipping"],
        "total" => (float)$row["total"],
        "payment" => $row["payment_method"],
        "payment_status" => $row["payment_status"],
        "payment_reference" => $row["payment_reference"],
        "payment_screenshot" => $row["payment_screenshot"],
        "payment_rejection_reason" => $row["payment_rejection_reason"],
        "reservation_expires_at" => $row["reservation_expires_at"],
        "stock_deducted" => (bool)$row["stock_deducted"],
        "status_history" => $history,
        "shipping_confirmed" => !empty($row["shipping_confirmed_at"]),
        "status" => $row["status"],
        "date" => $row["order_date"]
    ];
}

respond(true, "", ["orders" => $orders]);
