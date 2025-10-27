<?php
// Direct DB connection test for Render debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
echo '<h2>DB Connection Test</h2>';
try {
    if (getenv('DATABASE_URL')) {
        $db = parse_url(getenv('DATABASE_URL'));
        $dsn = 'pgsql:host=' . $db['host'] . ';port=' . (isset($db['port']) ? $db['port'] : 5432) . ';dbname=' . ltrim($db['path'], '/');
        echo '<b>Using PostgreSQL:</b><br>';
        echo 'DSN: ' . htmlspecialchars($dsn) . '<br>';
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo '<span style="color:green;">SUCCESS: Connected to PostgreSQL!</span>';
    } else {
        echo '<b>Using MySQL (local/dev):</b><br>';
        $pdo = new PDO('mysql:host=localhost;dbname=blood_system', 'root', 'password112', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo '<span style="color:green;">SUCCESS: Connected to MySQL!</span>';
    }
} catch (Throwable $e) {
    echo '<span style="color:red;">ERROR: ' . htmlspecialchars($e->getMessage()) . '</span>';
}
