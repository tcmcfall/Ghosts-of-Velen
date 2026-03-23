<?php
// auth/api/dispatches/create.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/acl.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate_request()) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Bad request']);
  exit;
}

$pdo = db();
$userId = (int)($_SESSION['user_id'] ?? 0);
$sessionCampaignId = isset($_SESSION['campaign_id']) ? (int)$_SESSION['campaign_id'] : null;
$isAdminUser = isAdmin();
$isCampaignDm = $sessionCampaignId !== null && !empty($_SESSION['is_dm']);

if (!$isAdminUser && !$isCampaignDm) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }

$title = trim((string)($_POST['title'] ?? ''));
$content = trim((string)($_POST['content_html'] ?? ''));
$thumbnailUrl = trim((string)($_POST['thumbnail_url'] ?? ''));
$campaignId = $isAdminUser ? null : $sessionCampaignId;

if ($title === '' || $content === '') {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Title and content required']); exit;
}

$stmt = $pdo->prepare('INSERT INTO dispatches (title, content_html, thumbnail_url, campaign_id, created_by, created_at, updated_at)
                       VALUES (:t,:c,:u,:cid,:uid,NOW(),NOW())');
$stmt->execute([
  ':t' => $title,
  ':c' => $content,
  ':u' => $thumbnailUrl !== '' ? $thumbnailUrl : null,
  ':cid'=> $campaignId,
  ':uid'=> $userId ?: null,
]);

echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
