<?php
declare(strict_types=1);

/**
 * includes/log_event.php
 *
 * Backward-compatible logging helper.
 * - Drop-in for previous log_event($message)
 * - Adds optional level, channel and context
 * - Ensures a writable path (project /logs or /tmp fallback)
 * - Simple size-based rotation to avoid unbounded files
 *
 * Usage:
 *   log_event('Something happened');                               // default INFO to security.log
 *   log_event('User created', 'INFO', 'admin', ['user_id'=>123]);  // custom channel + context
 */

if (!function_exists('log_event')) {

    /**
     * Write a single-line log entry.
     *
     * @param string $message  Human-readable message (newlines collapsed)
     * @param string $level    e.g. INFO, WARN, ERROR, DEBUG
     * @param string $channel  log file base name (default 'security')
     * @param array  $context  extra key/vals appended as JSON
     */
    function log_event(string $message, string $level = 'INFO', string $channel = 'security', array $context = []): void
    {
        [$logFile, $rotMax] = _gov_log_resolve_path($channel);

        // Rotate if too large (best-effort; ignore failures)
        if (is_file($logFile) && filesize($logFile) !== false && filesize($logFile) > $rotMax) {
            _gov_log_rotate($logFile);
        }

        // Derive useful request/session metadata
        $ts   = date('c');
        $uid  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $user = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '-';
        $role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '-';

        $ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '-';
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip, 2)[0]);
        }
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '-';

        // Keep each entry to a single line
        $msg = str_replace(["\r\n", "\r", "\n"], ' ', $message);

        // Context as compact JSON (if any)
        $ctx = '';
        if (!empty($context)) {
            // prevent binary pollution
            $safe = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($safe)) {
                $ctx = ' | ' . $safe;
            }
        }

        $line = sprintf(
            "[%s] [%s] [%s] [uid=%s user=%s role=%s ip=%s] %s%s\n",
            $ts,
            strtoupper($level),
            $channel,
            $uid,
            $user,
            $role,
            $ip,
            $msg,
            $ctx
        );

        // Ensure directory exists (best-effort)
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // Write (atomic append with lock). On failure, mirror to PHP error_log.
        $ok = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        if ($ok === false) {
            // last-resort: system log
            @error_log("[gov_log_fallback] " . $line);
        }
    }
}

/**
 * Return candidate log file and rotation max bytes.
 * Honors optional env overrides:
 *   LOG_DIR       - directory for logs (e.g., /home/xxx/logs)
 *   LOG_MAX_BYTES - integer, defaults to 1_000_000
 *
 * Channel 'security' -> security.log
 * Other channels -> {channel}.log
 *
 * @return array{0:string,1:int}
 */
function _gov_log_resolve_path(string $channel): array
{
    // 1) If LOG_DIR is set, prefer it
    $envDir = (string)($_ENV['LOG_DIR'] ?? getenv('LOG_DIR') ?: '');
    if ($envDir !== '') {
        $file = rtrim($envDir, '/').'/'.($channel === 'security' ? 'security.log' : "{$channel}.log");
        return [$file, _gov_log_max_bytes()];
    }

    // 2) Try project /logs (…/ghostsofvelen/logs/)
    $projectLogs = dirname(__DIR__) . '/logs';
    $file = $projectLogs . '/' . ($channel === 'security' ? 'security.log' : "{$channel}.log");
    if (is_dir($projectLogs) && is_writable($projectLogs)) {
        return [$file, _gov_log_max_bytes()];
    }

    // 3) Fallback to /tmp
    $tmp = sys_get_temp_dir() ?: '/tmp';
    $file = rtrim($tmp, '/').'/gov_' . ($channel === 'security' ? 'security' : $channel) . '.log';
    return [$file, _gov_log_max_bytes()];
}

/** Rotation threshold helper. */
function _gov_log_max_bytes(): int
{
    $raw = (string)($_ENV['LOG_MAX_BYTES'] ?? getenv('LOG_MAX_BYTES') ?: '');
    if ($raw !== '' && ctype_digit($raw)) {
        $val = (int)$raw;
        if ($val > 0) return $val;
    }
    return 1_000_000; // ~1 MB default
}

/** Best-effort rotation: move current file to *.YYYYmmdd-HHMMSS.log */
function _gov_log_rotate(string $path): void
{
    $dir = dirname($path);
    $base = basename($path, '.log');
    $stamp = date('Ymd-His');
    $arch = sprintf('%s/%s.%s.log', $dir, $base, $stamp);
    @rename($path, $arch);
}

/**
 * (Optional) Expose the set of candidate paths for UIs that want to display them.
 * Not used by log_event() itself; safe to call from a dashboard.
 *
 * @return array<int,string>
 */
function log_event_candidates(): array
{
    $c = [];
    $envDir = (string)($_ENV['LOG_DIR'] ?? getenv('LOG_DIR') ?: '');
    if ($envDir !== '') {
        $c[] = rtrim($envDir, '/').'/security.log';
    }
    $c[] = dirname(__DIR__) . '/logs/security.log';
    $tmp = sys_get_temp_dir() ?: '/tmp';
    $c[] = rtrim($tmp, '/').'/gov_security.log';
    return array_values(array_unique($c));
}
