<?php
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

$pdo = db();

// ---- Input & validation ----
function sanitize(string $s): string {
    return trim($s);
}

$username         = isset($_POST['username']) ? sanitize((string)$_POST['username']) : '';
$email            = isset($_POST['email'])    ? sanitize((string)$_POST['email'])    : '';
$password         = isset($_POST['password']) ? (string)$_POST['password']           : '';
$confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

// Username: 2–10 alphanumeric
if (!preg_match('/^[a-zA-Z0-9]{2,10}$/', $username)) {
    http_response_code(400);
    exit('Invalid username.');
}

// Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Invalid email address.');
}

// Password checks
$tooShort   = strlen($password) < 8;
$noUpper    = !preg_match('/[A-Z]/', $password);
$noLower    = !preg_match('/[a-z]/', $password);
$noSpecial  = !preg_match('/[^a-zA-Z0-9]/', $password);
$containsUN = stripos($password, $username) !== false;

if ($password !== $confirm_password || $tooShort || $noUpper || $noLower || $noSpecial || $containsUN) {
    http_response_code(400);
    exit('Password must be strong, match confirmation, and must not contain the username.');
}

// ---- Check for existing username/email ----
$dupStmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
$dupStmt->execute([':u' => $username, ':e' => $email]);
if ($dupStmt->fetch()) {
    http_response_code(409);
    exit('Username or email already in use.');
}

// ---- Create account ----
$activation_token = bin2hex(random_bytes(32));
$password_hash    = password_hash($password, PASSWORD_DEFAULT);

// Your login flow reads from users.password and users.role,
// so we insert hashed password into `password` and default role to 'user'.
// Also set is_active = 0 and store the activation_token.

$ins = $pdo->prepare(
    'INSERT INTO users (username, email, password, role, is_active, activation_token)
     VALUES (:u, :e, :p, :r, :active, :token)'
);

$ins->execute([
    ':u'      => $username,
    ':e'      => $email,
    ':p'      => $password_hash,
    ':r'      => 'user',   // Option B: only 'user' or 'admin' exist globally
    ':active' => 0,
    ':token'  => $activation_token,
]);

// ---- Build activation link ----
$baseUrl = $_ENV['APP_URL'] ?? 'https://ghostsofvelen.com';
$activation_link = rtrim($baseUrl, '/') . '/auth/activate.php?token=' . urlencode($activation_token);

// ---- Send activation email via PHPMailer SMTP ----
/**
 * PHPMailer configuration from .env (required keys):
 * SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM, SMTP_FROM_NAME, SMTP_SECURE (tls/ssl)
 */
try {
    // Ensure Composer autoload present (bootstrap already tried, but safe to require again)
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    $smtpHost = $_ENV['SMTP_HOST']       ?? 'smtp.ionos.com';
    $smtpPort = (int)($_ENV['SMTP_PORT'] ?? 587);
    $smtpUser = $_ENV['SMTP_USER']       ?? '';
    $smtpPass = $_ENV['SMTP_PASS']       ?? '';
    $smtpFrom = $_ENV['SMTP_FROM']       ?? 'noreply@ghostsofvelen.com';
    $smtpName = $_ENV['SMTP_FROM_NAME']  ?? 'Ghosts of Velen';
    $smtpSec  = $_ENV['SMTP_SECURE']     ?? 'tls';

    $subject = 'Activate your Ghosts of Velen account';
    $bodyTxt = "Welcome to Ghosts of Velen, {$username}!\n\n"
             . "Please activate your account by visiting the link below:\n"
             . "{$activation_link}\n\n"
             . "If you didn’t request this, you can ignore this message.\n";
    $bodyHtml = '<p>Welcome to <strong>Ghosts of Velen</strong>, '
              . htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
              . '!</p>'
              . '<p>Please activate your account by clicking the link below:</p>'
              . '<p><a href="' . htmlspecialchars($activation_link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
              . htmlspecialchars($activation_link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></p>'
              . '<p>If you didn’t request this, you can ignore this message.</p>';

    // Use PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->Port       = $smtpPort;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = $smtpSec; // 'tls' or 'ssl'

    $mail->setFrom($smtpFrom, $smtpName);
    $mail->addReplyTo($smtpFrom, $smtpName);
    $mail->addAddress($email, $username);

    $mail->Subject = $subject;
    $mail->Body    = $bodyHtml;
    $mail->AltBody = $bodyTxt;
    $mail->isHTML(true);

    $mail->send();
} catch (Throwable $e) {
    // Fail safely: registration succeeded, but mail failed. Log and continue.
    error_log('Registration mail error: ' . $e->getMessage());
}

// ---- Response ----
echo 'Registration successful! Please check your email to activate your account.';
