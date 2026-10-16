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
async function order(reference){
  const form=new FormData();
  const fields={username:'testbuyer',customerName:'Test Customer',phone:'09123456789',address:'Quezon City, Metro Manila',payment:'GCash',deliveryMode:'delivery',paymentReference:reference,items:JSON.stringify([{productId:'P001',quantity:1}])};
  for(const [k,v] of Object.entries(fields))form.append(k,v);
  const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/Yz8AAAAASUVORK5CYII=','base64');
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
  assert.equal(found.status,'Confirmed');assert.equal(found.payment_status,'Paid');
  assert.equal(Number(sql("SELECT stock FROM products WHERE product_code='P001'")),before-1);
  // Duplicate reference must identify the existing order to its owner, never create another order.
  const countBefore=Number(sql("SELECT COUNT(*) FROM orders WHERE payment_reference='TEST-APPROVE-001'"));
  let duplicateError='';
  try{await order('TEST-APPROVE-001')}catch(e){duplicateError=String(e.message)}
  assert.ok(duplicateError.includes(one.order_id),'Duplicate error must identify original order');
  assert.equal(Number(sql("SELECT COUNT(*) FROM orders WHERE payment_reference='TEST-APPROVE-001'")),countBefore,'Duplicate must not create another order');
  const two=await order('TEST-REJECT-002');
  await call('admin','reject_payment.php',{json:{orderId:two.order_id,reason:'Test receipt rejected'}});
  customer=await call('buyer','orders.php');found=customer.orders.find(o=>o.id===two.order_id);
  assert.equal(found.status,'Cancelled');assert.equal(found.payment_rejection_reason,'Test receipt rejected');
  assert.equal(Number(sql("SELECT stock FROM products WHERE product_code='P001'")),before-1,'Reject must not add/deduct stock');
  console.log('PASS: customer/admin visibility, pending, approve, reject, stock, cancellation reason');
})().catch(e=>{console.error(e);process.exitCode=1});
