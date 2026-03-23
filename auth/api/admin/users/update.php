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
    $uid   = (int)($_POST['user_id'] ?? 0);
    $full  = isset($_POST['full_name']) ? trim((string)$_POST['full_name']) : null;
    $email = isset($_POST['email'])     ? trim((string)$_POST['email'])     : null;
    $role  = isset($_POST['role'])      ? trim((string)$_POST['role'])      : null;
    $fpc   = isset($_POST['force_password_change']) ? 1 : 0;
    $isAct = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    $resend= isset($_POST['resend_invite']) ? (int)$_POST['resend_invite'] : 0;

    if ($uid<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_ID','message'=>'Bad user id']]); exit; }
    if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_EMAIL','message'=>'Invalid email']]); exit;
    }
    if ($role !== null && !in_array($role, ['user','admin'], true)) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_ROLE','message'=>'Invalid role']]); exit;
    }

    $pdo = db();

    // Ensure exists
    $u = $pdo->prepare('SELECT id, username, email, is_active FROM users WHERE id=:id LIMIT 1');
    $u->execute([':id'=>$uid]);
    $cur = $u->fetch(PDO::FETCH_ASSOC);
    if (!$cur) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'User not found']]); exit; }

    // Unique email (if changed)
    if ($email !== null && $email !== '' && strcasecmp((string)$cur['email'], $email) !== 0) {
        $dup = $pdo->prepare('SELECT 1 FROM users WHERE email=:e AND id<>:id LIMIT 1');
        $dup->execute([':e'=>$email, ':id'=>$uid]);
        if ($dup->fetch()) { http_response_code(409); echo json_encode(['ok'=>false,'error'=>['code'=>'CONFLICT','message'=>'Email already in use']]); exit; }
    }

    // Build dynamic update
    $sets = ['force_password_change=:fpc'];
    $params = [':fpc'=>$fpc, ':id'=>$uid];
    if ($full !== null)  { $sets[] = 'full_name=:f'; $params[':f'] = ($full !== '' ? $full : null); }
    if ($email !== null) { $sets[] = 'email=:e';     $params[':e'] = $email ?? null; }
    if ($role  !== null) { $sets[] = 'role=:r';      $params[':r'] = $role; }
    if ($isAct !== null) { $sets[] = 'is_active=:a'; $params[':a'] = $isAct ? 1 : 0; }

    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id=:id';
    $pdo->prepare($sql)->execute($params);

    // Resend invite (only makes sense if not active)
    $mailInfo = null;
    if ($resend) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE users SET activation_token=:t, activation_token_created_at=NOW() WHERE id=:id')
            ->execute([':t'=>$token, ':id'=>$uid]);
        try {
            $to = $email ?: (string)$cur['email'];
            if ($to) gov_send_activation_email($to, (string)$cur['username'], $token);
            $mailInfo = 'sent';
        } catch (Throwable $e) {
            if ($DEV) $mailInfo = 'error: '.$e->getMessage();
        }
    }

    echo json_encode(['ok'=>true,'mail'=>$mailInfo]);
} catch (Throwable $e) {
    http_response_code(500);
    $out = ['ok'=>false,'error'=>['code'=>'SERVER_ERROR','message'=>'Unexpected server error']];
    if ($DEV) $out['error']['debug'] = $e->getMessage();
    echo json_encode($out);
}
