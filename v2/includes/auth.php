<?php
/**
 * includes/auth.php — Admin authentication helpers
 */

require_once __DIR__ . '/../config.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Harden session cookie
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isAdminLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: /v2/admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function adminLogin(string $username, string $password): bool {
    if ($username !== ADMIN_USERNAME) {
        return false;
    }
    if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
        return false;
    }
    startSecureSession();
    session_regenerate_id(true);       // prevent session fixation
    $_SESSION['admin']    = true;
    $_SESSION['admin_at'] = time();
    return true;
}

function adminLogout(): void {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
}

// ─── CSRF ────────────────────────────────────────────────────────────────────

function csrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(string $token): bool {
    startSecureSession();
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrfToken()) . '">';
}

// ─── Flash messages ──────────────────────────────────────────────────────────

function flashSet(string $type, string $message): void {
    startSecureSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flashGet(): ?array {
    startSecureSession();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function flashHtml(): string {
    $flash = flashGet();
    if (!$flash) return '';
    $type = in_array($flash['type'], ['success', 'error', 'warning', 'info'], true)
        ? $flash['type'] : 'info';
    return '<div class="flash flash-' . $type . '">'
         . htmlspecialchars($flash['message'])
         . '</div>';
}
