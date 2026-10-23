/* Admin product review viewer - read-only, uses existing review API and state. */
(function(){
const state={query:'',rating:'all'};
function safe(v){return escapeHTML(v==null?'':String(v))}
function stars(n){return '<span class="ar-stars-v107" aria-label="'+Number(n)+' out of 5 stars">'+[1,2,3,4,5].map(i=>'<span class="'+(i<=Number(n)?'filled':'')+'">★</span>').join('')+'</span>'}
async function render(){
 const root=document.getElementById('adminReviewListV107');if(!root)return;
 try{await loadProductReviews()}catch(err){root.innerHTML='<p class="ar-empty-v107">Unable to load reviews. '+safe(err.message||'Please try again.')+'</p>';return}
 const list=Array.isArray(productReviews)?productReviews:[];
 const total=list.length, sum=list.reduce((s,r)=>s+Number(r.rating||0),0);
 const avg=total?(sum/total).toFixed(1):'0.0';
 const fives=list.filter(r=>Number(r.rating)===5).length;
 document.getElementById('adminReviewTotalV107').textContent=total;
 document.getElementById('adminReviewAverageV107').textContent=avg;
 document.getElementById('adminReviewFivesV107').textContent=fives;
 const matching=list.filter(r=>{
  const p=products.find(p=>Number(p.db_id)===Number(r.productId));
  return (state.rating==='all'||Number(r.rating)===Number(state.rating)) &&
  [p?.name,p?.id,r.customerName,r.reviewText].some(t=>String(t||'').toLowerCase().includes(state.query.toLowerCase()));
 });
 document.getElementById('adminReviewShownV107').textContent=matching.length+' review'+(matching.length===1?'':'s');
 root.innerHTML=matching.length?matching.map(r=>{
  const p=products.find(p=>Number(p.db_id)===Number(r.productId));
  const image=p?.image?'<img class="ar-product-image-v107" src="'+safe(p.image)+'" alt="'+safe(p.name)+'" loading="lazy">':'<div class="ar-product-image-v107 ar-placeholder-v107" aria-hidden="true">◈</div>';
  const photo=r.reviewImage&&/^\/?images\/reviews\/[a-zA-Z0-9_.-]+$/.test(r.reviewImage)?'<a class="ar-review-photo-v107" href="'+safe(r.reviewImage)+'" target="_blank" rel="noopener noreferrer"><img src="'+safe(r.reviewImage)+'" alt="Photo uploaded with customer review" loading="lazy"></a>':'';
  return '<article class="ar-review-card-v107">'+image+'<div class="ar-review-body-v107"><div class="ar-review-top-v107"><strong>'+safe(p?.name||'Product #'+r.productId)+'</strong><time>'+safe(r.createdAt||'')+'</time></div><div class="ar-rating-v107">'+stars(r.rating)+' <b>'+Number(r.rating).toFixed(1)+'</b></div><span class="ar-customer-v107">'+safe(r.customerName||'Customer')+'</span>'+(r.reviewText?'<p>'+safe(r.reviewText)+'</p>':'<p class="ar-no-text-v107">Star rating only</p>')+photo+'</div></article>'
 }).join(''):'<div class="ar-empty-v107">No reviews match your filters.</div>';
}
const previous=window.showAdminPage;
window.showAdminPage=function(page,button){const result=previous.apply(this,arguments);if(page==='product-reviews')render();return result};
document.addEventListener('DOMContentLoaded',()=>{
 const search=document.getElementById('adminReviewSearchV107');
 const rating=document.getElementById('adminReviewRatingV107');
 search?.addEventListener('input',e=>{state.query=e.target.value;render()});
 rating?.addEventListener('change',e=>{state.rating=e.target.value;render()});
 document.getElementById('adminReviewRefreshV107')?.addEventListener('click',render);
});
})();