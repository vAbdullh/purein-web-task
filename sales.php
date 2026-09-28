<?php
session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$user = $_SESSION['user'];

// Show the user's own station by default. Head office picks one from the list.
$station = $_GET['station'] ?? ($user['station_id'] ?? 1);

$sales = $db->query("SELECT * FROM sales WHERE station_id = $station ORDER BY sold_at DESC")->fetchAll();
$stations = $db->query('SELECT * FROM stations ORDER BY id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sales - Fuel Panel</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="top">Logged in as <?= $user['username'] ?> | <a href="logout.php">Log out</a></div>
  <h1>Sales</h1>

  <form method="get">
    <select name="station">
      <?php foreach ($stations as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $s['id'] == $station ? 'selected' : '' ?>><?= $s['name'] ?></option>
      <?php endforeach; ?>
    </select>
    <button>Show</button>
  </form>

  <table class="sales">
    <tr><th>Time</th><th>Pump</th><th>Fuel</th><th>Litres</th><th>Amount (SAR)</th></tr>
    <?php foreach ($sales as $row): ?>
      <tr>
        <td><?= $row['sold_at'] ?></td>
        <td><?= $row['pump'] ?></td>
        <td><?= $row['fuel'] ?></td>
        <td><?= $row['litres'] ?></td>
        <td><?= sprintf('%f', $row['amount']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
