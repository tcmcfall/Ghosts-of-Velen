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

    // Prefer POST fields; fall back to JSON payload
    $cid     = (int)($_POST['campaign_id'] ?? $_POST['id'] ?? $json['campaign_id'] ?? $json['id'] ?? 0);
    $confirm = trim((string)($_POST['confirm'] ?? $json['confirm'] ?? ''));

    if ($cid <= 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>['code'=>'BAD_INPUT','message'=>'campaign_id (or id) is required']]);
        exit;
    }

    $pdo = db();

    // Confirm the campaign exists and fetch the name
    $stmt = $pdo->prepare('SELECT id, name FROM campaigns WHERE id = :cid LIMIT 1');
    $stmt->execute([':cid' => $cid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'Campaign not found']]);
        exit;
    }
    $cname = (string)$row['name'];

    // If a confirmation string was provided, enforce exact match
    if ($confirm !== '' && $confirm !== $cname) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>['code'=>'CONFIRM_MISMATCH','message'=>'Confirmation text mismatch']]);
        exit;
    }

    // Delete within a transaction
    $pdo->beginTransaction();

    // 1) campaign_users
    $del1 = $pdo->prepare('DELETE FROM campaign_users WHERE campaign_id = :cid');
    $del1->execute([':cid' => $cid]);
    $deletedCU = $del1->rowCount();

    // 2) characters (optional table; ignore if missing)
    $deletedChars = null;
    try {
        $del2 = $pdo->prepare('DELETE FROM characters WHERE campaign_id = :cid');
        $del2->execute([':cid' => $cid]);
        $deletedChars = $del2->rowCount();
    } catch (\Throwable $ignored) {
        // table may not exist yet; that's fine
    }

    // TODO: delete related calendar/journal/etc. when those tables are finalized

    // 3) campaign itself
    $del3 = $pdo->prepare('DELETE FROM campaigns WHERE id = :cid');
    $del3->execute([':cid' => $cid]);
    $deletedCampaign = $del3->rowCount();

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'deleted' => (bool)$deletedCampaign,
        'campaign' => [
            'id'   => (int)$cid,
            'name' => $cname,
        ],
        'affected' => [
            'campaign_users' => $deletedCU,
            'characters'     => $deletedChars, // may be null if table missing
        ],
    ]);
} catch (\Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
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
