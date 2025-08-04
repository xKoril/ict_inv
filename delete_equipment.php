<?php
require 'db.php';

// 1. Get equipment ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    die('❌ No equipment selected.');
}

// 2. Delete the record
$del = $pdo->prepare('DELETE FROM equipment WHERE equipment_id = ?');
$del->execute([$id]);

// 3. Redirect back to list
header('Location: index.php');
exit;