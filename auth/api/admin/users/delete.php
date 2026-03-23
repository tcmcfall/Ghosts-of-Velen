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
    $uid      = (int)($_POST['user_id'] ?? $_POST['id'] ?? $json['user_id'] ?? $json['id'] ?? 0);
    $username = trim((string)($_POST['username'] ?? $json['username'] ?? ''));
    $confirm  = trim((string)($_POST['confirm']  ?? $json['confirm']  ?? ''));

    $pdo = db();

    // Resolve user by username if id missing
    if ($uid <= 0 && $username !== '') {
        $q = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $q->execute([':u' => $username]);
        $uid = (int)($q->fetchColumn() ?: 0);
    }

    // Load username for validation/response
    $q = $pdo->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
    $q->execute([':id' => $uid]);
    $foundUsername = (string)($q->fetchColumn() ?: '');

    if ($uid <= 0 || $foundUsername === '') {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'User not found']]);
        exit;
    }

    // If client provided a confirm string, enforce it (optional)
    if ($confirm !== '' && $confirm !== $foundUsername) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>['code'=>'CONFIRM_MISMATCH','message'=>'Confirmation text mismatch']]);
        exit;
    }

    // Prevent deleting a user who is currently DM of any campaign
    $dmCount = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE dm_user_id = :uid');
    $dmCount->execute([':uid' => $uid]);
    if ((int)$dmCount->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>['code'=>'IS_DM','message'=>'User is DM of one or more campaigns. Transfer DM first.']]);
        exit;
    }

    // Perform deletes (transaction)
    $pdo->beginTransaction();

    // 1) Remove campaign memberships
    $delCU = $pdo->prepare('DELETE FROM campaign_users WHERE user_id = :uid');
    $delCU->execute([':uid' => $uid]);
    $removedMemberships = $delCU->rowCount();

    // 2) Remove characters (optional table; ignore if missing)
    $removedChars = null;
    try {
        $delCh = $pdo->prepare('DELETE FROM characters WHERE user_id = :uid');
        $delCh->execute([':uid' => $uid]);
        $removedChars = $delCh->rowCount();
    } catch (\Throwable $ignored) {
        // characters table may not exist yet
    }

    // 3) Delete the user
    $delU = $pdo->prepare('DELETE FROM users WHERE id = :uid');
    $delU->execute([':uid' => $uid]);
    $deletedUser = ($delU->rowCount() > 0);

    $pdo->commit();

    echo json_encode([
        'ok'       => true,
        'deleted'  => $deletedUser,
        'user'     => ['id' => $uid, 'username' => $foundUsername],
        'affected' => [
            'campaign_users' => $removedMemberships,
            'characters'     => $removedChars, // may be null if table absent
        ],
    ]);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (\Throwable $ignored) {}
    }
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
