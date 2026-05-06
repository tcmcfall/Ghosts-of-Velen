<?php
/* Minimal bootstrap:
 * - Loads /homepages/15/d91361623/htdocs/.env
 * - Copies keys into $_SERVER and $_ENV
 * - Hardens session cookies
 */

if (function_exists('ini_set')) {
  @ini_set('display_errors', '0');
  @ini_set('session.use_strict_mode', '1');
  @ini_set('session.cookie_httponly', '1');
  @ini_set('session.cookie_secure', '1');
  @ini_set('session.cookie_samesite', 'Lax');
}

$envPath = dirname(__DIR__, 2) . '/.env';
load_env_fallback($envPath);

/* Merge env into superglobals */
if (isset($_ENV) && is_array($_ENV)) {
  foreach ($_ENV as $k => $v) {
    if (!isset($_SERVER[$k])) { $_SERVER[$k] = $v; }
  }
}

/* Start session with hardened params */
if (session_status() !== PHP_SESSION_ACTIVE) {
  $p = session_get_cookie_params();
  $p['httponly'] = true; $p['secure'] = true; $p['samesite'] = 'Lax';
  if (function_exists('session_set_cookie_params')) { session_set_cookie_params($p); }
  session_name('govsid');
  @session_start();
}

/* APP_ENV=dev -> show errors */
if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'dev') {
  if (function_exists('ini_set')) { @ini_set('display_errors', '1'); }
  error_reporting(E_ALL);
}

/* -------- helper -------- */
function load_env_fallback($envPath) {
  if (!is_file($envPath)) { return; }
  $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!is_array($lines)) { return; }

  foreach ($lines as $line) {
    $trim = ltrim($line);
    if ($trim === '' || $trim[0] === '#') { continue; }
    $pos = strpos($line, '=');
    if ($pos === false) { continue; }

    $key = rtrim(substr($line, 0, $pos));
    $val = ltrim(substr($line, $pos + 1));

    // Strip surrounding quotes if present
    if ($val !== '' && ($val[0] === '"' || $val[0] === "'") && substr($val, -1) === $val[0]) {
      $val = substr($val, 1, -1);
    } else {
      // Unquoted values: allow inline comments after whitespace.
      $val = preg_replace('/\s+[;#].*$/', '', $val) ?? $val;
      $val = rtrim($val);
    }

    if (!isset($_ENV[$key]))    { $_ENV[$key]    = $val; }
    if (!isset($_SERVER[$key])) { $_SERVER[$key] = $val; }
    putenv($key . '=' . $val);
  }
}
