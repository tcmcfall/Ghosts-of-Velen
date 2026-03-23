<?php
// auth/api/dispatches/list.php
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

$search = trim((string)($_GET['search'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'created_at');
$dir  = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$includeGlobal = (int)($_GET['include_global'] ?? 1) === 1;
$campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;

// Sorting whitelist
$sortCol = 'created_at';
if (in_array($sort, ['created_at','updated_at','title'], true)) $sortCol = $sort;

$params = [];
$scopeClauses = [];

if (!$isAdminUser) {
  if ($campaignId !== null && $sessionCampaignId !== null && $campaignId !== $sessionCampaignId) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Forbidden campaign scope']);
    exit;
  }

  if ($sessionCampaignId === null) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Missing campaign scope']);
    exit;
  }

  $scopeClauses[] = 'd.campaign_id = :cid';
  $params[':cid'] = $campaignId ?? $sessionCampaignId;
} else {
  if ($includeGlobal) {
    $scopeClauses[] = 'd.campaign_id IS NULL';
  }

  if ($campaignId !== null) {
    $scopeClauses[] = 'd.campaign_id = :cid';
    $params[':cid'] = $campaignId;
  }
}

$whereClauses = [];
if ($scopeClauses !== []) {
  $whereClauses[] = '(' . implode(' OR ', $scopeClauses) . ')';
}

if ($search !== '') {
  $whereClauses[] = '(d.title LIKE :q OR d.content_html LIKE :q)';
  $params[':q'] = '%'.$search.'%';
}

$whereSql = $whereClauses ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

$sql = "SELECT d.id, d.title, d.thumbnail_url, d.content_html, d.campaign_id,
               d.created_by, d.created_at, d.updated_at
        FROM dispatches d
        $whereSql
        ORDER BY d.$sortCol $dir
        LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode($rows);
