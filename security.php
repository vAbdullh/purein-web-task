<?php
// Keep technical failures in server logs, never in the browser.
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);
    showError(500, 'Something went wrong', 'We could not complete your request. Please try again shortly.');
});
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
        showError(403, 'Please try again', 'This form has expired or could not be verified. Return to the page and submit it again.');
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

// Use a consistent error page with safe, fixed navigation links.
function showError(int $status, string $title, string $message): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    $signedIn = !empty($_SESSION['user']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= escape($title) ?> - Fuel Panel</title>
      <link rel="stylesheet" href="/style.css">
    </head>
    <body class="error-page">
      <main class="error-card">
        <p class="error-code">Fuel Panel &middot; <?= $status ?></p>
        <h1><?= escape($title) ?></h1>
        <p><?= escape($message) ?></p>
        <div class="error-actions">
          <a class="button" href="<?= $signedIn ? '/sales.php' : '/index.php' ?>"><?= $signedIn ? 'Back to sales' : 'Back to login' ?></a>
          <?php if ($signedIn): ?><a href="/index.php">Go to login</a><?php endif; ?>
        </div>
      </main>
    </body>
    </html>
    <?php
    exit;
}
