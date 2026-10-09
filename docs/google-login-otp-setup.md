# Google Login + email OTP — preparation (NOT LIVE)

Branch: `feature/google-login-otp-prep`. Nothing in this branch should be merged or deployed until reviewed and tested.

## Confirmed current deployment

The existing `.github/workflows/deploy.yml` deploys `lorencebetta/**` from `main` to `/htdocs/` via FTP.
It excludes `index.html`, `api/login.php`, `api/db.php`, so a normal commit will not update those three live files.

## Google configuration

Google Web Client ID:
`821976549681-969e5u5de30gu6823tuq8s8jl33dvimn.apps.googleusercontent.com`

Use Google Identity Services, then **validate the returned ID token server-side** (signature, audience, issuer, expiry, email_verified, and sub) before issuing OTP. Do not trust the browser-supplied email. Require a second code sent to the verified email before login. Existing admin users must not be automatically linked.

## Email sender

`lorencebetta/api/google_otp_mailer.php` is a helper, not an exposed endpoint.
It requires PHPMailer (run `composer install --no-dev --optimize-autoloader` locally and upload `vendor/` when ready) and a credential file OUTSIDE the web root, at the hosting account root `private/google_mail_config.php`.

The private PHP file must return:
```php
<?php
return [
  'email' => 'YOUR_GMAIL_ADDRESS',
  'app_password' => 'YOUR_GMAIL_APP_PASSWORD'
];
```
Never paste real credentials in GitHub, chat, screenshots, or public web files. Confirm whether the InfinityFree account allows a private directory outside `htdocs` before creating it. If it does not, redesign credential storage before deployment. Do not use the public web root for secrets.

The Gmail SMTP connection on port 587 has NOT been tested on this hosting account. Verify network connectivity before promising delivery.

## Database

Review `lorencebetta/sql/google_login_otp_migration.sql` and back up database first. OTPs must be hashed with `password_hash`, compared with `password_verify`, expire in 5 minutes, be one-time use, and enforce resend and attempt limits. Database writes and email sending must be coordinated to avoid orphaned/usable codes when delivery fails.

## Remaining work BEFORE enabling

1. Verify deployed live `index.html` matches the repo copy; the repo copy may be older than the current site.
2. Implement Google ID token validation and secure user linking/new customer creation.
3. Implement OTP request, verify, resend, expiry, rate limiting, and transactional completion.
4. Confirm PHP runtime, PHPMailer dependencies and SMTP connectivity on InfinityFree.
5. Add buttons to both login and signup without changing existing layout; keep normal password login/admin behavior.
6. Test in a non-production environment, then plan explicit deployment of excluded files and DB migration.

No secrets, passwords, or live account changes are included in this branch.
