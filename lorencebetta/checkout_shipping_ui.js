/* Checkout delivery mode, courier visibility and server-priced order summary. */
(function(){
  let quoteSequence=0;
  const money=n=>'₱'+Number(n).toFixed(2);
  const selectedMode=()=>document.querySelector('input[name="deliveryMode"]:checked')?.value||'delivery';

  renderCheckoutCouriers=function(){
    const el=document.getElementById('checkoutCourierOptions');
    if(!el)return;
    const mode=selectedMode();
    el.innerHTML='<label class="payment-option"><input type="radio" name="deliveryMode" value="delivery" '+(mode==='delivery'?'checked':'')+' onchange="renderCheckout()"> Delivery</label>'+
      '<label class="payment-option"><input type="radio" name="deliveryMode" value="pickup" '+(mode==='pickup'?'checked':'')+' onchange="renderCheckout()"> Pick Up</label>';
  };

  renderCheckout=function(){
    const el=document.getElementById('checkoutSummary');
    if(!el)return;
    renderCheckoutAddress();
    renderCheckoutCouriers();
    const mode=selectedMode(),a=selectedAddress(),subtotal=calculateCartTotal(true);
    const courier=document.getElementById('checkoutCourierSection');
    if(courier)courier.hidden=mode!=='delivery';
    const note=document.getElementById('checkoutDeliveryNote');
    if(note)note.textContent=mode==='pickup'?'Pick Up: no shipping fee.':'Courier: J&T Express';
    let html='';
    getSelectedCartItems().forEach(i=>{
      const p=products.find(x=>String(x.id)===String(i.productId));
      if(p)html+='<div class="summary-row"><span>'+escapeHTML(p.name)+' × '+Number(i.quantity)+'</span><strong>'+money(Number(p.price)*Number(i.quantity))+'</strong></div>';
    });
    html+='<hr><div class="summary-row"><span>Subtotal</span><strong>'+money(subtotal)+'</strong></div>';
    const request=++quoteSequence;
    const button=document.getElementById('checkoutPlaceOrder');
    if(button)button.disabled=mode==='delivery';
    if(mode==='pickup'){
      el.innerHTML=html+'<div class="summary-row"><span>Shipping</span><strong>FREE</strong></div><div class="summary-total">Final total: '+money(subtotal)+'</div>';
      if(button)button.disabled=false;
      return;
    }
    el.innerHTML=html+'<div class="summary-row"><span>Shipping</span><strong>Calculating...</strong></div><div class="summary-total">Calculating final total...</div>';
    if(!a){el.innerHTML=html+'<p class="error">Select a delivery address to calculate shipping.</p>';return;}
    apiRequest('shipping_quote.php',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({deliveryMode:'delivery',address:addressSnapshot(a)})
    }).then(data=>{
      if(request!==quoteSequence||selectedMode()!=='delivery')return;
      const shipping=Number(data.shipping);
      if(!Number.isFinite(shipping))throw Error('Invalid shipping quote.');
      el.innerHTML=html+'<div class="summary-row"><span>Shipping (J&T Express)</span><strong>'+money(shipping)+'</strong></div><div class="summary-total">Final total: '+money(subtotal+shipping)+'</div>';
      if(button)button.disabled=false;
    }).catch(error=>{
      if(request!==quoteSequence||selectedMode()!=='delivery')return;
      el.innerHTML=html+'<div class="summary-row"><span>Shipping</span><strong>Unavailable</strong></div><p class="error">'+escapeHTML(error.message||'Unable to calculate shipping.')+'</p>';
      if(button)button.disabled=true;
    });
  };

  const oldChoose=chooseCheckoutAddress;
  chooseCheckoutAddress=function(id){oldChoose(id);renderCheckout();};
})();
