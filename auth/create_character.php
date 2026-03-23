<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/campaigns.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

if (!csrf_validate_request()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'csrf_failed']);
    exit;
}

$campaignId = (int)($_POST['campaign_id'] ?? 0);
$name = (string)($_POST['name'] ?? '');

if ($campaignId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'campaign_required']);
    exit;
}

try {
    $character = create_campaign_character(
        db(),
        (int)$_SESSION['user_id'],
        $campaignId,
        $name
    );

    echo json_encode([
        'ok' => true,
        'character' => [
            'id' => $character['id'],
            'name' => $character['name'],
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (RuntimeException $exception) {
    $message = $exception->getMessage();
    $status = $message === 'You are not assigned to that campaign.' ? 403 : 422;
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
}
