<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'Method not allowed.', [], 405);
}
$user=require_user_session($conn);
if (($user['role'] ?? '') !== 'customer') {
    respond(false, 'Customer login required.', [], 403);
}
if (!isset($_FILES['profile_image']) || !is_array($_FILES['profile_image'])) {
    respond(false, 'Choose a picture before clicking Update Photo.', [], 400);
}
$photo=$_FILES['profile_image'];
if (($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respond(false, 'Photo upload failed. Please try another image under 5 MB.', [], 400);
}
$size=(int)($photo['size'] ?? 0);
if ($size<1 || $size>5*1024*1024) {
    respond(false, 'Profile photo must be smaller than 5 MB.', [], 400);
}
$tmp=(string)($photo['tmp_name'] ?? '');
if (!$tmp || !is_uploaded_file($tmp)) {
    respond(false, 'Invalid uploaded file.', [], 400);
}
$details=@getimagesize($tmp);
$mime=$details['mime'] ?? '';
$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if (!isset($types[$mime]) || ($details[0] ?? 0)>5000 || ($details[1] ?? 0)>5000) {
    respond(false, 'Only valid JPG, PNG, or WEBP images up to 5000 pixels are allowed.', [], 400);
}
$folder=dirname(__DIR__).'/uploads/profile_photos';
if (!is_dir($folder) && !@mkdir($folder,0755,true) && !is_dir($folder)) {
    respond(false, 'Photo storage unavailable. Contact administrator.', [], 503);
}
$id=(int)$_SESSION['user_id'];
$extension=$types[$mime];
$base='customer_'.$id;
$dest=$folder.'/'.$base.'.'.$extension;
if (!@move_uploaded_file($tmp,$dest)) {
    respond(false, 'Unable to save profile photo on the hosting server.', [], 503);
}
@chmod($dest,0644);
foreach (['jpg','png','webp'] as $ext) {
    if ($ext!==$extension && is_file($folder.'/'.$base.'.'.$ext)) @unlink($folder.'/'.$base.'.'.$ext);
}
$url='uploads/profile_photos/'.$base.'.'.$extension.'?v='.filemtime($dest);
respond(true,'Profile picture updated.',['profile_image'=>$url]);
?>