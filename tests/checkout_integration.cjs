const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const base='http://127.0.0.1:8765/api/';
const jar={};
async function call(role,path,{json,form}={}){
  const headers={};if(json)headers['Content-Type']='application/json';
  if(jar[role])headers.Cookie=jar[role];
  const response=await fetch(base+path,{method:json||form?'POST':'GET',headers,body:json?JSON.stringify(json):form});
  const cookie=response.headers.get('set-cookie');if(cookie)jar[role]=cookie.split(';')[0];
  const raw=await response.text();let data;try{data=JSON.parse(raw)}catch{throw Error(path+' returned invalid JSON: '+raw.slice(0,300))}
  if(!response.ok||data.success===false)throw Error(path+' '+response.status+': '+JSON.stringify(data));
  return data;
}
function sql(q){return execFileSync('mysql',['-h','127.0.0.1','-uroot','-proot','-N','-e',q,'lorence_betta_fish'],{encoding:'utf8'}).trim()}
async function order(reference,receiptVariant=0){
  const form=new FormData();
  const fields={username:'testbuyer',customerName:'Test Customer',phone:'09123456789',address:'Quezon City, Metro Manila',payment:'GCash',deliveryMode:'delivery',paymentReference:reference,items:JSON.stringify([{productId:'P001',quantity:1}])};
  for(const [k,v] of Object.entries(fields))form.append(k,v);
  const png=Buffer.from(receiptVariant===0?'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/Yz8AAAAASUVORK5CYII=':'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFElEQVR42mP8z8AARMAgYGBgAAAaDgQBBxXPRwAAAABJRU5ErkJggg==','base64');
  form.append('screenshot',new Blob([png],{type:'image/png'}),'proof.png');
  return call('buyer','place_order.php',{form});
}
(async()=>{
  await call('buyer','login.php',{json:{username:'testbuyer',password:'testpass123'}});
  await call('admin','login.php',{json:{username:'admin',password:'testpass123'}});
  const before=Number(sql("SELECT stock FROM products WHERE product_code='P001'"));
  const one=await order('TEST-APPROVE-001');assert.ok(one.order_id);
  let customer=await call('buyer','orders.php');
  let found=customer.orders.find(o=>o.id===one.order_id);
  assert.equal(found?.status,'Pending');assert.equal(found?.payment_status,'Payment Submitted');
  let admin=await call('admin','orders.php');assert.ok(admin.orders.some(o=>o.id===one.order_id),'Admin cannot see order');
  assert.equal(Number(sql("SELECT stock FROM products WHERE product_code='P001'")),before,'Stock must not deduct before approval');
  await call('admin','mark_payment.php',{json:{orderId:one.order_id}});
  customer=await call('buyer','orders.php');found=customer.orders.find(o=>o.id===one.order_id);
  assert.equal(found.status,'Ready to Ship');assert.equal(found.payment_status,'Paid');
  let trackingRequired=false;
  try{await call('admin','update_order.php',{json:{orderId:one.order_id,status:'Shipped'}})}catch(e){trackingRequired=String(e.message).includes('tracking number or link')}
  assert.ok(trackingRequired,'J&T handover must require tracking');
  await call('admin','update_order.php',{json:{orderId:one.order_id,status:'Shipped',trackingNumber:'JT-TEST-123'}});
  customer=await call('buyer','orders.php');found=customer.orders.find(o=>o.id===one.order_id);
  assert.equal(found.status,'Shipped');assert.equal(found.tracking_number,'JT-TEST-123');
  await call('buyer','receive_order.php',{json:{username:'testbuyer',orderId:one.order_id}});
  customer=await call('buyer','orders.php');found=customer.orders.find(o=>o.id===one.order_id);
  assert.equal(found.status,'Order Received');
  assert.equal(Number(sql("SELECT stock FROM products WHERE product_code='P001'")),before-1);
  // Duplicate reference must identify the existing order to its owner, never create another order.
  const countBefore=Number(sql("SELECT COUNT(*) FROM orders WHERE payment_reference='TEST-APPROVE-001'"));
  let duplicateError='';
  try{await order('TEST-APPROVE-001')}catch(e){duplicateError=String(e.message)}
  assert.ok(duplicateError.includes(one.order_id),'Duplicate error must identify original order');
  assert.equal(Number(sql("SELECT COUNT(*) FROM orders WHERE payment_reference='TEST-APPROVE-001'")),countBefore,'Duplicate must not create another order');
  const two=await order('TEST-REJECT-002',1);
  await call('admin','reject_payment.php',{json:{orderId:two.order_id,reason:'Test receipt rejected'}});
  customer=await call('buyer','orders.php');found=customer.orders.find(o=>o.id===two.order_id);
  assert.equal(found.status,'Awaiting Payment');assert.equal(found.payment_status,'Payment Needs Correction');assert.equal(found.payment_rejection_reason,'Test receipt rejected');
  assert.equal(Number(sql("SELECT stock FROM products WHERE product_code='P001'")),before-1,'Reject must not add/deduct stock');
  // Customer corrects the rejected proof on the SAME order.
  const corrected=new FormData();
  corrected.append('username','testbuyer');
  corrected.append('orderId',two.order_id);
  corrected.append('reference','TEST-CORRECTED-003');
  const correctedPng=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/Yz8AAAAASUVORK5CYII=','base64');
  corrected.append('screenshot',new Blob([correctedPng],{type:'image/png'}),'corrected.png');
  let correctionError='';
  try { await call('buyer','submit_payment.php',{form:corrected}); } catch(e) { correctionError=String(e.message); }
  assert.ok(correctionError.includes('Payment Already Used!'),'Previously submitted receipt image must be rejected');
  const correctedDifferent=new FormData();
  correctedDifferent.append('username','testbuyer');
  correctedDifferent.append('orderId',two.order_id);
  correctedDifferent.append('reference','TEST-CORRECTED-003');
  // Use a distinct valid image payload for the corrected receipt.
  const newProof=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/Yz8AAAAASUVORK5CYII=','base64');
  correctedDifferent.append('screenshot',new Blob([newProof],{type:'image/png'}),'corrected-new.png');
  // Reuse must remain blocked until a genuinely different receipt is provided.
  let repeatedError='';
  try { await call('buyer','submit_payment.php',{form:correctedDifferent}); } catch(e) { repeatedError=String(e.message); }
  assert.ok(repeatedError.includes('Payment Already Used!'));
  customer=await call('buyer','orders.php');
  found=customer.orders.find(o=>o.id===two.order_id);
  assert.equal(found.payment_status,'Payment Needs Correction');
  // A different receipt must still be rejected if its reference belongs to another order.
  const reusedReference=new FormData();
  reusedReference.append('username','testbuyer');
  reusedReference.append('orderId',two.order_id);
  reusedReference.append('reference','TEST-APPROVE-001');
  reusedReference.append('screenshot',new Blob([Buffer.concat([correctedPng,Buffer.from('new-image-old-reference')])],{type:'image/png'}),'other-proof.png');
  let reusedReferenceError='';
  try { await call('buyer','submit_payment.php',{form:reusedReference}); } catch(e) { reusedReferenceError=String(e.message); }
  assert.ok(reusedReferenceError.includes('Payment Already Used!'),'Corrected proof cannot reuse another order transaction reference');
  const freshProof=new FormData();
  freshProof.append('username','testbuyer');
  freshProof.append('orderId',two.order_id);
  freshProof.append('reference','TEST-CORRECTED-004');
  // A different valid PNG file, distinct from the two original submitted receipts.
  const freshPng=Buffer.concat([correctedPng,Buffer.from('unique-correction-proof')]);
  freshProof.append('screenshot',new Blob([freshPng],{type:'image/png'}),'fresh-proof.png');
  await call('buyer','submit_payment.php',{form:freshProof});
  customer=await call('buyer','orders.php');
  found=customer.orders.find(o=>o.id===two.order_id);
  assert.equal(found.payment_status,'Payment Submitted');
  assert.equal(found.payment_rejection_reason,null);
  assert.equal(found.payment_reference,'TEST-CORRECTED-004');
  await call('admin','mark_payment.php',{json:{orderId:two.order_id}});
  customer=await call('buyer','orders.php');
  found=customer.orders.find(o=>o.id===two.order_id);
  assert.equal(found.payment_status,'Paid');
  assert.equal(found.status,'Ready to Ship');
  assert.equal(Number(sql("SELECT stock FROM products WHERE product_code='P001'")),before-2,'Corrected order stock deducted exactly once');
  // Concurrent submissions against one rejected order: only one may be accepted.
  const raceCreated=await order('TEST-RACE-ORDER-006',1);
  await call('admin','reject_payment.php',{json:{orderId:raceCreated.order_id,reason:'Correct the proof'}});
  const raceForm=(suffix)=>{
    const form=new FormData();
    form.append('username','testbuyer');
    form.append('orderId',raceCreated.order_id);
    form.append('reference','TEST-RACE-REFERENCE-007');
    form.append('screenshot',new Blob([Buffer.concat([correctedPng,Buffer.from('race-'+suffix)])],{type:'image/png'}),'race-'+suffix+'.png');
    return form;
  };
  const results=await Promise.allSettled([
    call('buyer','submit_payment.php',{form:raceForm('first')}),
    call('buyer','submit_payment.php',{form:raceForm('second')})
  ]);
  assert.equal(results.filter(x=>x.status==='fulfilled').length,1,'Only one concurrent payment correction may succeed');
  customer=await call('buyer','orders.php');
  found=customer.orders.find(o=>o.id===raceCreated.order_id);
  assert.equal(found.payment_status,'Payment Submitted');
  // Two different checkout orders racing on one transaction reference.
  const crossOrderForm=(suffix)=>{
    const form=new FormData();
    const fields={username:'testbuyer',customerName:'Test Customer',phone:'09123456789',address:'Quezon City, Metro Manila',payment:'GCash',deliveryMode:'delivery',paymentReference:'TEST-CROSS-ORDER-008',items:JSON.stringify([{productId:'P001',quantity:1}])};
    for(const [k,v] of Object.entries(fields))form.append(k,v);
    form.append('screenshot',new Blob([Buffer.concat([correctedPng,Buffer.from('cross-order-'+suffix)])],{type:'image/png'}),'cross-'+suffix+'.png');
    return form;
  };
  const crossResults=await Promise.allSettled([
    call('buyer','place_order.php',{form:crossOrderForm('first')}),
    call('buyer','place_order.php',{form:crossOrderForm('second')})
  ]);
  assert.equal(crossResults.filter(x=>x.status==='fulfilled').length,1,'One reference must create only one order under concurrency');
  assert.equal(Number(sql("SELECT COUNT(*) FROM orders WHERE payment_reference='TEST-CROSS-ORDER-008'")),1,'No duplicate transaction reference may be persisted');
  console.log('PASS: customer/admin visibility, pending, approve, reject, stock, payment correction reason');
})().catch(e=>{console.error(e);process.exitCode=1});
