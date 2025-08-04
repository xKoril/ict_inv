<?php
// db.php: establish a PDO connection with password
$host = '127.0.0.1';
$db   = 'ict_inventory';
$user = 'root';
$pass = 'Password@123!';  // your MySQL root password
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo '✅ Connected successfully to ict_inventory database.';
} catch (PDOException $e) {
    echo '❌ Connection failed: ' . $e->getMessage();
    exit;
}
?>