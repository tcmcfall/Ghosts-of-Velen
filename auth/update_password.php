<?php
// auth/update_password.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';         // secure session cookie flags + session_start + Dotenv
require_once __DIR__ . '/db_connect.php';        // provides db()
require_once __DIR__ . '/../includes/csrf.php';  // CSRF helpers

// Enforce POST + CSRF
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
if (!csrf_validate_request()) {
    http_response_code(400);
    exit('Bad Request: CSRF validation failed.');
}

// Inputs
$token         = isset($_POST['token']) ? (string) $_POST['token'] : '';
$newPassword   = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
$confirmPass   = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';

// Basic token sanity check (expect 64 hex chars if generated via bin2hex(32))
if ($token === '' || !preg_match('/^[A-Fa-f0-9]{64}$/', $token)) {
    http_response_code(400);
    exit('Invalid or expired token.');
}

// Password policy (align with registration baseline)
$tooShort  = strlen($newPassword) < 8;
$noUpper   = !preg_match('/[A-Z]/', $newPassword);
$noLower   = !preg_match('/[a-z]/', $newPassword);
$noSpecial = !preg_match('/[^a-zA-Z0-9]/', $newPassword);

if ($newPassword !== $confirmPass || $tooShort || $noUpper || $noLower || $noSpecial) {
    http_response_code(400);
    exit('Password must be strong, match confirmation, and include upper, lower, and a non-alphanumeric character.');
}

$pdo = db();

// Look up valid reset request
$sql = <<<SQL
SELECT pr.user_id
FROM password_resets pr
WHERE pr.token = :t
  AND pr.expires_at > NOW()
LIMIT 1
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute([':t' => $token]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset || !isset($reset['user_id'])) {
    http_response_code(400);
    exit('Invalid or expired token.');
}

$userId = (int) $reset['user_id'];

// Update user password (login.php expects column `users.password`)
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$upd = $pdo->prepare('UPDATE users SET password = :p WHERE id = :uid');
$upd->execute([':p' => $hash, ':uid' => $userId]);

// Invalidate all outstanding reset tokens for this user (defense-in-depth)
$del = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :uid');
$del->execute([':uid' => $userId]);

// Optional: regenerate session ID (even though user likely not logged in here)
session_regenerate_id(true);

echo 'Password updated successfully. You may now log in.';
