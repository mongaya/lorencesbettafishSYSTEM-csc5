(function(){
  const previous=renderAdminDashboard;
  function cleanup(){
    const dash=document.getElementById('admin-dashboard');
    if(!dash)return;
    // v69: legacy dashboard renderers write to these IDs after every refresh.
    // Do not remove the nodes: CSS hides their containing panels consistently.
    // Keep actionable work visible: payment verification, shipping, cancellations and refunds.
    const list=document.getElementById('proAttentionV27');
    if(list){
      const all=Array.isArray(orders)?orders:[];
      const pending=all.filter(o=>o.cancellation?.status==='Pending Review').length;
      const refunds=all.filter(o=>o.cancellation?.status==='Refund Pending').length;
      const activeOrder=o=>o.status!=='Cancelled'&&o.cancellation?.status!=='Pending Review'&&o.cancellation?.status!=='Refund Pending'&&o.cancellation?.status!=='Refunded'&&o.cancellation?.status!=='Approved';
      const needs=all.filter(o=>activeOrder(o)&&o.payment_status==='Payment Submitted').length;
      const ready=all.filter(o=>activeOrder(o)&&o.payment_status==='Paid'&&!['Shipped','Out for Delivery','Delivered','Order Received','Picked Up','Lalamove Booked'].includes(o.status)&&!o.tracking_number&&!o.tracking_url).length;
      const actions=[['Payment Verification',needs,'orders'],['Ready for Shipment',ready,'courier-shipping'],['Cancellation Requests',pending,'cancellations'],['Pending Refunds',refunds,'cancellations']];
      const active=actions.reduce((sum,a)=>sum+a[1],0);
      const total=document.getElementById('proPendingV27');if(total)total.textContent=String(active);
      const escape=typeof escapeHTML==='function'?escapeHTML:(v=>String(v));
      list.innerHTML=actions.map(([label,count,page],i)=>'<button type="button" class="pro-attention-item-v27 dash-action-v68" data-dash-page="'+page+'"><div class="pro-attention-dot-v27">'+(i+1)+'</div><div><b>'+count+'</b><span>'+escape(label)+'</span></div><strong aria-hidden="true">›</strong></button>').join('');
    }
  }
  renderAdminDashboard=function(){previous();cleanup()};
  document.addEventListener('click',e=>{
    const action=e.target.closest('[data-dash-page]');
    if(!action)return;
    const page=action.dataset.dashPage;
    const btn=document.querySelector('#adminSidebar .nav-btn[onclick*="'+page+'"]');
    showAdminPage(page,btn);
  });
})();