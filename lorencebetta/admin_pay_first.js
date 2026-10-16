/* Payment verification controls for submitted pay-first orders. */
(function(){
  const oldView=viewOrder;
  viewOrder=function(id){
    oldView.apply(this,arguments);
    const o=(orders||[]).find(x=>String(x.id)===String(id));
    const details=document.getElementById('orderDetails');
    if(!o||!details)return;
    if(o.payment_status==='Payment Submitted'&&o.status!=='Cancelled'){
      const panel=document.createElement('div');panel.className='admin-shipping-box';
      panel.innerHTML='<h4>Payment Verification</h4><p>Check the actual bank or wallet transaction before approving. A screenshot alone is not proof of payment.</p><button class="primary-btn" id="payFirstApprove">Approve Payment</button> <button class="secondary-btn" id="payFirstReject">Reject Payment</button>';
      details.appendChild(panel);
      panel.querySelector('#payFirstApprove').addEventListener('click',()=>markPaymentPaid(id));
      panel.querySelector('#payFirstReject').addEventListener('click',async()=>{
        const reason=prompt('Reason for rejecting this payment (required):');
        if(!reason||!reason.trim())return;
        try{
          await apiRequest('reject_payment.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({orderId:id,reason:reason.trim()})});
          await loadOrders();renderAdminOrders();renderAdminDashboard();viewOrder(id);
          showToast('Payment rejected and order cancelled.');
        }catch(e){showToast(e.message);}
      });
    }
    if(o.status==='Cancelled'&&o.payment_rejection_reason){
      const note=document.createElement('p');note.className='payment-note';note.textContent='Payment rejection reason: '+o.payment_rejection_reason;details.appendChild(note);
    }
  };
})();
