<?php
// Opens the SQLite database next to this file, and creates it on the first run.

$db = new PDO('sqlite:' . __DIR__ . '/app.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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
            station_id INTEGER,                    -- NULL = head office, sees every station
            is_admin   INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE sales (
            id         INTEGER PRIMARY KEY,
            station_id INTEGER NOT NULL,
            sold_at    TEXT NOT NULL,
            pump       INTEGER NOT NULL,
            fuel       TEXT NOT NULL,
            litres     REAL NOT NULL,
            amount     REAL NOT NULL
        );

        INSERT INTO stations (id, name) VALUES (1, 'Station A'), (2, 'Station B'), (3, 'Station C');

        INSERT INTO users (username, password, station_id, is_admin) VALUES
            ('admin',     'admin123',  NULL, 1),
            ('manager_a', 'manager_a', 1,    0),
            ('manager_b', 'pass1234',  2,    0);
    ");

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
