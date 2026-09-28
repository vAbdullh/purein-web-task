<?php
require __DIR__ . '/security.php';
require __DIR__ . '/db.php';

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
// Read current roles so revoked permissions take effect immediately.
$account = $db->prepare('SELECT id, username, station_id, is_admin FROM users WHERE id = ?');
$account->execute([$_SESSION['user']['id']]);
$user = $account->fetch();
if (!$user) { http_response_code(403); exit('Access denied'); }
$admin = (int) $user['is_admin'] === 1;
$station = $admin ? filter_var($_GET['station'] ?? 1, FILTER_VALIDATE_INT) : $user['station_id'];
if (!$station || $station < 1) { http_response_code(403); exit('Access denied'); }
// Only admins may select another station.
if (!$admin && isset($_GET['station']) && (string) $_GET['station'] !== (string) $station) {
    audit('station_denied', ['user_id' => $user['id']]);
    http_response_code(403); exit('Access denied');
}
$query = $db->prepare('SELECT * FROM sales WHERE station_id = ? ORDER BY sold_at DESC');
$query->execute([$station]);
$sales = $query->fetchAll();
$query = $db->prepare('SELECT * FROM stations WHERE ? = 1 OR id = ? ORDER BY id');
$query->execute([$admin ? 1 : 0, $station]);
$stations = $query->fetchAll();
audit('station_view', ['user_id' => $user['id'], 'station_id' => $station]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sales - Fuel Panel</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="top">Logged in as <?= escape($user['username']) ?> | <form method="post" action="logout.php">
    <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
    <button>Log out</button></form></div>
  <h1>Sales</h1>

  <form method="get">
    <select name="station">
      <?php foreach ($stations as $s): ?>
        <option value="<?= escape($s['id']) ?>" <?= $s['id'] == $station ? 'selected' : '' ?>><?= escape($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button>Show</button>
  </form>

  <table class="sales">
    <tr><th>Time</th><th>Pump</th><th>Fuel</th><th>Litres</th><th>Amount (SAR)</th></tr>
    <?php foreach ($sales as $row): ?>
      <tr>
        <td><?= escape($row['sold_at']) ?></td>
        <td><?= escape($row['pump']) ?></td>
        <td><?= escape($row['fuel']) ?></td>
        <td><?= escape($row['litres']) ?></td>
        <td><?= escape(sprintf('%f', $row['amount'])) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
