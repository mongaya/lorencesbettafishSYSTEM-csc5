<?php
/**
 * Google sign-in + second-step email OTP.
 * STAGING: requires DB migration, PHPMailer, and Google token verification connectivity.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/google_otp_mailer.php';

const GOOGLE_CLIENT_ID = '821976549681-969e5u5de30gu6823tuq8s8jl33dvimn.apps.googleusercontent.com';
const OTP_TTL_SECONDS = 300;
const OTP_MAX_ATTEMPTS = 5;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}

function google_fail(string $message, int $status = 400): void {
    respond(false, $message, [], $status);
}

function google_token_identity(string $token): array {
    if ($token === '' || strlen($token) > 8192) google_fail('Invalid Google credential.');
    if (!function_exists('curl_init')) google_fail('Google verification is unavailable.', 503);
    // Google's tokeninfo endpoint validates ID-token signature, expiry, issuer and audience.
    // For production volume, use Google's maintained token-verification library with cached JWKs.
    $curl = curl_init('https://oauth2.googleapis.com/tokeninfo');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['id_token' => $token]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $data = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($data)
        || !hash_equals(GOOGLE_CLIENT_ID, (string) ($data['aud'] ?? ''))
        || !in_array(($data['iss'] ?? ''), ['accounts.google.com', 'https://accounts.google.com'], true)
        || (int) ($data['exp'] ?? 0) <= time()
        || !in_array(strtolower((string) ($data['email_verified'] ?? '')), ['true', '1'], true)
        || !filter_var(($data['email'] ?? ''), FILTER_VALIDATE_EMAIL)
        || empty($data['sub'])) {
        google_fail('Google verification failed. Please try again.', 401);
    }
    return [
        'sub' => (string) $data['sub'],
        'email' => strtolower((string) $data['email']),
        'name' => trim((string) ($data['name'] ?? 'Google Customer'))
    ];
}

function google_fetch(mysqli $conn, string $sql, string $type, string $value): ?array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Database query unavailable');
    $stmt->bind_param($type, $value);
    $stmt->execute();
    $row = stmt_fetch_assoc_compat($stmt);
    $stmt->close();
    return $row;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') google_fail('Method not allowed.', 405);
$data = json_input();
$action = (string) ($data['action'] ?? '');

try {
    if ($action === 'start') {
        $last = (int) ($_SESSION['google_otp_last_start'] ?? 0);
        if (time() - $last < 60) google_fail('Please wait before requesting another code.', 429);
        $identity = google_token_identity((string) ($data['credential'] ?? ''));
        $existing = google_fetch($conn, 'SELECT id, role, status FROM users WHERE google_sub=? LIMIT 1', 's', $identity['sub']);
        if ($existing && ($existing['role'] !== 'customer' || $existing['status'] !== 'active')) {
            google_fail('This account cannot use Google sign-in.', 403);
        }
        // Never silently take over an existing password/admin account by matching email.
        if (!$existing) {
            $emailOwner = google_fetch($conn, 'SELECT id FROM users WHERE email=? LIMIT 1', 's', $identity['email']);
            if ($emailOwner) google_fail('This email already has an account. Sign in with your password.', 409);
        }

        $code = (string) random_int(100000, 999999);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $sub = $identity['sub'];
        $email = $identity['email'];
        $stmt = $conn->prepare('INSERT INTO google_login_otps (google_sub,email,code_hash,expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))');
        if (!$stmt) throw new RuntimeException('OTP storage unavailable');
        $stmt->bind_param('sss', $sub, $email, $hash);
        if (!$stmt->execute()) throw new RuntimeException('OTP storage failed');
        $otpId = (int) $conn->insert_id;
        $stmt->close();
        $_SESSION['google_otp_last_start'] = time();
        try {
            send_google_login_otp($email, $code);
        } catch (Throwable $mailError) {
            $stmt = $conn->prepare('UPDATE google_login_otps SET consumed_at=NOW() WHERE id=?');
            $stmt->bind_param('i', $otpId);
            $stmt->execute();
            $stmt->close();
            error_log('Google OTP mail failed: ' . $mailError->getMessage());
            google_fail('Unable to send the verification email. Try again later.', 503);
        }
        // Store pending verified identity server-side only.
        $_SESSION['google_pending'] = ['otp_id'=>$otpId, 'sub'=>$sub, 'email'=>$email, 'name'=>$identity['name']];
        respond(true, 'Verification code sent. It expires in 5 minutes.');
    }

    if ($action === 'verify') {
        $pending = $_SESSION['google_pending'] ?? null;
        if (!is_array($pending)) google_fail('Start Google sign-in again.', 401);
        $code = (string) ($data['code'] ?? '');
        if (!preg_match('/^[0-9]{6}$/D', $code)) google_fail('Enter a valid 6-digit code.');
        $otpId = (int) $pending['otp_id'];
        $stmt = $conn->prepare('SELECT id,code_hash,attempts,expires_at,consumed_at FROM google_login_otps WHERE id=? AND google_sub=? LIMIT 1');
        $stmt->bind_param('is', $otpId, $pending['sub']);
        $stmt->execute();
        $otp = stmt_fetch_assoc_compat($stmt);
        $stmt->close();
        if (!$otp || $otp['consumed_at'] !== null || strtotime($otp['expires_at']) <= time() || (int) $otp['attempts'] >= OTP_MAX_ATTEMPTS) {
            unset($_SESSION['google_pending']);
            google_fail('Code expired or attempts exceeded. Start again.', 401);
        }
        $stmt = $conn->prepare('UPDATE google_login_otps SET attempts=attempts+1 WHERE id=? AND consumed_at IS NULL AND attempts < 5');
        $stmt->bind_param('i', $otpId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected !== 1) google_fail('Too many attempts.', 429);
        if (!password_verify($code, $otp['code_hash'])) google_fail('Incorrect verification code.', 401);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE google_login_otps SET consumed_at=NOW() WHERE id=? AND consumed_at IS NULL AND expires_at>NOW()');
            $stmt->bind_param('i', $otpId);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();
            if ($changed !== 1) throw new RuntimeException('Code already used');

            $user = google_fetch($conn, 'SELECT id,name,username,email,phone,address,role,status FROM users WHERE google_sub=? LIMIT 1', 's', $pending['sub']);
            if (!$user) {
                // A collision on email is rejected, never auto-linked.
                $owner = google_fetch($conn, 'SELECT id FROM users WHERE email=? LIMIT 1', 's', $pending['email']);
                if ($owner) throw new RuntimeException('Email already registered');
                $username = 'google_' . bin2hex(random_bytes(8));
                $password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name,username,email,password,google_sub,role,status,created_at) VALUES (?,?,?,?,?,'customer','active',NOW())");
                $stmt->bind_param('sssss', $pending['name'], $username, $pending['email'], $password, $pending['sub']);
                if (!$stmt->execute()) throw new RuntimeException('Account creation failed');
                $stmt->close();
                $user = google_fetch($conn, 'SELECT id,name,username,email,phone,address,role,status FROM users WHERE google_sub=? LIMIT 1', 's', $pending['sub']);
            }
            if (!$user || $user['role'] !== 'customer' || $user['status'] !== 'active') throw new RuntimeException('Account unavailable');
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Google OTP completion failed: ' . $e->getMessage());
            google_fail('Could not complete Google sign-in.', 409);
        }
        unset($_SESSION['google_pending']);
        session_regenerate_id(true);
        respond(true, 'Google sign-in successful.', ['user'=>[
            'id'=>(int)$user['id'], 'type'=>'customer', 'username'=>$user['username'],
            'name'=>$user['name'], 'email'=>$user['email'],
            'phone'=>$user['phone'] ?? '', 'address'=>$user['address'] ?? ''
        ]]);
    }
    google_fail('Unknown action.');
} catch (Throwable $e) {
    error_log('Google authentication error: ' . $e->getMessage());
    google_fail('Google sign-in is temporarily unavailable.', 503);
}
