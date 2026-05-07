<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_once __DIR__ . '/../../auth/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$started = microtime(true);
$statusCode = 200;
$payload = [
    'ok' => true,
    'service' => 'fts-api',
    'timestamp' => gmdate('c'),
    'php_version' => PHP_VERSION,
    'app_env' => (string)($_ENV['APP_ENV'] ?? 'unknown'),
    'db' => 'ok',
];

try {
    $pdo = db();
    $row = $pdo->query('SELECT 1 AS ok')->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || (string)($row['ok'] ?? '') !== '1') {
        throw new RuntimeException('Health query mismatch.');
    }
} catch (Throwable $e) {
    $statusCode = 503;
    $payload['ok'] = false;
    $payload['db'] = 'error';
    $payload['error'] = 'database_unavailable';
}

$payload['latency_ms'] = (int)round((microtime(true) - $started) * 1000);

http_response_code($statusCode);
echo json_encode($payload, JSON_UNESCAPED_SLASHES);

