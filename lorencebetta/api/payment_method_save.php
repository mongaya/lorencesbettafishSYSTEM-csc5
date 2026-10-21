<?php
// Alternate write route for payment methods. Reuse the existing validation,
// admin-session authorization, database writes and QR upload handling.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
require __DIR__ . '/payment_settings.php';
