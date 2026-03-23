<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';          // NOT '/../../../auth/bootstrap.php'
require_once __DIR__ . '/../../../db_connect.php';         // NOT '/../../../auth/db_connect.php'
require_once __DIR__ . '/../../../../includes/csrf.php';   // needs 4x .. to reach project root

header('Content-Type: application/json; charset=utf-8');

// Admin-only
if (($_SESSION['role'] ?? null) !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Admins only']]);
    exit;
}

// POST only
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'POST required']]);
    exit;
}

// CSRF
if (!csrf_validate_request()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => ['code' => 'CSRF_FAILED', 'message' => 'CSRF token missing/invalid']]);
    exit;
}

try {
    // Accept x-www-form-urlencoded (dashboard default) OR JSON
    $ctype = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $json = [];
    if (strpos($ctype, 'application/json') === 0) {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        if (!is_array($json)) $json = [];
    }

    // Prefer POST; fall back to JSON
    $cid      = (int)($_POST['campaign_id'] ?? $_POST['id'] ?? $json['campaign_id'] ?? $json['id'] ?? 0);
    $userId   = (int)($_POST['user_id'] ?? $json['user_id'] ?? 0);
    $username = trim((string)($_POST['username'] ?? $json['username'] ?? ''));

    if ($cid <= 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_INPUT','message'=>'campaign_id (or id) is required']]);
        exit;
    }
    if ($userId <= 0 && $username === '') {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_INPUT','message'=>'Provide user_id or username']]);
        exit;
    }

    $pdo = db();

    // Fetch campaign + DM
    $stmt = $pdo->prepare('SELECT id, name, dm_user_id FROM campaigns WHERE id = :cid LIMIT 1');
    $stmt->execute([':cid' => $cid]);
    $camp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$camp) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'Campaign not found']]);
        exit;
    }
    $dmId = (int)($camp['dm_user_id'] ?? 0);

    // Resolve user_id from username if needed
    if ($userId <= 0) {
        $u = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $u->execute([':u' => $username]);
        $userId = (int)($u->fetchColumn() ?: 0);
        if ($userId <= 0) {
            http_response_code(404);
            echo json_encode(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'User not found by username']]);
            exit;
        }
    } else if ($username === '') {
        // Get username for response if not provided
        $u = $pdo->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
        $u->execute([':id' => $userId]);
        $username = (string)($u->fetchColumn() ?: '');
    }

    // Prevent removing current DM
    if ($dmId > 0 && $userId === $dmId) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>['code'=>'CANNOT_REMOVE_DM','message'=>'Cannot remove current DM. Change DM first.']]);
        exit;
    }

    // Remove membership
    $del = $pdo->prepare('DELETE FROM campaign_users WHERE campaign_id = :cid AND user_id = :uid');
    $del->execute([':cid' => $cid, ':uid' => $userId]);
    $removed = ($del->rowCount() > 0);

    // New member count (optional but useful for UI)
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM campaign_users WHERE campaign_id = :cid');
    $cnt->execute([':cid' => $cid]);
    $memberCount = (int)$cnt->fetchColumn();

    echo json_encode([
        'ok'            => true,
        'removed'       => $removed, // false if the user was not a member
        'campaign_id'   => (int)$camp['id'],
        'campaign_name' => (string)$camp['name'],
        'user_id'       => $userId,
        'username'      => $username !== '' ? $username : null,
        'member_count'  => $memberCount,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => [
            'code'    => 'SERVER_ERROR',
            'message' => 'Unexpected server error.',
            // 'debug' => $e->getMessage(), // uncomment in dev if needed
        ],
    ]);
}
