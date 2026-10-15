<?php
// Session-based access control for administrative API endpoints.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    if (!session_start()) respond(false, 'Unable to start session.', [], 500);
}
function require_admin_session($conn) {
    if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['user_id'])) {
        respond(false, 'Admin login required. Please sign in again.', [], 401);
    }
    $id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND role='admin' AND status='active' LIMIT 1");
    if (!$stmt) respond(false, 'Unable to validate admin session.', [], 500);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $admin = stmt_fetch_assoc_compat($stmt);
    $stmt->close();
    if (!$admin) {
        $_SESSION = [];
        respond(false, 'Admin session expired or account inactive.', [], 401);
    }
}
