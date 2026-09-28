<?php
require __DIR__ . '/security.php';
require __DIR__ . '/db.php';

$error = '';
$username = '';
$retryAfter = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    // Persist limits by account and address, independently of session cookies.
    $keys = [hash('sha256', 'user:' . $username), hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''))];
    $db->exec('BEGIN IMMEDIATE');
    $db->prepare('DELETE FROM login_attempts WHERE started <= ?')->execute([time() - 900]);
    $attempt = $db->prepare('SELECT attempts, started FROM login_attempts WHERE key = ?');
    foreach ($keys as $key) {
        $attempt->execute([$key]);
        $limit = $attempt->fetch();
        if ($limit && (int) $limit['attempts'] >= 5) {
            $retryAfter = max($retryAfter, (int) $limit['started'] + 900 - time(), 1);
        }
    }
    if ($retryAfter > 0) {
        $db->exec('COMMIT');
        audit('login_limited');
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        $error = 'Too many login attempts. Please try again after 15 minutes.';
    } else {
    $count = $db->prepare('INSERT INTO login_attempts VALUES (?, 1, ?) ON CONFLICT(key) DO UPDATE SET attempts = attempts + 1');
    foreach ($keys as $key) { $count->execute([$key, time()]); }
    $db->exec('COMMIT');
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        // Keep credentials out of the session.
        $_SESSION = ['user' => array_intersect_key($user, array_flip(['id', 'username', 'station_id', 'is_admin'])),
            'csrf' => bin2hex(random_bytes(32))];
        audit('login_success', ['user_id' => $user['id']]);
        header('Location: sales.php');
        exit;
    }
    audit('login_failure', ['username' => $username]);
    $error = 'Wrong username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Fuel Panel</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="box">
    <h1>Fuel Panel</h1>
    <?php if ($error): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
      <p><label>Username<br><input name="username" autocomplete="username" value="<?= escape($username) ?>"></label></p>
      <p><label>Password<br><input name="password" type="password" autocomplete="current-password"></label></p>
      <p><button>Log in</button></p>
    </form>
    <?php if ($retryAfter > 0): ?><p><a class="button" href="index.php">Back to login</a></p><?php endif; ?>
  </div>
</body>
</html>
