<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/db_connect.php';

function login_env_name(): string
{
    $candidates = [
        $_ENV['APP_ENV'] ?? null,
        getenv('APP_ENV'),
        $_SERVER['APP_ENV'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === false || $candidate === null) {
            continue;
        }

        $value = trim(explode('#', (string)$candidate, 2)[0], " \t\n\r\0\x0B\"'");
        if ($value !== '') {
            return $value;
        }
    }

    return 'prod';
}

function login_debug_enabled(string $appEnv): bool
{
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        setcookie('gov_debug', '1', [
            'expires' => time() + 600,
            'path' => '/auth',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    return stripos($appEnv, 'prod') !== 0
        || (($_COOKIE['gov_debug'] ?? '0') === '1')
        || (trim((string)($_ENV['DEBUG_LOGIN'] ?? '0')) === '1');
}

function log_login_exception(string $message): void
{
    error_log($message);
    $tmpDir = sys_get_temp_dir() ?: '/tmp';
    @error_log('[' . date('c') . '] ' . $message . "\n", 3, $tmpDir . '/gov_login_error.log');
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function verify_login_recaptcha(string $siteKey, string $secretKey, bool $debug, string &$error): void
{
    if ($siteKey === '' || $secretKey === '') {
        return;
    }

    $token = (string)($_POST['g-recaptcha-response'] ?? '');
    if ($token === '') {
        $error = 'reCAPTCHA validation failed. Please try again.';
        return;
    }

    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify'
        . '?secret=' . urlencode($secretKey)
        . '&response=' . urlencode($token);

    $responseData = null;
    try {
        $response = @file_get_contents($verifyUrl);
        if ($response !== false) {
            $responseData = json_decode($response, true);
        }
    } catch (Throwable $ignored) {
    }

    if (!is_array($responseData) || empty($responseData['success'])) {
        $error = 'reCAPTCHA validation failed.';
        if ($debug && isset($responseData['error-codes'])) {
            $error .= ' Error codes: ' . implode(', ', (array)$responseData['error-codes']);
        }
        return;
    }

    if (isset($responseData['score']) && (float)$responseData['score'] < 0.5) {
        $error = 'reCAPTCHA score too low.';
        return;
    }

    if (isset($responseData['action']) && $responseData['action'] !== 'login') {
        $error = 'reCAPTCHA action mismatch.';
    }
}

function redirect_authenticated_user(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        return;
    }

    if (($_SESSION['role'] ?? null) === 'admin') {
        header('Location: /panels/admin-dashboard-panel.php');
        exit;
    }

    if (isset($_SESSION['campaign_id']) && array_key_exists('is_dm', $_SESSION)) {
        header('Location: /map.php');
        exit;
    }

    header('Location: /auth/select_campaign.php');
    exit;
}

$appEnv = login_env_name();
$debug = login_debug_enabled($appEnv);
$error = '';
$notice = isset($_GET['activated']) && $_GET['activated'] === '1'
    ? 'Account activated. You can sign in now.'
    : '';
$recaptchaSiteKey = (string)($_ENV['RECAPTCHA_SITE_KEY'] ?? '');
$recaptchaSecretKey = (string)($_ENV['RECAPTCHA_SECRET_KEY'] ?? '');

redirect_authenticated_user();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validate_request()) {
        http_response_code(400);
        $error = 'Security token invalid. Please refresh and try again.';
    } else {
        verify_login_recaptcha($recaptchaSiteKey, $recaptchaSecretKey, $debug, $error);

        if ($error === '') {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $error = 'Please provide both username and password.';
            } else {
                try {
                    $pdo = db();
                    $stmt = $pdo->prepare(
                        'SELECT id, username, role, password, password_hash, is_active
                           FROM users
                          WHERE username = :username
                          LIMIT 1'
                    );
                    $stmt->execute([':username' => $username]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    $storedHash = is_array($user)
                        ? ($user['password'] ?? ($user['password_hash'] ?? null))
                        : null;

                    if (
                        !is_array($user)
                        || !is_string($storedHash)
                        || $storedHash === ''
                        || !password_verify($password, $storedHash)
                    ) {
                        $error = 'Invalid username or password.';
                    } elseif (($user['role'] ?? 'user') !== 'admin' && (int)($user['is_active'] ?? 1) !== 1) {
                        $error = 'Your account is not activated yet. Please use the activation link sent to your email.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['username'] = (string)$user['username'];
                        $_SESSION['role'] = (string)($user['role'] ?? 'user');

                        unset($_SESSION['campaign_id'], $_SESSION['character_id'], $_SESSION['is_dm']);

                        try {
                            $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
                                ->execute([':id' => (int)$user['id']]);
                        } catch (Throwable $ignored) {
                        }

                        redirect_authenticated_user();
                    }
                } catch (Throwable $exception) {
                    log_login_exception(
                        '[login.php] APP_ENV=' . $appEnv
                        . ' exception: ' . $exception->getMessage()
                        . "\n" . $exception->getTraceAsString()
                    );

                    http_response_code(500);
                    $error = $debug
                        ? 'Login error: ' . $exception->getMessage()
                        : 'Unexpected server error while processing login.';
                }
            }
        }
    }
}

$token = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Ghosts of Velen Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo $token; ?>">
  <link href="https://fonts.googleapis.com/css2?family=Bilbo&family=Jim+Nightshade&family=Quattrocento&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="../css/login.css">
  <script>window.CSRF_TOKEN = "<?php echo $token; ?>";</script>
  <?php if ($recaptchaSiteKey !== '' && $recaptchaSecretKey !== ''): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($recaptchaSiteKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        if (typeof grecaptcha === 'undefined') {
          return;
        }

        grecaptcha.ready(function () {
          grecaptcha.execute('<?php echo htmlspecialchars($recaptchaSiteKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>', { action: 'login' })
            .then(function (token) {
              var target = document.getElementById('g-recaptcha-response');
              if (target) {
                target.value = token;
              }
            });
        });
      });
    </script>
  <?php endif; ?>
</head>
<body>
  <button id="hamburger-btn" aria-label="Open updates menu" aria-controls="blog-drawer" aria-expanded="false">
    <span class="bars"><span class="bar-mid"></span></span>
  </button>

  <aside id="blog-drawer" aria-hidden="true">
    <div class="blog-drawer__header">
      <h2 class="blog-drawer__title">Dispatches Log</h2>
    </div>
    <div class="blog-drawer__content" id="blog-drawer-content"></div>
  </aside>

  <div id="login-page-root">
    <div id="visual-wrapper">
      <div id="splash-border" aria-label="Ghosts of Velen splash frame">
        <div id="splash-inner">
          <img id="splash-image" src="../assets/img/login/gov_splash_01.png" alt="Ghosts of Velen splash">
          <img id="page-title" src="../assets/img/gov_title.png" alt="Ghosts of Velen">
          <div class="login-form-container">
            <h1 class="visually-hidden">Ghosts of Velen</h1>
            <form action="/auth/login.php" method="post" autocomplete="off" novalidate>
              <?php echo csrf_input(); ?>
              <label for="username">Username:</label>
              <input type="text" id="username" name="username" required autofocus>
              <label for="password">Password:</label>
              <input type="password" id="password" name="password" required>
              <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
              <button type="submit">Login</button>
            </form>

            <?php if ($notice !== ''): ?>
              <p class="error-message" style="color:#1f5f32;"><?php echo h($notice); ?></p>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
              <p class="error-message">
                <?php
                echo h($error);
                if ($debug) {
                    echo '<br><small style="opacity:.8">[env: ' . h($appEnv) . ']</small>';
                }
                ?>
              </p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../js/login.js" defer></script>
</body>
</html>
