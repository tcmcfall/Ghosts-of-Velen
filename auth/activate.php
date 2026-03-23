<?php
// auth/activate.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';         // session, env, hardened cookie flags
require_once __DIR__ . '/db_connect.php';        // db()
require_once __DIR__ . '/../includes/csrf.php';  // csrf_token(), csrf_validate_request()

header('Content-Type: text/html; charset=utf-8');

// ---- helpers ----
function bad_request(string $msg, int $code = 400): void {
    http_response_code($code);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Account Activation</title><p>'
       . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
       . '</p>';
    exit;
}

function clean_hex_token(?string $t): string {
    $t = (string)($t ?? '');
    $t = trim($t);
    if ($t === '' || !preg_match('/^[A-Fa-f0-9]{64}$/', $t)) {
        bad_request('Invalid or expired activation link.');
    }
    return $t;
}

// ---- route ----
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();

if ($method === 'GET') {
    // Validate token & show password form
    $token = clean_hex_token($_GET['token'] ?? null);

    $stmt = $pdo->prepare('SELECT id, username, is_active FROM users WHERE activation_token = :t LIMIT 1');
    $stmt->execute([':t' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        bad_request('Invalid or expired activation link.');
    }
    if ((int)($user['is_active'] ?? 0) === 1) {
        bad_request('This account is already activated. You can log in.');
    }

    // Render minimal set-password form (CSRF-protected)
    $tokenEsc = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $csrf = csrf_token();
    $csrfMeta = htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Activate Your Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="$csrfMeta">
  <link rel="stylesheet" href="../css/global.css" />
  <link rel="stylesheet" href="../css/login.css" />
</head>
<body>
  <div class="login-form-container">
    <h1>Activate Account</h1>
    <form action="activate.php" method="POST" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="$csrfMeta">
      <input type="hidden" name="token" value="$tokenEsc">
      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" required>

      <label for="confirm_password">Confirm Password</label>
      <input type="password" id="confirm_password" name="confirm_password" required>

      <button type="submit">Set Password & Activate</button>
    </form>
    <p class="help-text">Password must be at least 8 characters and include upper, lower, and a non-alphanumeric character.</p>
  </div>
</body>
</html>
HTML;
    exit;
}

if ($method === 'POST') {
    if (!csrf_validate_request()) {
        bad_request('Security token invalid. Please refresh and try again.', 400);
    }

    $token       = clean_hex_token($_POST['token'] ?? null);
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirm     = (string)($_POST['confirm_password'] ?? '');

    // Password policy (align with registration/update_password)
    $tooShort  = strlen($newPassword) < 8;
    $noUpper   = !preg_match('/[A-Z]/', $newPassword);
    $noLower   = !preg_match('/[a-z]/', $newPassword);
    $noSpecial = !preg_match('/[^a-zA-Z0-9]/', $newPassword);
    if ($newPassword !== $confirm || $tooShort || $noUpper || $noLower || $noSpecial) {
        bad_request('Password must be strong, match confirmation, and include upper, lower, and a non-alphanumeric character.');
    }

    // Lookup user by activation token
    $stmt = $pdo->prepare('SELECT id, is_active FROM users WHERE activation_token = :t LIMIT 1');
    $stmt->execute([':t' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        bad_request('Invalid or expired activation link.');
    }
    if ((int)($user['is_active'] ?? 0) === 1) {
        bad_request('This account is already activated. You can log in.');
    }

    // Activate + set password atomically
    $pdo->beginTransaction();
    try {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $upd = $pdo->prepare(
            'UPDATE users
             SET password = :p,
                 is_active = 1,
                 activation_token = NULL,
                 force_password_change = 0
             WHERE id = :id'
        );
        $upd->execute([':p' => $hash, ':id' => (int)$user['id']]);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        bad_request('Unexpected server error. Please try again later.', 500);
    }

    // Rotate session ID (defense in depth)
    session_regenerate_id(true);

    // Success — redirect to login
    header('Location: /auth/login.php?activated=1');
    exit;
}

// Fallback
bad_request('Unsupported method.', 405);
