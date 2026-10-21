<?php
// Temporary authenticated, read-only diagnostic endpoint.
// No submitted field names, values, filenames, or uploads are logged or stored.
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
    'post_reached_php' => $method === 'POST',
    'content_type' => $method === 'POST' ? (
        stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0 ? 'multipart/form-data' :
        (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/x-www-form-urlencoded') === 0 ? 'application/x-www-form-urlencoded' :
        (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0 ? 'application/json' : 'other'))
    ) : 'none',
    'field_count' => $method === 'POST' ? count($_POST) : 0,
    'file_count' => $method === 'POST' ? count($_FILES) : 0
]);
