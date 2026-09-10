<?php

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