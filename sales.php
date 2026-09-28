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
if (!$user) {
    unset($_SESSION['user']);
    showError(403, 'Account unavailable', 'Your account is no longer available. Please sign in again or contact your administrator.');
}
$admin = (int) $user['is_admin'] === 1;
$station = $admin ? filter_var($_GET['station'] ?? 1, FILTER_VALIDATE_INT) : $user['station_id'];
if (!$station || $station < 1) {
    showError($admin ? 400 : 403, $admin ? 'Invalid station' : 'Access denied',
        $admin ? 'Please return to sales and choose a valid station.' : 'No station is assigned to your account. Please contact your administrator.');
}
// Only admins may select another station.
if (!$admin && isset($_GET['station']) && (!is_string($_GET['station']) || $_GET['station'] !== (string) $station)) {
    audit('station_denied', ['user_id' => $user['id']]);
    showError(404, 'Wrong station', 'Please return to sales to view your assigned station.');
}
// Reject missing stations instead of showing an empty, misleading page.
$exists = $db->prepare('SELECT id FROM stations WHERE id = ?');
$exists->execute([$station]);
if (!$exists->fetchColumn()) {
    showError(404, 'Station not found', 'This station is unavailable. Please return to sales and choose another station.');
}
$query = $db->prepare('SELECT * FROM sales WHERE station_id = ? ORDER BY sold_at DESC');
$query->execute([$station]);
$sales = $query->fetchAll();
$query = $db->prepare('SELECT * FROM stations WHERE ? = 1 OR id = ? ORDER BY id');
// Bind the admin flag as an integer so SQLite matches it to 1.
$query->bindValue(1, $admin ? 1 : 0, PDO::PARAM_INT);
$query->bindValue(2, (int) $station, PDO::PARAM_INT);
$query->execute();
$stations = $query->fetchAll();
audit('station_view', ['user_id' => $user['id'], 'station_id' => $station]);
?>
<!DOCTYPE html>
<html lang="<?= language() ?>" dir="<?= direction() ?>">
<head>
  <meta charset="utf-8">
  <title><?= escape(t('Sales')) ?> - <?= escape(t('Fuel Panel')) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php languageSwitch(); ?>
  <div class="top"><?= escape(t('Logged in as')) ?> <bdi><?= escape($user['username']) ?></bdi> | <form method="post" action="logout.php">
    <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
    <button><?= escape(t('Log out')) ?></button></form></div>
  <h1><?= escape(t('Sales')) ?></h1>

  <form method="get">
    <select name="station" aria-label="<?= escape(t('Station')) ?>">
      <?php foreach ($stations as $s): ?>
        <option value="<?= escape($s['id']) ?>" <?= $s['id'] == $station ? 'selected' : '' ?>><?= escape(t($s['name'])) ?></option>
      <?php endforeach; ?>
    </select>
    <button><?= escape(t('Show')) ?></button>
  </form>

  <table class="sales">
    <tr><th><?= escape(t('Time')) ?></th><th><?= escape(t('Pump')) ?></th><th><?= escape(t('Fuel')) ?></th><th><?= escape(t('Litres')) ?></th><th><?= escape(t('Amount (SAR)')) ?></th></tr>
    <?php foreach ($sales as $row): ?>
      <tr>
        <td><bdi><?= escape(localizedDate($row['sold_at'])) ?></bdi></td>
        <td><bdi><?= escape(localizedNumber($row['pump'])) ?></bdi></td>
        <td><bdi><?= escape(t($row['fuel'])) ?></bdi></td>
        <td><bdi><?= escape(localizedNumber($row['litres'], 2)) ?></bdi></td>
        <td><bdi><?= escape(localizedNumber($row['amount'], 2, true)) ?></bdi></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
