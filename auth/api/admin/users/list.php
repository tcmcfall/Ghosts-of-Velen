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

    // ---------- Single item mode (detail panel): GET ?id=123 ----------
    if (isset($_GET['id']) && $_GET['id'] !== '') {
        $id = (int)$_GET['id'];

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.role,
                u.force_password_change,
                u.is_active,
                u.last_login_at
            FROM users u
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            echo json_encode(['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'User not found']]);
            exit;
        }

        // Campaign memberships for this user
        $mc = $pdo->prepare("
            SELECT c.id AS campaign_id, c.name
            FROM campaign_users cu
            JOIN campaigns c ON c.id = cu.campaign_id
            WHERE cu.user_id = :uid
            ORDER BY c.name ASC
        ");
        $mc->execute([':uid' => $id]);
        $campaigns = $mc->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'ok'   => true,
            'id'   => (int)$u['id'],
            'username'              => (string)$u['username'],
            'full_name'             => $u['full_name'] !== null ? (string)$u['full_name'] : null,
            'email'                 => (string)$u['email'],
            'role'                  => (string)$u['role'],
            'force_password_change' => (int)($u['force_password_change'] ?? 0),
            'is_active'             => isset($u['is_active']) ? (int)$u['is_active'] : null,
            'last_login_at'         => $u['last_login_at'] ?? null,
            'campaigns'             => array_map(static fn($r) => [
                'campaign_id' => (int)$r['campaign_id'],
                'name'        => (string)$r['name'],
            ], $campaigns),
        ]);
        exit;
    }

    // ---------- List mode (table/search/pagination) ----------
    $page   = isset($_GET['page'])   ? (int)$_GET['page']   : 1;
    $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 25;
    // Accept both 'search' and 'q'
    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : trim((string)($_GET['q'] ?? ''));

    $sort   = isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : 'username';
    $dir    = isset($_GET['dir'])  ? strtolower((string)$_GET['dir'])  : 'asc';

    // Clamp pagination
    if ($page < 1)  { $page = 1; }
    if ($limit < 1) { $limit = 1; }
    if ($limit > 100) { $limit = 100; }
    $offset = ($page - 1) * $limit;

    // Whitelist sort columns
    $allowedSort = [
        'id'            => 'u.id',
        'username'      => 'u.username',
        'full_name'     => 'u.full_name',
        'email'         => 'u.email',
        'role'          => 'u.role',
        'last_login_at' => 'u.last_login_at'
    ];
    $sortCol = $allowedSort[$sort] ?? $allowedSort['username'];
    $dirSql  = $dir === 'desc' ? 'DESC' : 'ASC';

    // WHERE (search by username, email, full_name)
    $where  = '';
    $params = [];
    if ($search !== '') {
        $where = "WHERE (u.username LIKE :q OR u.email LIKE :q OR u.full_name LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }

    // Total count
    $sqlCount = "SELECT COUNT(*) AS total FROM users u $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)($stmtCount->fetchColumn() ?: 0);

    // Page query
    $sqlPage = "
        SELECT
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.role,
            u.force_password_change,
            u.is_active,
            u.last_login_at
        FROM users u
        $where
        ORDER BY $sortCol $dirSql, u.id ASC
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

    // Shape output
    $users = array_map(static function(array $r): array {
        return [
            'id'                     => (int)$r['id'],
            'username'               => (string)$r['username'],
            'full_name'              => $r['full_name'] !== null ? (string)$r['full_name'] : null,
            'email'                  => (string)$r['email'],
            'role'                   => (string)$r['role'],
            'force_password_change'  => (int)($r['force_password_change'] ?? 0),
            'is_active'              => isset($r['is_active']) ? (int)$r['is_active'] : null,
            'last_login_at'          => $r['last_login_at'] ?? null,
        ];
    }, $rows);

    echo json_encode([
        'ok'      => true,
        'data'    => $users,
        'items'   => $users, // alias for UI compatibility
        'page'    => $page,
        'limit'   => $limit,
        'total'   => $total,
        'sort'    => $sort,
        'dir'     => $dir,
        'search'  => $search,
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
