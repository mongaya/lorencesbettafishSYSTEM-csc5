<?php
require_once "db.php";
$result=$conn->query("SELECT id,name,username,email,phone,address,DATE_FORMAT(created_at,'%m/%d/%Y') AS registered FROM users WHERE role='customer' ORDER BY id DESC");
$customers=[];
while($row=$result->fetch_assoc()){
    $id=(int)$row['id'];
    $photo='';
    $base=dirname(__DIR__).'/uploads/profile_photos/customer_'.$id.'.';
    foreach(['jpg','png','webp'] as $ext){
        if(is_file($base.$ext)){
            $photo='uploads/profile_photos/customer_'.$id.'.'.$ext.'?v='.filemtime($base.$ext);
            break;
        }
    }
    $customers[]=['id'=>$id,'name'=>$row['name'],'username'=>$row['username'],'email'=>$row['email'],'phone'=>$row['phone'],'address'=>$row['address'],'registered'=>$row['registered'],'profile_image'=>$photo];
}
respond(true,"",["customers"=>$customers]);
?>