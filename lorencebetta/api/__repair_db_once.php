<?php
header('Content-Type: application/json; charset=UTF-8');

$expectedToken = 'repair-9f7c1e6b-20260919';
if (!isset($_GET['token']) || !hash_equals($expectedToken, (string) $_GET['token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$backupPath = __DIR__ . '/db_backup.php';
$targetPath = __DIR__ . '/db.php';
$backup = @file_get_contents($backupPath);
$current = @file_get_contents($targetPath);

if ($backup === false || $current === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to read required files']);
    exit;
}

if (!preg_match_all('~\\$password\\s*=\\s*(["\\\'])([^"\\\'\\r\\n]{6,128})\\1\\s*;~', $backup, $matches)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No credential candidates found']);
    exit;
}

$password = null;
foreach (array_unique($matches[2]) as $candidate) {
    if ($candidate === '' || strpos($candidate, '<?php') !== false || strpos($candidate, 'PASTE_') !== false) {
        continue;
    }
    $test = @new mysqli('sql200.infinityfree.com', 'if0_42882922', $candidate, 'if0_42882922_lorencebetta', 3306);
    if (!$test->connect_errno) {
        $password = $candidate;
        $test->close();
        break;
    }
}

if ($password === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No backup credential passed database validation']);
    exit;
}

$safePassword = str_replace(['\\', "'"], ['\\\\', "\\'"], $password);
$updates = [
    ['~^\\s*\\$host\\s*=.*$~m', "\\$host = 'sql200.infinityfree.com';"],
    ['~^\\s*\\$username\\s*=.*$~m', "\\$username = 'if0_42882922';"],
    ['~^\\s*\\$password\\s*=.*$~m', "\\$password = '" . $safePassword . "';"],
    ['~^\\s*\\$database\\s*=.*$~m', "\\$database = 'if0_42882922_lorencebetta';"],
];

$updated = $current;
foreach ($updates as $update) {
    $count = 0;
    $updated = preg_replace($update[0], $update[1], $updated, 1, $count);
    if ($updated === null || $count !== 1) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Live db.php configuration format is invalid']);
        exit;
    }
}

$tempPath = $targetPath . '.repairing';
if (@file_put_contents($tempPath, $updated, LOCK_EX) === false || !@rename($tempPath, $targetPath)) {
    @unlink($tempPath);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save repaired configuration']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Database configuration repaired and validated']);
