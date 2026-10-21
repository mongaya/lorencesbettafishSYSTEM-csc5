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
  if(note)note.textContent='Awaiting review';
 }
};
})();