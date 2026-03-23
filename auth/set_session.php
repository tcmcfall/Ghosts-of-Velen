<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/campaigns.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: /auth/login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!csrf_validate_request()) {
    http_response_code(400);
    exit('Bad Request: CSRF validation failed.');
}

$campaignId = (int)($_POST['campaign_id'] ?? 0);
if ($campaignId <= 0) {
    http_response_code(400);
    exit('Campaign required.');
}

$rawCharacterId = trim((string)($_POST['character_id'] ?? ''));
$characterId = $rawCharacterId === '' ? null : (int)$rawCharacterId;
$userId = (int)$_SESSION['user_id'];

try {
    $pdo = db();
    $campaign = require_campaign_membership($pdo, $userId, $campaignId);

    if ($characterId === null && !$campaign['is_dm']) {
        http_response_code(400);
        exit('Character required.');
    }

    if ($characterId !== null) {
        require_campaign_character($pdo, $userId, $campaignId, $characterId);
    }

    apply_campaign_session_scope($campaignId, $characterId, $campaign['is_dm']);
    header('Location: /map.php');
    exit;
} catch (RuntimeException $exception) {
    http_response_code(403);
    exit($exception->getMessage());
}
