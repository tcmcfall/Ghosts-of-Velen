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
    $in = [];
    if (strpos($ctype, 'application/json') === 0) {
        $raw = file_get_contents('php://input') ?: '';
        $in = json_decode($raw, true);
        if (!is_array($in)) $in = [];
    }

    // Prefer POST fields; fall back to JSON payload
    $cid = (int)($_POST['campaign_id'] ?? $in['campaign_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? $in['user_id'] ?? 0);
    $username = trim((string)($_POST['username'] ?? $in['username'] ?? ''));

    if ($cid <= 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_INPUT','message'=>'campaign_id is required']]);
        exit;
    }
    if ($userId <= 0 && $username === '') {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_INPUT','message'=>'Provide user_id or username']]);
        exit;
    }

    $pdo = db();

    // Confirm campaign exists
    $stmt = $pdo->prepare('SELECT id, name FROM campaigns WHERE id = :cid LIMIT 1');
    $stmt->execute([':cid' => $cid]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'Campaign not found']]);
        exit;
    }

    // Resolve user_id if only username was given
    if ($userId <= 0) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $userId = (int)($stmt->fetchColumn() ?: 0);
        if ($userId <= 0) {
            http_response_code(404);
            echo json_encode(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'User not found by username']]);
            exit;
        }
    } else {
        // Optionally fetch username for response
        if ($username === '') {
            $stmt = $pdo->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $username = (string)($stmt->fetchColumn() ?: '');
        }
    }

    // Add membership (idempotent)
    $ins = $pdo->prepare('INSERT IGNORE INTO campaign_users (campaign_id, user_id) VALUES (:cid, :uid)');
    $ins->execute([':cid' => $cid, ':uid' => $userId]);
    $added = ($ins->rowCount() > 0);

    // Optionally compute new member_count (cheap and useful for UI refresh)
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM campaign_users WHERE campaign_id = :cid');
    $countStmt->execute([':cid' => $cid]);
    $memberCount = (int)$countStmt->fetchColumn();

    echo json_encode([
        'ok'            => true,
        'added'         => $added,                 // false if already a member
        'campaign_id'   => (int)$campaign['id'],
        'campaign_name' => (string)$campaign['name'],
        'user_id'       => $userId,
        'username'      => $username !== '' ? $username : null,
        'member_count'  => $memberCount,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => [
            'code'    => 'SERVER_ERROR',
            'message' => 'Unexpected server error.',
            // In dev you can uncomment the next line to surface details:
            // 'debug'   => $e->getMessage(),
        ],
    ]);
}
