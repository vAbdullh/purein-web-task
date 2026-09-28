<?php
require __DIR__ . '/security.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('POST required');
}
checkCsrf();
audit('logout', ['user_id' => $_SESSION['user']['id'] ?? null]);
// Clear both the server session and browser cookie.
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600, 'path' => $params['path'], 'domain' => $params['domain'],
    'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict',
]);
session_destroy();
header('Location: index.php');
exit;
