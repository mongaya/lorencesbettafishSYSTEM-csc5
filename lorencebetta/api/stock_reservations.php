<?php
function ensure_order_flow_columns($conn){
    $cols=[
        "reservation_expires_at"=>"DATETIME NULL",
        "stock_deducted"=>"TINYINT(1) NOT NULL DEFAULT 1",
        "payment_rejection_reason"=>"VARCHAR(500) NULL"
    ];
    foreach($cols as $name=>$definition){
        $q=$conn->query("SHOW COLUMNS FROM orders LIKE '".$conn->real_escape_string($name)."'");
        if($q && $q->num_rows===0){
            if(!$conn->query("ALTER TABLE orders ADD COLUMN `$name` $definition")) throw new Exception("Unable to prepare order flow.");
        }
    }
    if(!$conn->query("CREATE TABLE IF NOT EXISTS order_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        status VARCHAR(60) NOT NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) throw new Exception("Unable to prepare order history.");
}
function log_order_status($conn,$orderId,$status){
    $s=$conn->prepare("INSERT INTO order_status_history(order_id,status,changed_at) VALUES(?,?,NOW())");
    $s->bind_param("is",$orderId,$status);$s->execute();$s->close();
}
function release_expired_reservations($conn){
    $conn->query("UPDATE orders SET reservation_expires_at=NULL,updated_at=NOW()
        WHERE stock_deducted=0 AND payment_status IN ('Unpaid','Payment Needs Correction')
        AND reservation_expires_at IS NOT NULL AND reservation_expires_at<NOW()");
}
function reserved_quantity($conn,$productId){
    $s=$conn->prepare("SELECT COALESCE(SUM(oi.quantity),0) q
        FROM order_items oi JOIN orders o ON o.id=oi.order_id
        WHERE oi.product_id=? AND o.stock_deducted=0 AND o.status<>'Cancelled'
        AND (o.payment_status='Payment Submitted' OR o.status='Pending Shipping Fee'
             OR (o.reservation_expires_at IS NOT NULL AND o.reservation_expires_at>NOW()))");
    $s->bind_param("i",$productId);$s->execute();$r=stmt_fetch_assoc_compat($s);$s->close();
    return (int)($r["q"]??0);
}
?>