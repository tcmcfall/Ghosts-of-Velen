<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../auth/bootstrap.php';
require_once __DIR__ . '/../../../auth/db_connect.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/mailer.php';

if (($_SESSION['role'] ?? null) !== 'admin') { http_response_code(403); exit('Admins only'); }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
if (!csrf_validate_request()) { http_response_code(400); exit('CSRF failed'); }
header('Content-Type: application/json; charset=utf-8');

$DEV = (($_ENV['APP_ENV'] ?? 'prod') === 'dev');

try {
    $username = isset($_POST['username'])  ? trim((string)$_POST['username'])  : '';
    $fullName = isset($_POST['full_name']) ? trim((string)$_POST['full_name']) : '';
    $email    = isset($_POST['email'])     ? trim((string)$_POST['email'])     : '';
    $flow     = isset($_POST['flow'])      ? trim((string)$_POST['flow'])      : 'activation';

    if (!preg_match('/^[a-zA-Z0-9]{2,10}$/', $username)) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_USERNAME','message'=>'Username must be 2–10 alphanumeric.']]); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_EMAIL','message'=>'Invalid email.']]); exit;
    }
    if (!in_array($flow, ['activation','initial_password'], true)) $flow = 'activation';

    $pdo = db();
    $pdo->beginTransaction();

    // Unique constraint
    $dup = $pdo->prepare('SELECT 1 FROM users WHERE username=:u OR email=:e LIMIT 1');
    $dup->execute([':u'=>$username, ':e'=>$email]);
    if ($dup->fetch()) {
        $pdo->rollBack();
        http_response_code(409); echo json_encode(['ok'=>false,'error'=>['code'=>'CONFLICT','message'=>'Username or email already in use.']]); exit;
    }

    // Password and flags
    $forceChange  = 0;
    $isActive     = 0;
    if ($flow === 'initial_password') {
        $initial = bin2hex(random_bytes(4)) . '!' . strtoupper(bin2hex(random_bytes(2)));
        $passwordHash = password_hash($initial, PASSWORD_DEFAULT);
        $forceChange  = 1;
        $isActive     = 1;
    } else {
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }

    $ins = $pdo->prepare(
        'INSERT INTO users (username, full_name, email, password, role, is_active, force_password_change)
         VALUES (:u, :f, :e, :p, "user", :active, :fpc)'
    );
    $ins->execute([
        ':u'=>$username,
        ':f'=>$fullName !== '' ? $fullName : null,
        ':e'=>$email,
        ':p'=>$passwordHash,
        ':active'=>$isActive,
        ':fpc'=>$forceChange
    ]);
    $userId = (int)$pdo->lastInsertId();

    if ($flow === 'activation') {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE users SET activation_token=:t, activation_token_created_at=NOW() WHERE id=:id')
            ->execute([':t'=>$token, ':id'=>$userId]);
        try { gov_send_activation_email($email, $username, $token); } catch (Throwable $e) {
            // don’t fail the whole request on mail errors; surface in dev
            if ($DEV) $mailErr = $e->getMessage();
        }
    }

    $pdo->commit();

    $out = ['ok'=>true,'user_id'=>$userId,'flow'=>$flow];
    if (isset($mailErr)) $out['mail_error'] = $mailErr;
    echo json_encode($out);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    $out = ['ok'=>false,'error'=>['code'=>'SERVER_ERROR','message'=>'Unexpected server error.']];
    if ($DEV) $out['error']['debug'] = $e->getMessage();
    echo json_encode($out);
}
