<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/shipping_rates.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
 http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;
}
$data=json_decode(file_get_contents('php://input'),true);
$mode=is_array($data)?($data['deliveryMode']??''):'';
$address=is_array($data)?($data['address']??''):'';
if (!is_string($address) || strlen($address)>2000 || !in_array($mode,['delivery','pickup'],true)) {
 http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid delivery information']); exit;
}
if ($mode==='pickup') { echo json_encode(['success'=>true,'shipping'=>0,'courier'=>null]); exit; }
$fee=regional_shipping_fee($address);
if ($fee===null) {
 http_response_code(422); echo json_encode(['success'=>false,'message'=>'Please select an address with a recognizable Philippine province or Metro Manila city.']); exit;
}
echo json_encode(['success'=>true,'shipping'=>$fee,'courier'=>'J&T Express']);
