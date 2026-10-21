<?php

function save_admin_base64_image($data, $subfolder, $prefix) {
    if (!is_string($data) || !preg_match('~^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$~D', $data, $m)) respond(false, "Invalid image format.", [], 400);
    if (strlen($m[2]) > 7 * 1024 * 1024) respond(false, "Image exceeds 5 MB.", [], 400);
    $binary = base64_decode($m[2], true);
    if ($binary === false || strlen($binary) > 5 * 1024 * 1024 || strlen($binary) === 0) respond(false, "Invalid image data.", [], 400);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_buffer($finfo, $binary);
    finfo_close($finfo);
    $ext = ["image/jpeg"=>"jpg", "image/png"=>"png", "image/webp"=>"webp"][$mime] ?? null;
    if ($ext === null) respond(false, "Only JPG, PNG and WEBP images are allowed.", [], 400);
    $folder = dirname(__DIR__) . "/images/" . $subfolder;
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) respond(false, "Could not create image folder.", [], 500);
    $filename = $prefix . "_" . bin2hex(random_bytes(10)) . "." . $ext;
    if (file_put_contents($folder . "/" . $filename, $binary, LOCK_EX) === false) respond(false, "Could not save image.", [], 500);
    return "images/" . $subfolder . "/" . $filename;
}

function save_product_image($file) {
    if (!isset($file) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        respond(false, "Image upload failed.", [], 400);
    }

    if ($file["size"] > 5 * 1024 * 1024) {
        respond(false, "Image is too large. Maximum size is 5MB.", [], 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    $allowed = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (!isset($allowed[$mime])) {
        respond(false, "Only JPG, PNG and WEBP images are allowed.", [], 400);
    }

    $extension = $allowed[$mime];

    $filename =
        "betta_" .
        date("Ymd_His") . "_" .
        bin2hex(random_bytes(6)) .
        "." . $extension;

    $folder = dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" .
              DIRECTORY_SEPARATOR . "products";

    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    $destination = $folder . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        respond(false, "Could not save the uploaded image.", [], 500);
    }

    return "images/products/" . $filename;
}
?>