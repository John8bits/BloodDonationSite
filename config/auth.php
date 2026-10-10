<?php

/*
|--------------------------------------------------------------------------
| Auth / role helpers
|--------------------------------------------------------------------------
| Small shared helpers so every protected page checks login state and
| role the same way instead of repeating the same `if` block everywhere.
| Include this AFTER session_start().
*/

function current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function current_role(): string
{
    return $_SESSION['role'] ?? 'user';
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

/**
 * Path helper so both /index.php (root) and /admin|/dashboard (one
 * level down) can build a correct relative link to "my home page".
 */
function role_home_path(string $fromRoot = ''): string
{
    $target = is_admin() ? 'admin/dashboard.php' : 'dashboard/dashboard.php';
    return $fromRoot . $target;
}

/**
 * Call at the top of any page that requires a logged-in user.
 * $loginPath is relative to the page calling this function.
 */
function require_login(string $loginPath = 'login.php'): void
{
    if (!is_logged_in()) {
        header("Location: " . $loginPath);
        exit;
    }
}

/**
 * Call at the top of any page that is admin-only. Logged-out users are
 * sent to login; logged-in non-admins are sent back to their dashboard
 * instead of an error page, since this is a routing decision, not a
 * security boundary they're trying to break.
 */
function require_admin(string $loginPath = '../login.php', string $userHomePath = '../dashboard/dashboard.php'): void
{
    if (!is_logged_in()) {
        header("Location: " . $loginPath);
        exit;
    }

    if (!is_admin()) {
        header("Location: " . $userHomePath);
        exit;
    }
}
