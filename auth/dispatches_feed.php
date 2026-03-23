<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$limit = max(1, min((int)($_GET['limit'] ?? 10), 50));
$offset = max(0, (int)($_GET['offset'] ?? 0));

try {
    $stmt = db()->prepare(
        'SELECT id, title, content_html, thumbnail_url, created_at, updated_at
           FROM dispatches
          WHERE campaign_id IS NULL
          ORDER BY created_at DESC, id DESC
          LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    echo json_encode([], JSON_UNESCAPED_SLASHES);
}
