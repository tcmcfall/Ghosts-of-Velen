<?php
declare(strict_types=1);

/**
 * CSRF utilities.
 * - Generates a per-session token
 * - Easy helpers for forms, meta tags, and validation
 * - Works with form posts (hidden input) and AJAX (X-CSRF-Token header)
 *
 * NOTE: We intentionally do NOT read php://input here to avoid
 * consuming JSON bodies before your endpoint reads them.
 */

// Centralize secure session + env
require_once __DIR__ . '/../auth/bootstrap.php';

/** Header name we accept for AJAX CSRF. */
function csrf_header_name(): string {
    return 'X-CSRF-Token';
}

/**
 * Return (and create if missing) the per-session CSRF token.
 * 32 bytes => 64 hex chars.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden input for HTML forms.
 * Usage: echo csrf_input();
 */
function csrf_input(): string {
    $tok = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="'.$tok.'">';
}

/**
 * Meta tag for pages that bootstrap JS which will copy the token into headers.
 * Usage: echo csrf_meta();
 */
function csrf_meta(): string {
    $tok = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<meta name="csrf-token" content="'.$tok.'">';
}

/**
 * Optional helper to expose the token via a readable cookie for single-page apps.
 * Clients should STILL send it back via the 'X-CSRF-Token' header (double-submit pattern).
 * Usage: csrf_cookie(); (call once on HTML page renders)
 */
function csrf_cookie(): void {
    $tok = csrf_token();
    @setcookie('XSRF-TOKEN', $tok, [
        'expires'  => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,      // must be readable by JS to copy into header
        'samesite' => 'Lax',
    ]);
}

/**
 * Fetch the CSRF token presented by the client:
 * - Prefer POST/GET 'csrf_token' for standard HTML forms
 * - Otherwise check the 'X-CSRF-Token' header for AJAX
 *
 * We do NOT read JSON bodies here to avoid consuming php://input.
 */
function csrf_client_token(): string {
    // Form field
    if (isset($_POST['csrf_token'])) {
        return is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    }
    if (isset($_GET['csrf_token'])) {
        return is_string($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    }

    // AJAX header(s)
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        // Fallback for environments without getallheaders()
        foreach ($_SERVER as $name => $value) {
            if (strncmp($name, 'HTTP_', 5) === 0) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
    }

    // Normalize lookups for common variations
    $candidates = [
        csrf_header_name(),        // X-CSRF-Token
        'X-Csrf-Token',
        'x-csrf-token',
        'X-XSRF-Token',            // some frameworks use this
        'X-Requested-Csrf',
    ];
    foreach ($candidates as $h) {
        if (isset($headers[$h]) && is_string($headers[$h]) && $headers[$h] !== '') {
            return $headers[$h];
        }
    }

    return '';
}

/**
 * Compare a provided token with the session token (timing-safe).
 */
function csrf_validate(string $providedToken): bool {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '' || !is_string($providedToken) || $providedToken === '') {
        return false;
    }
    return hash_equals($sessionToken, $providedToken);
}

/**
 * Validate the CSRF token for the current request.
 * Returns true on success, false on failure.
 */
function csrf_validate_request(): bool {
    return csrf_validate(csrf_client_token());
}

/**
 * Enforce CSRF protection and terminate if invalid.
 * Optionally restrict by HTTP methods (e.g., ['POST']).
 */
function csrf_enforce(?array $allowedMethods = null): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($allowedMethods !== null && !in_array($method, $allowedMethods, true)) {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    if (!csrf_validate_request()) {
        http_response_code(400);
        exit('Bad Request: CSRF validation failed.');
    }
}
