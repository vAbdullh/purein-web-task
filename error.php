<?php
require __DIR__ . '/security.php';
// Apache supplies the original status for blocked or missing resources.
$status = (int) ($_SERVER['REDIRECT_STATUS'] ?? 404);
// Conceal forbidden resources behind the same response as missing pages.
if ($status === 403) { $status = 404; }
$messages = [
    400 => ['Invalid request', 'We could not understand this request. Please return and try again.'],
    404 => ['Page not found', 'This page could not be found. Please use the button below to continue.'],
    500 => ['Something went wrong', 'We could not complete your request. Please try again shortly.'],
];
if (!isset($messages[$status])) { $status = 500; }
showError($status, ...$messages[$status]);
