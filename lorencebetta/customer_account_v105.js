(function(){
 const $=id=>document.getElementById(id);
 let kind='username'; 
 function clearError(id){const el=$(id);if(!el)return;el.removeAttribute('aria-invalid');el.classList.remove('bt-account-invalid-v136');const msg=el.parentElement?.querySelector('[data-error-for="'+id+'"]');msg?.remove();}
 function showError(id,txt){const el=$(id);if(!el)return;clearError(id);el.setAttribute('aria-invalid','true');el.classList.add('bt-account-invalid-v136');const note=document.createElement('small');note.dataset.errorFor=id;note.className='bt-account-error-v136';note.setAttribute('role','alert');note.textContent=txt;el.insertAdjacentElement('afterend',note);el.focus();}
 function classifyError(message,valueId,passwordId,otpId){
   const m=String(message||'');
   if(/code|otp|verification expired|incorrect verification/i.test(m))return otpId;
   if(/current password|incorrect password|wrong password/i.test(m))return passwordId;
   if(/username|email|new password|different password|8.?128|valid email|already registered|already taken/i.test(m))return valueId;
   return null;
 }
 document.addEventListener('input',e=>{if(e.target?.classList?.contains('bt-account-invalid-v136'))clearError(e.target.id);});

 function message(s){$('customerAccountMessageV105').textContent=s||'';}
 async function request(fields){
  const res=await fetch('api/customer_account.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(fields).toString(),cache:'no-store'});
  let data;try{data=await res.json();}catch(e){throw Error('Unable to read server response.');}
  if(!res.ok||!data.success)throw Error(data.message||'Request failed.');
  return data;
 }
 window.customerAccountCloseV105=function(){
  $('customerAccountFormV105').hidden=true;
  $('customerAccountOtpFormV105').hidden=true;
  $('customerAccountValueV105').value='';
  $('customerAccountPasswordV105').value='';
  $('customerAccountOtpV105').value='';
  ['customerAccountValueV105','customerAccountPasswordV105','customerAccountOtpV105'].forEach(clearError);
  message('');
 };
 window.customerAccountOpenV105=function(type){
  if(!['username','password','email'].includes(type))return;
  window.customerAccountCloseV105();
  kind=type;
  const label=type==='username'?'Username':type==='password'?'Password':'Email';
  $('customerAccountHeadingV105').textContent='Change '+label;
  $('customerAccountValueLabelV105').textContent='New '+label;
  const el=$('customerAccountValueV105');
  el.type=type==='password'?'password':type==='email'?'email':'text';
  el.autocomplete=type==='password'?'new-password':type==='email'?'email':'off';
  el.minLength=type==='password'?8:3;
  el.maxLength=type==='password'?10:type==='email'?254:50;
  $('customerAccountFormV105').hidden=false;
 };
 window.customerAccountStartV105=async function(event){
  event.preventDefault();
  const val=$('customerAccountValueV105').value.trim();
  const valueId='customerAccountValueV105';
  if(kind==='username'&&!/^[A-Za-z0-9_.-]{3,50}$/.test(val))return showError(valueId,'Username must be 3–50 characters using letters, numbers, dots, underscores or hyphens.');
  if(kind==='email'&&!/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(val))return showError(valueId,'Please enter a valid email address.');
  if(kind==='password'&&(val.length<8||val.length>10||!/[0-9]/.test(val)||!/[^A-Za-z0-9]/.test(val)))return showError(valueId,'Password must contain 8–10 characters, one number and one special character.');
  ['customerAccountValueV105','customerAccountPasswordV105'].forEach(clearError);
  const button=$('customerAccountSendV105');
  button.disabled=true;message('Sending verification code...');
  try{
   const result=await request({action:'start',type:kind,value:$('customerAccountValueV105').value,current_password:$('customerAccountPasswordV105').value});
   $('customerAccountValueV105').value='';
   $('customerAccountPasswordV105').value='';
   $('customerAccountFormV105').hidden=true;
   $('customerAccountOtpFormV105').hidden=false;
   $('customerAccountOtpHelpV105').textContent='Enter the 6-digit code sent to your registered email. Valid for 5 minutes.';
   message(result.message+' If you do not receive it, confirm this is your registered email and check Spam or All Mail.');
  }catch(e){const id=classifyError(e.message,'customerAccountValueV105','customerAccountPasswordV105','customerAccountOtpV105');if(id){showError(id,e.message);message('');}else message('OTP request failed: '+e.message);}
  finally{button.disabled=false;}
 };
 window.customerAccountVerifyV105=async function(event){
  event.preventDefault();
  const button=$('customerAccountVerifyButtonV113');
  button.disabled=true;message('Verifying code...');
  try{
   const result=await request({action:'verify',code:$('customerAccountOtpV105').value});
   $('customerAccountOtpV105').value='';
   if(result.stage==='new'){
    $('customerAccountOtpHelpV105').textContent='Enter the NEW 6-digit code sent to your new email address. Valid for 5 minutes.';
    message(result.message);
   }else if(result.done && result.requires_login){
    // The server has committed the change and invalidated the PHP session.
    // Never keep showing the old customer identity from localStorage.
    try{localStorage.removeItem('lorence_currentUser');}catch(e){}
    try{sessionStorage.removeItem('lorence_currentUser');}catch(e){}
    window.customerAccountCloseV105();
    const notice=$('customerAccountSuccessV112');
    if(notice){
     const what=kind==='username'?'Username':kind==='password'?'Password':'Email';
     notice.textContent=what+' updated successfully. You have been signed out. Please sign in again'+(kind==='username'?' using your new username.':'.');
     notice.hidden=false;
    }
    // The app bootstraps currentUser from localStorage, so reload into signed-out storefront.
    window.location.reload();
    return;
   }else{message(result.message);}
  }catch(e){const id=classifyError(e.message,'customerAccountValueV105','customerAccountPasswordV105','customerAccountOtpV105');if(id){showError(id,e.message);message('');}else message('Verification failed: '+e.message);}
  finally{button.disabled=false;}
 };
})();