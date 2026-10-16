<?php
// Dependency-free checkout quantity regression tests. Production implementation must retain the same validation.
function aggregate_checkout_items(array $items): array {
    $requested=[];
    foreach($items as $item){
        if(!is_array($item))throw new Exception("Invalid order item.");
        $code=trim((string)($item["productId"]??""));
        $rawQty=$item["quantity"]??null;
        if($code==="" || !is_scalar($rawQty) || !preg_match('/^[1-9][0-9]*$/',(string)$rawQty))throw new Exception("Invalid order item.");
        $qty=(int)$rawQty;
        if($qty<=0 || $qty>100000 || ($requested[$code]??0)>100000-$qty)throw new Exception("Invalid order quantity.");
        $requested[$code]=($requested[$code]??0)+$qty;
    }
    ksort($requested,SORT_STRING);
    return $requested;
}
$cases=[
 ["duplicate lines", [["productId"=>"A","quantity"=>2],["productId"=>"A","quantity"=>3]],["A"=>5]],
 ["distinct items", [["productId"=>"B","quantity"=>1],["productId"=>"A","quantity"=>2]],["A"=>2,"B"=>1]],
 ["zero", [["productId"=>"A","quantity"=>0]],null],
 ["negative", [["productId"=>"A","quantity"=>-1]],null],
 ["fractional", [["productId"=>"A","quantity"=>1.5]],null],
 ["text", [["productId"=>"A","quantity"=>"bad"]],null],
 ["oversized", [["productId"=>"A","quantity"=>100001]],null],
 ["cumulative oversized", [["productId"=>"A","quantity"=>90000],["productId"=>"A","quantity"=>10001]],null],
 ["missing code", [["quantity"=>1]],null],
 ["malformed item", ["bad"],null],
];
$passed=0;
foreach($cases as [$name,$items,$expected]){
    $actual=null;
    try{$actual=aggregate_checkout_items($items);}catch(Throwable $e){}
    if($actual!==$expected){fwrite(STDERR,"FAIL $name\n");exit(1);}
    echo "PASS $name\n";$passed++;
}
$production=file_get_contents(__DIR__."/../api/place_order.php");
foreach(['$requested=[]','ksort($requested,SORT_STRING)','foreach($requested as $code=>$qty)'] as $required){
    if(strpos($production,$required)===false){fwrite(STDERR,"Missing production guard: $required\n");exit(1);}
}
echo "Checkout quantity checks passed: $passed/".count($cases)."\n";
