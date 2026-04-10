<?php
require_once __DIR__ . '/etc/config.php';

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $parts = explode(':', DB_SERVERNAME, 2);
    $host = trim($parts[0] ?? '127.0.0.1');
    $port = isset($parts[1]) ? trim($parts[1]) : null;

    // PDO (mysql) uses the unix socket when host is "localhost". Force TCP by using 127.0.0.1.
    if ($host === 'localhost') {
        $host = '127.0.0.1';
    }

    $dsn = 'mysql:host=' . $host . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    if ($port !== null && $port !== '') {
        $dsn .= ';port=' . $port;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    return $pdo;
}

?>
