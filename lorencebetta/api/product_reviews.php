<?php
require_once "db.php";

$conn->query("CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating TINYINT NOT NULL,
    review_text TEXT NULL,
    review_image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_product (user_id, product_id),
    INDEX idx_product_reviews_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function review_image_path($file) {
    if (!isset($file) || ($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file["error"] !== UPLOAD_ERR_OK) respond(false, "Review image upload failed.", [], 400);
    if ($file["size"] > 5 * 1024 * 1024) respond(false, "Review image must be 5MB or smaller.", [], 400);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);
    $allowed = ["image/jpeg"=>"jpg","image/png"=>"png","image/webp"=>"webp"];
    if (!isset($allowed[$mime])) respond(false, "Only JPG, PNG and WEBP review images are allowed.", [], 400);

    $folder = dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "reviews";
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) respond(false, "Could not create the review image folder.", [], 500);

    $guard = $folder . DIRECTORY_SEPARATOR . ".htaccess";
    if (!file_exists($guard)) {
        @file_put_contents($guard, "<FilesMatch \"\\.(php|php[0-9]*|phtml|phar)$\">\n    Require all denied\n</FilesMatch>\n");
    }

    $filename = "review_" . date("Ymd_His") . "_" . bin2hex(random_bytes(6)) . "." . $allowed[$mime];
    if (!move_uploaded_file($file["tmp_name"], $folder . DIRECTORY_SEPARATOR . $filename)) {
        respond(false, "Could not save the review image.", [], 500);
    }
    return "/images/reviews/" . $filename;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $productId = (int)($_POST["productId"] ?? 0);
    $rating = (int)($_POST["rating"] ?? 0);
    $reviewText = trim($_POST["reviewText"] ?? "");

    if ($username === "" || $productId <= 0 || $rating < 1 || $rating > 5) {
        respond(false, "Please choose a rating from 1 to 5 stars.", [], 400);
    }

    $u = $conn->prepare("SELECT id FROM users WHERE username=? AND role='customer' AND status='active' LIMIT 1");
    $u->bind_param("s", $username);
    $u->execute();
    $user = stmt_fetch_assoc_compat($u);
    if (!$user) respond(false, "Customer account not found.", [], 404);
    $userId = (int)$user["id"];

    $eligible = $conn->prepare("SELECT o.id
        FROM orders o
        INNER JOIN order_items oi ON oi.order_id=o.id
        WHERE o.user_id=? AND oi.product_id=? AND o.status IN ('Delivered','Picked Up')
        LIMIT 1");
    $eligible->bind_param("ii", $userId, $productId);
    $eligible->execute();
    if (!stmt_has_rows_compat($eligible)) {
        respond(false, "You can rate this product after your order has been delivered or picked up.", [], 403);
    }

    $check = $conn->prepare("SELECT id FROM product_reviews WHERE user_id=? AND product_id=? LIMIT 1");
    $check->bind_param("ii", $userId, $productId);
    $check->execute();
    if (stmt_has_rows_compat($check)) respond(false, "You have already rated this product.", [], 409);

    $image = review_image_path($_FILES["reviewImage"] ?? null);
    $stmt = $conn->prepare("INSERT INTO product_reviews(user_id,product_id,rating,review_text,review_image,created_at) VALUES(?,?,?,?,?,NOW())");
    $stmt->bind_param("iiiss", $userId, $productId, $rating, $reviewText, $image);
    $stmt->execute();

    respond(true, "Thank you for your product rating.");
}

$sql = "SELECT pr.id, pr.product_id, pr.rating, pr.review_text, pr.review_image,
               DATE_FORMAT(pr.created_at,'%Y-%m-%d %H:%i:%s') AS created_at,
               u.name AS customer_name
        FROM product_reviews pr
        INNER JOIN users u ON u.id=pr.user_id
        ORDER BY pr.id DESC";
$result = $conn->query($sql);
if (!$result) respond(false, "Unable to load product ratings.", [], 500);

$reviews = [];
$summary = [];
while ($row = $result->fetch_assoc()) {
    $pid = (int)$row["product_id"];
    $reviews[] = [
        "id" => (int)$row["id"],
        "productId" => $pid,
        "rating" => (int)$row["rating"],
        "reviewText" => $row["review_text"],
        "reviewImage" => $row["review_image"],
        "customerName" => $row["customer_name"],
        "createdAt" => $row["created_at"]
    ];
    if (!isset($summary[$pid])) $summary[$pid] = ["total"=>0,"count"=>0];
    $summary[$pid]["total"] += (int)$row["rating"];
    $summary[$pid]["count"]++;
}

$out = [];
foreach ($summary as $pid => $s) {
    $out[(string)$pid] = [
        "average" => round($s["total"] / $s["count"], 1),
        "count" => $s["count"]
    ];
}

respond(true, "", ["reviews"=>$reviews, "summary"=>$out]);
?>