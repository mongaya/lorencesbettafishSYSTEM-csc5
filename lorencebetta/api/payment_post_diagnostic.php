<?php
// Temporary, read-only diagnostic for POST request filtering.
// Remove this file after troubleshooting.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin_session($conn);
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
echo json_encode([
    'success' => true,
    'request_method' => $method,
    'post_reached_php' => $method === 'POST'
]);
