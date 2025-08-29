<?php
// db.php: XAMPP Compatible Database Connection
$host = '127.0.0.1';
$db   = 'ict_inventory';
$user = 'root';              // XAMPP default user
$pass = '';                  // XAMPP default password (empty)
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Connection successful
} catch (PDOException $e) {
    echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;">';
    echo '<h3>❌ Database Connection Failed</h3>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>Solution:</strong> <a href="setup_database.php">Run Database Setup</a></p>';
    echo '</div>';
    exit;
}
?>