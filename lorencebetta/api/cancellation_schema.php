<?php
function ensure_cancellation_requests($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS cancellation_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        customer_id INT NOT NULL,
        reason VARCHAR(120) NOT NULL,
        explanation VARCHAR(1000) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Pending Review',
        admin_reason VARCHAR(1000) NULL,
        refund_reference VARCHAR(150) NULL,
        refund_proof VARCHAR(255) NULL,
        refund_amount DECIMAL(12,2) NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        refunded_at DATETIME NULL,
        UNIQUE KEY unique_order(order_id),
        UNIQUE KEY unique_refund_ref(refund_reference),
        INDEX(customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql)) throw new Exception("Unable to prepare cancellation requests.");
}
