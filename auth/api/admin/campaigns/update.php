<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../auth/bootstrap.php';
require_once __DIR__ . '/../../../auth/db_connect.php';
require_once __DIR__ . '/../../../includes/csrf.php';

if (($_SESSION['role'] ?? null) !== 'admin') { http_response_code(403); exit('Admins only'); }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
if (!csrf_validate_request()) { http_response_code(400); exit('CSRF failed'); }
header('Content-Type: application/json; charset=utf-8');

try {
    $cid = (int)($_POST['campaign_id'] ?? 0);
    $name = isset($_POST['name']) ? trim((string)$_POST['name']) : null;
    $dm   = isset($_POST['dm_username']) ? trim((string)$_POST['dm_username']) : null;

    if ($cid<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_ID','message'=>'Bad campaign id']]); exit; }

    $pdo = db();
    $exists = $pdo->prepare('SELECT id, name, dm_user_id FROM campaigns WHERE id=:id LIMIT 1');
    $exists->execute([':id'=>$cid]);
    $cur = $exists->fetch(PDO::FETCH_ASSOC);
    if (!$cur) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'Campaign not found']]); exit; }

    $sets = [];
    $params = [':id'=>$cid];

    if ($name !== null) {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 128) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_NAME','message'=>'Bad name']]); exit; }
        $sets[] = 'name=:n'; $params[':n'] = $name;
    }

    if ($dm !== null) {
        if ($dm === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_DM','message'=>'Campaign must have a DM']]); exit; }
        $uid = $pdo->prepare('SELECT id FROM users WHERE username=:u LIMIT 1');
        $uid->execute([':u'=>$dm]);
        $dmId = (int)($uid->fetchColumn() ?: 0);
        if ($dmId<=0) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>['code'=>'DM_NOT_FOUND','message'=>'DM user not found']]); exit; }
        $sets[] = 'dm_user_id=:dm'; $params[':dm'] = $dmId;
    }

    if ($sets) {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE campaigns SET '.implode(',',$sets).' WHERE id=:id')->execute($params);
        if (isset($params[':dm'])) {
            $pdo->prepare('INSERT IGNORE INTO campaign_users (campaign_id,user_id) VALUES (:cid,:uid)')->execute([':cid'=>$cid, ':uid'=>$params[':dm']]);
        }
        $pdo->commit();
    }

    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>['code'=>'SERVER_ERROR','message'=>'Unexpected server error']]);
}
