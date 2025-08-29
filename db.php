<?php
// db.php: establish a PDO connection with password
$host = '127.0.0.1';
$db   = 'ict_inventory';
$user = 'ict_inv_user';
$pass = '!R%z%5vPm2ZWe7';
$charset = 'utf8';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Connection successful - no need to echo anything
} catch (PDOException $e) {
    echo '❌ Connection failed: ' . $e->getMessage();
    exit;
}
?>