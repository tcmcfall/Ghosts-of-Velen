<?php
declare(strict_types=1);

/**
 * includes/rate_limiter.php
 *
 * Drop-in replacement for is_rate_limited().
 * - Uses a sliding window (per key) based on hit timestamps.
 * - File-backed with flock() for concurrency safety.
 * - Works with any key (IP address, email, "smtp", etc.).
 * - Returns TRUE when the new hit would exceed $limit within $window seconds.
 *
 * Backward compatible:
 *   is_rate_limited($ipOrKey, $limit = 5, $window = 3600): bool
 *
 * Extras you can use (optional):
 *   rate_limit_consume($key, $limit, $window): array   // consume 1 hit, returns status
 *   rate_limit_status($key, $limit, $window): array    // check without consuming
 *   rate_limit_reset($key): void                       // clear window for key
 */

if (!function_exists('is_rate_limited')) {
    function is_rate_limited(string $key, int $limit = 5, int $window = 3600): bool
    {
        $st = rate_limit_consume($key, $limit, $window);
        return $st['limited'];
    }
}

/**
 * Consume 1 hit and return status.
 * @return array{
 *   key:string, limit:int, window:int, count:int, remaining:int, limited:bool,
 *   reset_in:int, reset_at:int, file:string
 * }
 */
function rate_limit_consume(string $key, int $limit, int $window): array
{
    $file = _rl_path($key);
    $now  = time();

    // Open file (create if missing), lock exclusively
    $fp = @fopen($file, 'c+');
    if ($fp === false) {
        // Fallback: act as if unlimited but safe
        return _rl_status_out($key, $limit, $window, 1, false, $now, $file);
    }

    try {
        @flock($fp, LOCK_EX);

        // Read current data
        $raw  = stream_get_contents($fp);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data)) { $data = []; }

        $hits = isset($data['hits']) && is_array($data['hits']) ? $data['hits'] : [];

        // Keep only hits within the window
        $cutoff = $now - $window;
        $hits = array_values(array_filter($hits, static function ($t) use ($cutoff) {
            return is_int($t) && $t >= $cutoff;
        }));

        // Add this hit
        $hits[] = $now;
        $count = count($hits);

        // Trim to avoid unbounded growth (keep a small buffer)
        $maxKeep = max($limit * 4, 64);
        if ($count > $maxKeep) {
            $hits = array_slice($hits, -$maxKeep);
            $count = count($hits);
        }

        // Limited if count strictly exceeds limit (matches previous behavior: > $limit)
        $limited = ($count > $limit);

        // Rewind & write back
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode(['hits' => $hits], JSON_UNESCAPED_SLASHES));
        fflush($fp);

        // Compute reset info
        $oldest = $hits ? $hits[0] : $now;
        $resetIn = max(0, ($oldest + $window) - $now);

        return [
            'key'       => $key,
            'limit'     => $limit,
            'window'    => $window,
            'count'     => $count,
            'remaining' => max(0, $limit - min($count, $limit)),
            'limited'   => $limited,
            'reset_in'  => $resetIn,
            'reset_at'  => $now + $resetIn,
            'file'      => $file,
        ];
    } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

/**
 * Check current status WITHOUT consuming a hit.
 * @return array same shape as rate_limit_consume()
 */
function rate_limit_status(string $key, int $limit, int $window): array
{
    $file = _rl_path($key);
    $now  = time();
    $count = 0;

    $hits = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($data) && isset($data['hits']) && is_array($data['hits'])) {
            $hits = $data['hits'];
        }
    }

    $cutoff = $now - $window;
    $hits = array_values(array_filter($hits, static function ($t) use ($cutoff) {
        return is_int($t) && $t >= $cutoff;
    }));
    $count = count($hits);

    $limited = ($count > $limit);
    $oldest  = $hits ? $hits[0] : $now;
    $resetIn = max(0, ($oldest + $window) - $now);

    return [
        'key'       => $key,
        'limit'     => $limit,
        'window'    => $window,
        'count'     => $count,
        'remaining' => max(0, $limit - min($count, $limit)),
        'limited'   => $limited,
        'reset_in'  => $resetIn,
        'reset_at'  => $now + $resetIn,
        'file'      => $file,
    ];
}

/** Clear the rolling window for a key. */
function rate_limit_reset(string $key): void
{
    $file = _rl_path($key);
    if (is_file($file)) {
        @unlink($file);
    }
}

/** Internal: map a key to a stable, safe file path. */
function _rl_path(string $key): string
{
    $hash = substr(sha1($key), 0, 16);
    $dir  = sys_get_temp_dir() ?: '/tmp';
    return rtrim($dir, '/')."/gov_rl_{$hash}.json";
}

/** Internal: fallback status builder. */
function _rl_status_out(string $key, int $limit, int $window, int $count, bool $limited, int $now, string $file): array
{
    return [
        'key'       => $key,
        'limit'     => $limit,
        'window'    => $window,
        'count'     => $count,
        'remaining' => max(0, $limit - min($count, $limit)),
        'limited'   => $limited,
        'reset_in'  => $window,
        'reset_at'  => $now + $window,
        'file'      => $file,
    ];
}
