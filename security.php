<?php
// Apply the same session and CSRF protection on every page.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
]);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function escape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function checkCsrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Invalid request token');
    }
}
function audit(string $event, array $details = []): void {
    // JSON keeps untrusted values from forging log lines.
    error_log(json_encode(['time' => gmdate('c'), 'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'details' => $details]));
}
