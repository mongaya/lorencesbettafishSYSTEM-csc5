<?php
require_once __DIR__.'/gmail_config.php';
function smtp_read($fp){$data='';while($line=fgets($fp,515)!==false){$data.=$line;if(strlen($line)<4||$line[3]!=='-')break;}return $data;}
function smtp_expect($fp,$code){$r=smtp_read($fp);if(strpos($r,(string)$code)!==0)throw new Exception('Gmail SMTP error: '.trim($r));}
function send_gmail($to,$subject,$html){
 if(GMAIL_USERNAME==='YOUR_GMAIL@gmail.com'||GMAIL_APP_PASSWORD==='YOUR_16_CHARACTER_APP_PASSWORD')throw new Exception('Gmail SMTP is not configured yet. Edit api/gmail_config.php first.');
 $fp=fsockopen(GMAIL_SMTP_HOST,GMAIL_SMTP_PORT,$errno,$errstr,20);if(!$fp)throw new Exception('Cannot connect to Gmail SMTP: '.$errstr);stream_set_timeout($fp,20);smtp_expect($fp,220);
 fwrite($fp,"EHLO localhost\r\n");smtp_expect($fp,250);fwrite($fp,"STARTTLS\r\n");smtp_expect($fp,220);if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new Exception('TLS negotiation failed.');
 fwrite($fp,"EHLO localhost\r\n");smtp_expect($fp,250);fwrite($fp,"AUTH LOGIN\r\n");smtp_expect($fp,334);fwrite($fp,base64_encode(GMAIL_USERNAME)."\r\n");smtp_expect($fp,334);fwrite($fp,base64_encode(GMAIL_APP_PASSWORD)."\r\n");smtp_expect($fp,235);
 fwrite($fp,"MAIL FROM:<".GMAIL_USERNAME.">\r\n");smtp_expect($fp,250);fwrite($fp,"RCPT TO:<".$to.">\r\n");smtp_expect($fp,250);fwrite($fp,"DATA\r\n");smtp_expect($fp,354);
 $headers="From: ".GMAIL_FROM_NAME." <".GMAIL_USERNAME.">\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\nTo: <".$to.">\r\n\r\n";
 $body=str_replace(["\r\n","\r","\n"],"\r\n",$headers.$html);$body=preg_replace('/^\./m','..',$body);fwrite($fp,$body."\r\n.\r\n");smtp_expect($fp,250);fwrite($fp,"QUIT\r\n");fclose($fp);return true;
}
?>