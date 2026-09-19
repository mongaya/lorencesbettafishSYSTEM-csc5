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

if (!is_file($backupPath) || !is_file($targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Required file missing']);
    exit;
}

$backup = file_get_contents($backupPath);
$current = file_get_contents($targetPath);
if ($backup === false || $current === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to read database files']);
    exit;
}

$pattern = '~\\$host\\s*=\\s*["\\\']sql200\\.infinityfree\\.com["\\\']\\s*;\\s*'
    . '\\$username\\s*=\\s*["\\\']if0_42882922["\\\']\\s*;\\s*'
    . '\\$password\\s*=\\s*(["\\\'])([^"\\\'\\r\\n]{6,128})\\1\\s*;\\s*'
    . '\\$database\\s*=\\s*["\\\']if0_42882922_lorencebetta["\\\']\\s*;~s';

if (!preg_match($pattern, $backup, $match)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Valid hosted credentials were not found in backup']);
    exit;
}

$password = $match[2];
if ($password === '' || strpos($password, '<?php') !== false || strpos($password, 'PASTE_') !== false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Backup credential is invalid']);
    exit;
}

$test = @new mysqli('sql200.infinityfree.com', 'if0_42882922', $password, 'if0_42882922_lorencebetta', 3306);
if ($test->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Backup credential failed database validation']);
    exit;
}
$test->close();

$safePassword = str_replace(['\\', "'"], ['\\\\', "\\'"], $password);
$replacements = [
    '~^\\s*\\$host\\s*=.*$~m' => "\\$host = 'sql200.infinityfree.com';",
    '~^\\s*\\$username\\s*=.*$~m' => "\\$username = 'if0_42882922';",
    '~^\\s*\\$password\\s*=.*$~m' => "\\$password = '" . $safePassword . "';",
    '~^\\s*\\$database\\s*=.*$~m' => "\\$database = 'if0_42882922_lorencebetta';",
];

$updated = $current;
foreach ($replacements as $search => $replacement) {
    $count = 0;
    $updated = preg_replace($search, $replacement, $updated, 1, $count);
    if ($updated === null || $count !== 1) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database configuration format is invalid']);
        exit;
    }
}

$tempPath = $targetPath . '.repairing';
if (file_put_contents($tempPath, $updated, LOCK_EX) === false || !rename($tempPath, $targetPath)) {
    @unlink($tempPath);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save repaired configuration']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Database configuration repaired']);
