<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/campaigns.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit;
}

if (($_SESSION['role'] ?? null) === 'admin') {
    echo json_encode(['ok' => true, 'campaigns' => []], JSON_UNESCAPED_SLASHES);
    exit;
}

$campaigns = fetch_user_campaigns(db(), (int)$_SESSION['user_id']);

echo json_encode([
    'ok' => true,
    'campaigns' => $campaigns,
], JSON_UNESCAPED_SLASHES);
