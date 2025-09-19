<?php
// Path to your SQLite DB file (created earlier from your schema)
define('DB_PATH', __DIR__ . '/bank.db'); // change if your DB name/path differs

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}
