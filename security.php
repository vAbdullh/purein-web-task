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
    // Record the local date, time, and timezone for each event.
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh'));
    $entry = json_encode(['time' => $now->format(DateTimeInterface::ATOM),
        'timezone' => $now->getTimezone()->getName(), 'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'details' => $details], JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
    $directory = __DIR__ . '/logs';
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        error_log('Audit directory unavailable: ' . $entry);
        return;
    }
    // Use the actual event time in a filename-safe format.
    $timezone = str_replace('/', '-', $now->getTimezone()->getName());
    $filename = $directory . '/audit-' . $now->format('Y-m-d_H-i-s') . '-' . $timezone . '.log';
    if (@file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX) === false) {
        error_log('Audit file unavailable: ' . $entry);
    }
}
