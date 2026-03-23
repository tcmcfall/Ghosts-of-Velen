<?php
// auth/reset_password.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';         // secure session cookie flags + session_start + Dotenv
require_once __DIR__ . '/db_connect.php';        // provides db()
require_once __DIR__ . '/../includes/csrf.php';  // CSRF helpers

$pdo   = db();
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

// Basic token sanity check (expect 64 hex chars if generated via bin2hex(32))
if ($token === '' || !preg_match('/^[A-Fa-f0-9]{64}$/', $token)) {
    http_response_code(400);
    exit('Invalid reset link.');
}

// Look up reset record
$stmt = $pdo->prepare('SELECT user_id, expires_at FROM password_resets WHERE token = :t LIMIT 1');
$stmt->execute([':t' => $token]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset) {
    http_response_code(400);
    exit('Invalid or expired reset link.');
}

// Expiry check
if (isset($reset['expires_at']) && strtotime((string)$reset['expires_at']) < time()) {
    http_response_code(400);
    exit('Invalid or expired reset link.');
}

// Passed checks; render form
$safeToken = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="../css/login.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <div class="login-form-container">
    <h2>Reset Your Password</h2>
    <form method="POST" action="update_password.php" autocomplete="off">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="token" value="<?php echo $safeToken; ?>">

      <label for="new_password">New Password:</label>
      <input type="password" id="new_password" name="new_password" required>

      <label for="confirm_password">Confirm New Password:</label>
      <input type="password" id="confirm_password" name="confirm_password" required>

      <button type="submit">Update Password</button>
    </form>
    <p class="hint">
      Passwords must be at least 8 characters, include upper &amp; lower case, and a non-alphanumeric character.
    </p>
  </div>
</body>
</html>
