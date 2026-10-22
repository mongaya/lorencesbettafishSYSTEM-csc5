<?php
require_once __DIR__.'/gmail_config.php';
function smtp_read($fp){$data='';while(($line=fgets($fp,515))!==false){$data.=$line;if(strlen($line)<4||$line[3]!=='-')break;}return $data;}
function smtp_expect($fp,$code){$r=smtp_read($fp);if(strpos($r,(string)$code)!==0)throw new Exception('Gmail SMTP error: '.trim($r));}
function send_gmail($to,$subject,$html){
 if(GMAIL_USERNAME==='YOUR_GMAIL@gmail.com'||GMAIL_APP_PASSWORD==='YOUR_16_CHARACTER_APP_PASSWORD')throw new Exception('Gmail SMTP is not configured yet. Edit api/gmail_config.php first.');
 $fp=fsockopen(GMAIL_SMTP_HOST,GMAIL_SMTP_PORT,$errno,$errstr,20);if(!$fp)throw new Exception('Cannot connect to Gmail SMTP: '.$errstr);stream_set_timeout($fp,20);smtp_expect($fp,220);
 fwrite($fp,"EHLO localhost\r\n");smtp_expect($fp,250);fwrite($fp,"STARTTLS\r\n");smtp_expect($fp,220);if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new Exception('TLS negotiation failed.');
 fwrite($fp,"EHLO localhost\r\n");smtp_expect($fp,250);fwrite($fp,"AUTH LOGIN\r\n");smtp_expect($fp,334);fwrite($fp,base64_encode(GMAIL_USERNAME)."\r\n");smtp_expect($fp,334);fwrite($fp,base64_encode(GMAIL_APP_PASSWORD)."\r\n");smtp_expect($fp,235);
 fwrite($fp,"MAIL FROM:<".GMAIL_USERNAME.">\r\n");smtp_expect($fp,250);fwrite($fp,"RCPT TO:<".$to.">\r\n");smtp_expect($fp,250);fwrite($fp,"DATA\r\n");smtp_expect($fp,354);
 // Use a standards-compliant multipart message so Gmail can parse both text and HTML.
 $domain=substr(strrchr(GMAIL_USERNAME,'@'),1)?:'gmail.com';
 $boundary='betta_'.bin2hex(random_bytes(12));
 $plain=html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i',"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8');
 $headers="Date: ".date('r')."\r\n"
  ."Message-ID: <".bin2hex(random_bytes(16))."@".$domain.">\r\n"
  ."From: ".GMAIL_FROM_NAME." <".GMAIL_USERNAME.">\r\n"
  ."To: <".$to.">\r\n"
  ."Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n"
  ."MIME-Version: 1.0\r\n"
  ."Content-Type: multipart/alternative; boundary=\"".$boundary."\"\r\n\r\n";
 $mime="--".$boundary."\r\n"
  ."Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
  .quoted_printable_encode($plain)."\r\n"
  ."--".$boundary."\r\n"
  ."Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
  .quoted_printable_encode($html)."\r\n"
  ."--".$boundary."--\r\n";
 $body=$headers.$mime;
 // SMTP DATA requires CRLF and escaped leading dots.
 $body=preg_replace("/\r\n|\r|\n/","\r\n",$body);
 $body=preg_replace('/(^|\r\n)\./',"$1..",$body);
 fwrite($fp,$body."\r\n.\r\n");smtp_expect($fp,250);
 fwrite($fp,"QUIT\r\n");fclose($fp);return true;
}
?>