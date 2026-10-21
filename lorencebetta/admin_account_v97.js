(function(){
const $=id=>document.getElementById(id);
function closeMenu(){ $('adminAccountMenuV97')?.classList.add('hidden'); $('adminAccountButtonV97')?.setAttribute('aria-expanded','false');}
window.toggleAdminAccountV97=function(){const menu=$('adminAccountMenuV97');if(!menu)return;const opening=menu.classList.contains('hidden');menu.classList.toggle('hidden',!opening);$('adminAccountButtonV97')?.setAttribute('aria-expanded',String(opening));};
window.openAdminAccountV97=function(tab){closeMenu();const modal=$('adminAccountModalV97');if(!modal)return;const user=typeof currentUser==='object'&&currentUser?currentUser:{};$('adminAccountNameV97').textContent=user.name||'Administrator';$('adminAccountUsernameV97').textContent=user.username||'—';$('adminAccountEmailV97').textContent=user.email||'No email on file';$('adminAccountEmailStatusV97').textContent='Email verification status not available';modal.classList.remove('hidden');window.switchAdminAccountV97(tab||'profile');};
window.switchAdminAccountV97=function(tab){['profile','account','security'].forEach(key=>{const panel=$('adminAccountPanel'+key+'V97');const button=$('adminAccountTab'+key+'V97');if(panel)panel.hidden=key!==tab;if(button){button.classList.toggle('selected',key===tab);button.setAttribute('aria-selected',String(key===tab));}});};
window.closeAdminAccountV97=function(){$('adminAccountModalV97')?.classList.add('hidden');};
document.addEventListener('click',e=>{if(!e.target.closest('#adminAccountMenuWrapV97'))closeMenu();if(e.target.id==='adminAccountModalV97')window.closeAdminAccountV97();});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeMenu();window.closeAdminAccountV97();}});
})();