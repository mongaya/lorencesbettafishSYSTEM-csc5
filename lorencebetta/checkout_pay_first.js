/* Pay-first checkout: existing admin payment settings, proof, and server-priced shipping. */
(function(){
  let quoteReady=false, quoteTotal=null, quoteVersion=0, sending=false;
  const $=id=>document.getElementById(id);
  const payment=()=>document.querySelector('input[name="payment"]:checked')?.value||'GCash';
  const valid=()=>!!$('checkoutPaymentReference')?.value.trim()&&!!$('checkoutPaymentScreenshot')?.files?.length;
  function refreshButton(){
    const btn=$('checkoutPlaceOrder');
    if(btn)btn.disabled=sending||!quoteReady||!valid();
  }
  function paymentInfo(){
    const method=payment(), data=paymentSettings?.[method]||{};
    const name=method==='GCash'?(data.account_name||data.merchant_name):(data.account_name||'');
    const number=method==='GCash'?(data.account_number||data.gcash_number):(data.account_number||'');
    const qr=data.qr_image||'';
    const el=$('checkoutPaymentDetails');if(!el)return;
    const ready=!!name&&!!number&&!!qr;
    el.innerHTML=ready?'<strong>'+escapeHTML(method)+'</strong><p>Account: '+escapeHTML(name)+'</p><p>Number: '+escapeHTML(number)+'</p><img style="width:220px;max-width:100%;display:block;margin:12px auto" alt="Payment QR" src="'+escapeHTML(qr)+'"><p>Pay the exact final total displayed in your order summary, then upload the receipt below.</p>':'<p>Payment account information is not yet available for '+escapeHTML(method)+'. Contact the shop before paying.</p>';
    el.dataset.ready=ready?'yes':'no';refreshButton();
  }
  const previous=renderCheckout;
  renderCheckout=function(){
    quoteReady=false;quoteTotal=null;quoteVersion++;previous.apply(this,arguments);
    paymentInfo();refreshButton();
  };
  const originalRequest=window.apiRequest;
  const observer=new MutationObserver(()=>{
    const summary=$('checkoutSummary');if(!summary)return;
    const text=summary.textContent||'';
    const m=text.match(/Final total:\s*₱([\d,]+\.\d{2})/);
    quoteReady=!!m;quoteTotal=m?Number(m[1].replace(/,/g,'')):null;
    refreshButton();
  });
  document.addEventListener('DOMContentLoaded',()=>{
    if($('checkoutSummary'))observer.observe($('checkoutSummary'),{childList:true,subtree:true,characterData:true});
    document.querySelectorAll('input[name="payment"]').forEach(x=>x.addEventListener('change',paymentInfo));
    ['checkoutPaymentReference','checkoutPaymentScreenshot'].forEach(id=>$(id)?.addEventListener('input',refreshButton));
    paymentInfo();refreshButton();
  });
  placeOrder=async function(){
    if(sending)return;
    const selected=getSelectedCartItems(),a=selectedAddress();
    if(!selected.length||!a||!quoteReady||!Number.isFinite(quoteTotal)||!valid()||$('checkoutPaymentDetails')?.dataset.ready!=='yes'){
      showToast('Complete the payment, reference number and receipt before placing the order.');return;
    }
    const file=$('checkoutPaymentScreenshot').files[0];
    if(file.size>5*1024*1024||!['image/jpeg','image/png','image/webp'].includes(file.type)){showToast('Use a JPG, PNG or WEBP receipt up to 5MB.');return;}
    const mode=document.querySelector('input[name="deliveryMode"]:checked')?.value||'delivery';
    const data=new FormData();
    Object.entries({username:currentUser.username,customerName:(a.first_name+' '+a.last_name).trim(),phone:a.phone,address:addressSnapshot(a),payment:payment(),deliveryMode:mode,paymentReference:$('checkoutPaymentReference').value.trim(),items:JSON.stringify(selected.map(i=>({productId:i.productId,quantity:Number(i.quantity)})))}).forEach(([k,v])=>data.append(k,v));
    data.append('screenshot',file);
    sending=true;refreshButton();
    try{
      await apiRequest('place_order.php',{method:'POST',body:data});
      const ids=new Set(selected.map(i=>String(i.productId)));
      saveCart(getCart().filter(i=>!ids.has(String(i.productId))));
      updateCartCount();await loadProducts();await loadOrders();
      showToast('Payment submitted. Waiting for admin verification.');
      showCustomerPage('orders');renderCustomerOrders();renderProducts();renderHomeProducts();renderStock();renderAdminDashboard();
    }catch(e){showToast(e.message);}
    finally{sending=false;refreshButton();}
  };
})();
