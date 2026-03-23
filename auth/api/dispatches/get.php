<?php
// auth/api/dispatches/get.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../../includes/acl.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = db();
$sessionCampaignId = isset($_SESSION['campaign_id']) ? (int)$_SESSION['campaign_id'] : null;
$isAdminUser = isAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid id']); exit; }

$stmt = $pdo->prepare('SELECT * FROM dispatches WHERE id = :id LIMIT 1');
$stmt->execute([':id'=>$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }

$cid = isset($row['campaign_id']) ? (int)$row['campaign_id'] : null;
if (!$isAdminUser && $cid !== null && $sessionCampaignId !== $cid) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit;
}

echo json_encode($row);
