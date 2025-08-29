<?php
// === logout.php - Logout Handler ===
require_once 'auth.php';

// Destroy the session and redirect
session_destroy();
header('Location: login.php?logout=success');
exit;
?>