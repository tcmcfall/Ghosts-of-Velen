<?php
declare(strict_types=1);

/**
 * Simple access-control helpers.
 * Intended to be used AFTER auth/bootstrap.php and auth_check.php (so session is started).
 *
 * Provides:
 * - isLoggedIn(), isAdmin(), isDm(?int $campaignId = null)
 * - requireLogin(), requireAdmin(), requireDm(?int $campaignId = null)
 * - requireCharacter(string $redirect = '/auth/select_campaign.php')
 *
 * These functions avoid hard failures if db() is unavailable by gracefully
 * falling back to session hints (e.g., $_SESSION['is_dm']) where possible.
 */

/** Detect whether the current request expects JSON (for nicer 403s on API calls). */
function _acl_wants_json(): bool {
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (strpos($accept, 'application/json') !== false) return true;
    $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return $xhr === 'xmlhttprequest';
}

/** Emit a 403 (or other) in JSON for APIs, or plain text for pages. Then exit. */
function _acl_abort(int $status, string $message = 'Forbidden', string $code = 'FORBIDDEN'): void {
    http_response_code($status);
    if (_acl_wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
    } else {
        // Keep it simple to avoid header issues in already-started pages
        echo $message;
    }
    exit;
}

/** True if a user session exists. */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

/** True if the current user is an admin. */
function isAdmin(): bool {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

/**
 * True if the current user is a DM.
 * - If $campaignId is provided, tests DM for THAT campaign.
 * - Otherwise, tests DM of ANY campaign (or falls back to $_SESSION['is_dm'] if present).
 */
function isDm(?int $campaignId = null): bool {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true; // admins pass all gates

    // Fast path: allow a session hint if present and no specific campaign is requested
    if ($campaignId === null && !empty($_SESSION['is_dm'])) {
        return true;
    }

    // If db() is available, verify against the campaigns table
    if (function_exists('db')) {
        try {
            $pdo = db();
            if ($campaignId !== null) {
                $stmt = $pdo->prepare('SELECT 1 FROM campaigns WHERE id = :cid AND dm_user_id = :uid LIMIT 1');
                $stmt->execute([':cid' => $campaignId, ':uid' => (int)$_SESSION['user_id']]);
            } else {
                $stmt = $pdo->prepare('SELECT 1 FROM campaigns WHERE dm_user_id = :uid LIMIT 1');
                $stmt->execute([':uid' => (int)$_SESSION['user_id']]);
            }
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            // On DB failure, fall back to session hint if any
            return !empty($_SESSION['is_dm']);
        }
    }

    // Last resort: session hint
    return !empty($_SESSION['is_dm']);
}

/** Require an authenticated user session. */
function requireLogin(): void {
    if (!isLoggedIn()) {
        _acl_abort(403, 'Login required', 'LOGIN_REQUIRED');
    }
}

/** Require admin role. */
function requireAdmin(): void {
    if (!isAdmin()) {
        _acl_abort(403, 'Admins only.', 'ADMINS_ONLY');
    }
}

/**
 * Require DM privileges.
 * If $campaignId is provided, requires DM of that campaign.
 * Otherwise, requires DM of any campaign (or admin).
 */
function requireDm(?int $campaignId = null): void {
    if (!isDm($campaignId)) {
        _acl_abort(403, 'DMs only.', 'DMS_ONLY');
    }
}

/**
 * Require that a character is selected in session; otherwise redirect
 * to the campaign selector (defaults to /auth/select_campaign.php).
 */
function requireCharacter(string $redirect = '/auth/select_campaign.php'): void {
    if (empty($_SESSION['character_id'])) {
        header('Location: ' . $redirect);
        exit;
    }
}
