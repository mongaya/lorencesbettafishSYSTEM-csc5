<?php
declare(strict_types=1);

/**
 * Reuse the website's existing Gmail SMTP sender and private api/gmail_config.php.
 * Never commit the SMTP credentials. The production copy remains on the server.
 */
require_once __DIR__ . '/smtp_mail.php';

function send_google_login_otp(string $recipient, string $code): void
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) ||
        !preg_match('/^[0-9]{6}$/D', $code)) {
        throw new InvalidArgumentException('Invalid verification request.');
    }

    $subject = "Lorence's Betta Fish verification code";
    $body = 'Your verification code is: ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
        . '<br><br>This code expires in 5 minutes.'
        . '<br>If you did not request it, ignore this email.';

    send_gmail($recipient, $subject, $body);
}
