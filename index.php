<?php
session_start();
require __DIR__ . '/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$_POST['username'] ?? '']);
    $user = $stmt->fetch();

    if ($user && $user['password'] === ($_POST['password'] ?? '')) {
        $_SESSION['user'] = $user;
        header('Location: sales.php');
        exit;
    }
    $error = 'Wrong username or password';
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
    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <form method="post">
      <p><label>Username<br><input name="username"></label></p>
      <p><label>Password<br><input name="password" type="password"></label></p>
      <p><button>Log in</button></p>
    </form>
  </div>
</body>
</html>
