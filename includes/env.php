<?php
declare(strict_types=1);

/**
 * includes/env.php
 *
 * Loads .env from ONE DIR ABOVE the web root (ghostsofvelen/..)/.env
 * Prefer $_ENV / getenv when already loaded (e.g., by bootstrap/Dotenv).
 * Lightweight parser handles comments, quotes, and caches values.
 *
 * Usage: env('KEY')  // returns string|null
 */

if (!function_exists('env')) {
    function env(string $key) {
        // 1) If bootstrap/Dotenv already populated, prefer that
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        $get = getenv($key);
        if ($get !== false) {
            return $get;
        }

        // 2) Lazy-load from .env (cached)
        static $vars = null;
        if ($vars === null) {
            $vars = _env_load_from_file();
            // Merge into $_ENV without clobbering
            foreach ($vars as $k => $v) {
                if (!array_key_exists($k, $_ENV)) {
                    $_ENV[$k] = $v;
                }
            }
        }

        // 3) Exact match
        if (array_key_exists($key, $vars)) {
            return $vars[$key];
        }

        // 4) Friendly aliases (both directions)
        static $aliases = [
            'SMTP_USERNAME' => 'SMTP_USER',
            'SMTP_PASSWORD' => 'SMTP_PASS',
            'DB_USERNAME'   => 'DB_USER',
            'DB_PASSWORD'   => 'DB_PASS',
        ];
        if (isset($aliases[$key]) && array_key_exists($aliases[$key], $vars)) {
            return $vars[$aliases[$key]];
        }
        // reverse lookup
        $rev = array_search($key, $aliases, true);
        if ($rev !== false && array_key_exists($rev, $vars)) {
            return $vars[$rev];
        }

        return null;
    }
}

/**
 * Internal: parse the .env file located one dir above web root.
 */
function _env_load_from_file(): array {
    $path = dirname(__DIR__, 2) . '/.env'; // includes/ -> up 2 -> parent of ghostsofvelen/
    if (!is_readable($path)) {
        return [];
    }

    $out = [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return $out;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue; // full-line comment/blank
        }

        // KEY=VALUE (first '=' only)
        $eq = strpos($line, '=');
        if ($eq === false) continue;

        $k = trim(substr($line, 0, $eq));
        $v = trim(substr($line, $eq + 1));

        if ($k === '') continue;

        // If quoted, strip surrounding quotes and unescape basic sequences.
        if (strlen($v) >= 2 && (
            ($v[0] === '"'  && substr($v, -1) === '"') ||
            ($v[0] === "'" && substr($v, -1) === "'")
        )) {
            $quote = $v[0];
            $v = substr($v, 1, -1);
            if ($quote === '"') {
                // Unescape common sequences in double quotes
                $v = str_replace(
                    ['\\"', '\\n', '\\r', '\\t', '\\\\'],
                    ['"',   "\n", "\r", "\t", '\\'],
                    $v
                );
            } // single quotes: value taken literally
        } else {
            // Unquoted: strip inline comments starting with space-# or tab-#
            // (Keeps URLs with '#' if no preceding whitespace)
            $v = preg_replace('/\s+#.*$/', '', $v) ?? $v;
            $v = trim($v);
        }

        // Normalize Windows-style CRLF leftovers
        $v = str_replace("\r", '', $v);

        $out[$k] = $v;
    }

    return $out;
}
