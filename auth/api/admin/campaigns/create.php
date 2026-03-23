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
    $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
    $dmUsername = isset($_POST['dm_username']) ? trim((string)$_POST['dm_username']) : '';

    if ($name === '' || mb_strlen($name) > 128) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_NAME','message'=>'Bad name']]); exit; }
    if ($dmUsername === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_DM','message'=>'Campaigns must have a DM']]); exit; }

    $pdo = db();
    $uid = $pdo->prepare('SELECT id FROM users WHERE username=:u LIMIT 1');
    $uid->execute([':u'=>$dmUsername]);
    $dmId = (int)($uid->fetchColumn() ?: 0);
    if ($dmId<=0) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>['code'=>'DM_NOT_FOUND','message'=>'DM user not found']]); exit; }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO campaigns (name, dm_user_id) VALUES (:n, :dm)')->execute([':n'=>$name, ':dm'=>$dmId]);
    $cid = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT IGNORE INTO campaign_users (campaign_id,user_id) VALUES (:cid,:uid)')->execute([':cid'=>$cid, ':uid'=>$dmId]);
    $pdo->commit();

    echo json_encode(['ok'=>true, 'campaign_id'=>$cid]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>['code'=>'SERVER_ERROR','message'=>'Unexpected server error']]);
}
