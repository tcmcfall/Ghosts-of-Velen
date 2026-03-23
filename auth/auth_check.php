<?php
declare(strict_types=1);

// Centralize session + env setup (secure cookie flags, session_start, Dotenv)
require_once __DIR__ . '/bootstrap.php';

// 1) Logged in at all?
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: /auth/login.php');
    exit;
}

// 2) Recognized global roles (Option B)
$allowedRoles = ['user','admin'];
if (!in_array($_SESSION['role'], $allowedRoles, true)) {
    http_response_code(403);
    exit('Forbidden');
}

// 3) Admins bypass campaign scoping
if ($_SESSION['role'] === 'admin') {
    return;
}

// 4) Non-admins must be scoped to a campaign
if (!isset($_SESSION['campaign_id'])) {
    header('Location: /auth/select_campaign.php');
    exit;
}

// 5) `is_dm` must be set server-side during campaign selection
if (!array_key_exists('is_dm', $_SESSION)) {
    header('Location: /auth/select_campaign.php');
    exit;
}

// Optional: Page-level flags can be enforced by including /includes/acl.php in the target page.
