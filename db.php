<?php
// SQLite files are blocked by Apache; enable referential integrity on each connection.

$db = new PDO('sqlite:' . __DIR__ . '/app.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('PRAGMA busy_timeout = 5000');

function seedPassword(string $username): string {
    $password = getenv(strtoupper($username) . '_PASSWORD');
    if (!$password || strlen($password) < 12) {
        throw new RuntimeException('Set ' . strtoupper($username) . '_PASSWORD to at least 12 characters.');
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

// Serialize setup and migration so concurrent requests cannot partly initialize data.
$db->exec('BEGIN IMMEDIATE');
try {

if (!$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sales'")->fetch()) {
    $db->exec("
        CREATE TABLE stations (
            id   INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        );
        CREATE TABLE users (
            id         INTEGER PRIMARY KEY,
            username   TEXT NOT NULL UNIQUE,
            password   TEXT NOT NULL,
            station_id INTEGER REFERENCES stations(id),
            is_admin   INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE sales (
            id         INTEGER PRIMARY KEY,
            station_id INTEGER NOT NULL REFERENCES stations(id),
            sold_at    TEXT NOT NULL,
            pump       INTEGER NOT NULL,
            fuel       TEXT NOT NULL,
            litres     REAL NOT NULL,
            amount     REAL NOT NULL
        );

        INSERT INTO stations (id, name) VALUES (1, 'Station A'), (2, 'Station B'), (3, 'Station C');

    ");
    $seed = $db->prepare('INSERT INTO users (username, password, station_id, is_admin) VALUES (?, ?, ?, ?)');
    foreach ([['admin', null, 1], ['manager_a', 1, 0], ['manager_b', 2, 0]] as [$name, $station, $admin]) {
        $seed->execute([$name, seedPassword($name), $station, $admin]);
    }

    mt_srand(7);
    $fuels = [['Gasoline 91', 2.18], ['Gasoline 95', 2.33], ['Diesel', 1.66]];
    $insert = $db->prepare('INSERT INTO sales (station_id, sold_at, pump, fuel, litres, amount) VALUES (?, ?, ?, ?, ?, ?)');
    for ($i = 0; $i < 60; $i++) {
        [$fuel, $price] = $fuels[mt_rand(0, 2)];
        $litres = mt_rand(500, 6000) / 100;
        $insert->execute([
            $i % 3 + 1,
            gmdate('Y-m-d H:i', strtotime('2026-09-14 06:00 UTC') + mt_rand(0, 3 * 86400)),
            mt_rand(1, 4),
            $fuel,
            $litres,
            round($litres * $price, 2),
        ]);
    }
}

// Upgrade existing databases without discarding their users or sales.
foreach (['users', 'sales'] as $table) {
    if (!$db->query("PRAGMA foreign_key_list($table)")->fetch()) {
        $sql = $db->query("SELECT sql FROM sqlite_master WHERE name = '$table'")->fetchColumn();
        $sql = preg_replace('/CREATE TABLE\s+' . $table . '/i', 'CREATE TABLE ' . $table . '_new', $sql, 1);
        $sql = preg_replace('/station_id\s+INTEGER(\s+NOT NULL)?/i', '$0 REFERENCES stations(id)', $sql, 1);
        $db->exec($sql);
        $db->exec("INSERT INTO {$table}_new SELECT * FROM $table");
        $db->exec("DROP TABLE $table");
        $db->exec("ALTER TABLE {$table}_new RENAME TO $table");
    }
}
$update = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
foreach ($db->query('SELECT id, username, password FROM users')->fetchAll() as $account) {
    if (password_get_info($account['password'])['algo'] === null) {
        // Replace documented defaults; hash any other existing password.
        $known = ['admin' => 'admin123', 'manager_a' => 'manager_a', 'manager_b' => 'pass1234'];
        $hash = ($known[$account['username']] ?? null) === $account['password']
            ? seedPassword($account['username']) : password_hash($account['password'], PASSWORD_DEFAULT);
        $update->execute([$hash, $account['id']]);
    }
}
$db->exec('CREATE TABLE IF NOT EXISTS login_attempts (key TEXT PRIMARY KEY, attempts INTEGER NOT NULL, started INTEGER NOT NULL)');
$db->exec('COMMIT');
} catch (Throwable $error) {
    $db->exec('ROLLBACK');
    throw $error;
}
