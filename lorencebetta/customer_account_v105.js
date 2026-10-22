(function(){
 const $=id=>document.getElementById(id);
 let kind='username';
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
  el.maxLength=type==='password'?128:type==='email'?254:50;
  $('customerAccountFormV105').hidden=false;
 };
 window.customerAccountStartV105=async function(event){
  event.preventDefault();
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
  }catch(e){message('OTP request failed: '+e.message);}
  finally{button.disabled=false;}
 };
 window.customerAccountVerifyV105=async function(event){
  event.preventDefault();
  const button=$('customerAccountVerifyV105');
  button.disabled=true;message('Verifying code...');
  try{
   const result=await request({action:'verify',code:$('customerAccountOtpV105').value});
   $('customerAccountOtpV105').value='';
   if(result.stage==='new'){
    $('customerAccountOtpHelpV105').textContent='Enter the NEW 6-digit code sent to your new email address. Valid for 5 minutes.';
    message(result.message);
   }else if(result.done){
    window.customerAccountCloseV105();
    try{localStorage.removeItem('lorence_currentUser');}catch(e){}
    // Credentials changed server-side; start a fresh authenticated session.
    location.reload();
   }else{message(result.message);}
  }catch(e){message(e.message);}
  finally{button.disabled=false;}
 };
})();