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

try {
    $pdo = db();

    // ---------- Single item mode (used by detail panel): GET ?id=123 ----------
    if (isset($_GET['id']) && $_GET['id'] !== '') {
        $id = (int)$_GET['id'];

        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.dm_user_id, dm.username AS dm_username
            FROM campaigns c
            LEFT JOIN users dm ON dm.id = c.dm_user_id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Campaign not found']]);
            exit;
        }

        $stmtM = $pdo->prepare("
            SELECT u.id, u.username
            FROM campaign_users cu
            JOIN users u ON u.id = cu.user_id
            WHERE cu.campaign_id = :id
            ORDER BY u.username ASC
        ");
        $stmtM->execute([':id' => $id]);
        $members = $stmtM->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'ok'          => true,
            'id'          => (int)$row['id'],
            'name'        => (string)$row['name'],
            'dm_user_id'  => $row['dm_user_id'] !== null ? (int)$row['dm_user_id'] : null,
            'dm_username' => $row['dm_username'] ?? null,
            'members'     => array_map(static fn($m) => [
                'id'       => (int)$m['id'],
                'username' => (string)$m['username'],
            ], $members),
        ]);
        exit;
    }

    // ---------- List mode (table/search/pagination) ----------
    $page   = isset($_GET['page'])   ? (int)$_GET['page']   : 1;
    $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 25;
    // Accept both 'search' and 'q'
    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : trim((string)($_GET['q'] ?? ''));

    $sort   = isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : 'name';
    $dir    = isset($_GET['dir'])  ? strtolower((string)$_GET['dir'])  : 'asc';

    $allowedSort = [
        'name'        => 'c.name',
        'id'          => 'c.id',
        'dm_username' => 'dm.username'
    ];
    $sortCol = $allowedSort[$sort] ?? $allowedSort['name'];
    $dirSql  = $dir === 'desc' ? 'DESC' : 'ASC';

    if ($page < 1)  { $page = 1; }
    if ($limit < 1) { $limit = 1; }
    if ($limit > 100) { $limit = 100; }
    $offset = ($page - 1) * $limit;

    $where = '';
    $params = [];
    if ($search !== '') {
        $where = "WHERE (c.name LIKE :q OR dm.username LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }

    // Count
    $sqlCount = "
        SELECT COUNT(*)
        FROM campaigns c
        LEFT JOIN users dm ON dm.id = c.dm_user_id
        $where
    ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)($stmtCount->fetchColumn() ?: 0);

    // Page of campaigns
    $sqlPage = "
        SELECT
            c.id,
            c.name,
            c.dm_user_id,
            dm.username AS dm_username
        FROM campaigns c
        LEFT JOIN users dm ON dm.id = c.dm_user_id
        $where
        ORDER BY $sortCol $dirSql, c.id ASC
        LIMIT :limit OFFSET :offset
    ";
    $stmtPage = $pdo->prepare($sqlPage);
    foreach ($params as $k => $v) {
        $stmtPage->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmtPage->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmtPage->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtPage->execute();
    $rows = $stmtPage->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$rows) {
        echo json_encode([
            'ok'     => true,
            'data'   => [],
            'items'  => [],   // also expose as 'items' for UI compatibility
            'page'   => $page,
            'limit'  => $limit,
            'total'  => $total,
            'sort'   => $sort,
            'dir'    => $dir,
            'search' => $search,
        ]);
        exit;
    }

    // Fetch members for the shown campaigns
    $campaignIds = array_map(static fn($r) => (int)$r['id'], $rows);
    $in = implode(',', array_fill(0, count($campaignIds), '?'));

    $sqlMembers = "
        SELECT cu.campaign_id, u.id AS user_id, u.username
        FROM campaign_users cu
        JOIN users u ON u.id = cu.user_id
        WHERE cu.campaign_id IN ($in)
        ORDER BY u.username ASC
    ";
    $stmtMembers = $pdo->prepare($sqlMembers);
    foreach ($campaignIds as $i => $cid) {
        $stmtMembers->bindValue($i + 1, $cid, PDO::PARAM_INT);
    }
    $stmtMembers->execute();
    $memberRows = $stmtMembers->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $membersByCampaign = [];
    foreach ($memberRows as $mr) {
        $cid = (int)$mr['campaign_id'];
        $membersByCampaign[$cid] ??= [];
        $membersByCampaign[$cid][] = [
            'id'       => (int)$mr['user_id'],
            'username' => (string)$mr['username'],
        ];
    }

    $out = [];
    foreach ($rows as $r) {
        $cid = (int)$r['id'];
        $members = $membersByCampaign[$cid] ?? [];
        $out[] = [
            'id'           => $cid,
            'name'         => (string)$r['name'],
            'dm_user_id'   => $r['dm_user_id'] !== null ? (int)$r['dm_user_id'] : null,
            'dm_username'  => $r['dm_username'] ?? null,
            'members'      => $members,
            'member_count' => count($members),
        ];
    }

    echo json_encode([
        'ok'     => true,
        'data'   => $out,
        'items'  => $out,  // for UI compatibility
        'page'   => $page,
        'limit'  => $limit,
        'total'  => $total,
        'sort'   => $sort,
        'dir'    => $dir,
        'search' => $search,
    ]);
} catch (\Throwable $e) {
    $debug = (isset($_ENV['APP_ENV']) && stripos((string)$_ENV['APP_ENV'], 'prod') !== 0);
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => [
            'code'    => 'SERVER_ERROR',
            'message' => 'Unexpected server error.',
            'debug'   => $debug ? $e->getMessage() : null,
        ],
    ]);
}
