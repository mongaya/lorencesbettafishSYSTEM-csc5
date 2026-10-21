(function(){
  const previous=renderAdminDashboard;
  function cleanup(){
    const dash=document.getElementById('admin-dashboard');
    if(!dash)return;
    // Hide all legacy duplicate widgets, while preserving their data hooks.
    for(const selector of ['#dashLowerV9','#recentOrders','.dash-lower-v9','#proRecentV27']){
      dash.querySelectorAll(selector).forEach(el=>{const panel=el.closest('.pro-panel-v27')||el.closest('.card');if(panel&&selector==='#proRecentV27')panel.style.display='none';else if(panel&&selector==='#recentOrders')panel.style.display='none';else el.style.display='none'});
    }
    const pro=dash.querySelector('#proSalesV27');
    if(pro){
      const parent=pro.closest('.pro-dashboard-v27')||pro.parentElement;
      const recent=dash.querySelector('#proRecentV27');
      if(recent){const panel=recent.closest('.pro-panel-v27');if(panel)panel.remove()}
    }
    // The old Order Status Overview and its legacy chart are redundant to shipping management.
    const status=dash.querySelector('#proStatusV27');
    if(status){const panel=status.closest('.pro-panel-v27');if(panel)panel.remove()}
    const legacy=dash.querySelector('#dashLowerV9');if(legacy)legacy.remove();
    // Keep actionable work visible: payment verification, shipping, cancellations and refunds.
    const list=document.getElementById('proAttentionV27');
    if(list){
      const all=Array.isArray(orders)?orders:[];
      const pending=all.filter(o=>o.cancellation?.status==='Pending Review').length;
      const refunds=all.filter(o=>o.cancellation?.status==='Refund Pending').length;
      const needs=all.filter(o=>o.payment_status==='Payment Submitted'&&o.cancellation?.status!=='Pending Review'&&o.status!=='Cancelled').length;
      const fees=all.filter(o=>o.status==='Pending Shipping Fee'&&!o.cancellation).length;
      const ready=all.filter(o=>o.status==='Ready to Ship'&&!o.cancellation).length;
      const pickup=all.filter(o=>o.status==='Ready for Pickup'&&!o.cancellation).length;
      const actions=[['Payment proofs to verify',needs,'orders'],['Shipping fees to confirm',fees,'orders'],['Orders ready for shipment',ready,'courier-shipping'],['Orders ready for pickup',pickup,'courier-shipping'],['Cancellation requests',pending,'cancellations'],['GCash refunds to process',refunds,'cancellations']];
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