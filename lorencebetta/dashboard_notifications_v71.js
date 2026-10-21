(function(){
const old=renderAdminDashboard;
renderAdminDashboard=function(){
 old();
 const all=Array.isArray(orders)?orders:[];
 const fresh=all.filter(o=>o.status!=='Cancelled'&&!o.cancellation&&o.payment_status==='Payment Submitted').length;
 const cancelled=all.filter(o=>o.cancellation?.status==='Pending Review').length;
 const number=document.getElementById('proPendingV27');
 if(number){
  number.textContent=String(fresh);
  const card=number.closest('.pro-kpi-v27');
  const label=card?.querySelector('.pro-kpi-top-v27 span');
  if(label)label.textContent='New Orders';
  const note=card?.querySelector('small');
  if(note)note.remove();
 }
 const panel=document.getElementById('proAttentionV27');
 if(panel){
  const wrapper=panel.closest('.pro-panel-v27');
  const title=wrapper?.querySelector('h3');
  const subtitle=wrapper?.querySelector('.pro-panel-title-v27 p');
  if(title)title.textContent='Notifications';
  if(subtitle)subtitle.textContent='New orders and cancellation requests';
  panel.innerHTML=[['New Orders',fresh,'orders','new'],['Cancellation Requests',cancelled,'cancellations','cancel']].map(([name,n,page,type])=>'<button type="button" class="dash-note-v71 '+type+'" data-dash-page="'+page+'"><span class="dash-note-icon-v71" aria-hidden="true"></span><span class="dash-note-label-v71"><strong>'+name+'</strong><small>'+n+' awaiting review</small></span><b>'+n+'</b><span aria-hidden="true">›</span></button>').join('');
 }
};
})();