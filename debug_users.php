<?php
// debug_users.php - Check what users exist and test passwords
require_once 'db.php';

echo "<h2>🔍 Database Users Debug</h2>";

// Check if users table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ <strong>Users table doesn't exist!</strong>";
        echo "<p>Solution: <a href='setup_database.php'>Run Database Setup</a></p>";
        echo "</div>";
        exit;
    } else {
        echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "✅ Users table exists";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "❌ Database error: " . $e->getMessage();
    echo "</div>";
    exit;
}

// Show all users
try {
    $users = $pdo->query("SELECT user_id, username, full_name, user_role, is_active, created_at, password_hash FROM users")->fetchAll();
    
    if (empty($users)) {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ <strong>No users found in database!</strong>";
        echo "<p><a href='#create-admin'>Create admin user below</a></p>";
        echo "</div>";
    } else {
        echo "<div style='background: #e7f3ff; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>📋 Users in Database (" . count($users) . " found):</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; background: white;'>";
        echo "<tr style='background: #007bff; color: white;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>Username</th>";
        echo "<th style='padding: 8px;'>Full Name</th>";
        echo "<th style='padding: 8px;'>Role</th>";
        echo "<th style='padding: 8px;'>Active</th>";
        echo "<th style='padding: 8px;'>Created</th>";
        echo "<th style='padding: 8px;'>Password Hash (First 20 chars)</th>";
        echo "</tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>{$user['user_id']}</td>";
            echo "<td style='padding: 8px; font-weight: bold;'>{$user['username']}</td>";
            echo "<td style='padding: 8px;'>{$user['full_name']}</td>";
            echo "<td style='padding: 8px;'>{$user['user_role']}</td>";
            echo "<td style='padding: 8px;'>" . ($user['is_active'] ? '✅' : '❌') . "</td>";
            echo "<td style='padding: 8px;'>{$user['created_at']}</td>";
            echo "<td style='padding: 8px; font-family: monospace; font-size: 0.8em;'>" . substr($user['password_hash'], 0, 20) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "❌ Error fetching users: " . $e->getMessage();
    echo "</div>";
}

// Test password verification
if (!empty($users)) {
    echo "<h3>🔐 Password Test</h3>";
    $testPassword = 'admin123';
    
    foreach ($users as $user) {
        $isValid = password_verify($testPassword, $user['password_hash']);
        $color = $isValid ? '#d4edda' : '#f8d7da';
        $textColor = $isValid ? '#155724' : '#721c24';
        $icon = $isValid ? '✅' : '❌';
        
        echo "<div style='background: $color; color: $textColor; padding: 10px; border-radius: 5px; margin: 5px 0;'>";
        echo "$icon <strong>{$user['username']}</strong> with password '$testPassword': " . ($isValid ? 'VALID' : 'INVALID');
        echo "</div>";
    }
}

echo "<hr style='margin: 30px 0;'>";

// Quick fix section
echo "<div id='create-admin' style='background: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #dee2e6;'>";
echo "<h3>🔧 Quick Fix: Create/Reset Admin User</h3>";

if ($_POST['action'] ?? '' === 'create_admin') {
    $username = 'admin';
    $password = 'admin123';
    $full_name = 'System Administrator';
    $role = 'Admin';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        // Delete existing admin user if exists
        $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$username]);
        
        // Create new admin user
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, user_role, is_active) VALUES (?, ?, ?, ?, 1)");
        $result = $stmt->execute([$username, $password_hash, $full_name, $role]);
        
        if ($result) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "✅ <strong>Admin user created successfully!</strong><br>";
            echo "Username: <strong>admin</strong><br>";
            echo "Password: <strong>admin123</strong><br>";
            echo "<a href='login.php' style='color: #0056b3; text-decoration: none; font-weight: bold;'>→ Try logging in now</a>";
            echo "</div>";
            
            // Refresh page to show updated user list
            echo "<script>setTimeout(() => window.location.reload(), 2000);</script>";
        }
    } catch (PDOException $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "❌ Error creating admin user: " . $e->getMessage();
        echo "</div>";
    }
} else {
    echo "<form method='POST' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='action' value='create_admin'>";
    echo "<button type='submit' style='background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;'>";
    echo "🔧 Create/Reset Admin User (admin/admin123)";
    echo "</button>";
    echo "</form>";
    echo "<p style='color: #666; font-size: 0.9em;'>This will create or reset the admin user with username 'admin' and password 'admin123'</p>";
}
echo "</div>";

echo "<div style='background: #e7f3ff; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>🚀 Next Steps:</h4>";
echo "<ol>";
echo "<li>If no users exist, click 'Create/Reset Admin User' above</li>";
echo "<li>If users exist but passwords don't work, click 'Create/Reset Admin User' to fix it</li>";
echo "<li>Go to <a href='login.php' style='color: #0056b3;'>login.php</a> and try: admin / admin123</li>";
echo "<li>If it still doesn't work, check that your auth.php and login.php files are correct</li>";
echo "</ol>";
echo "</div>";

echo "<p style='color: #999; font-size: 0.8em; margin-top: 30px;'>⚠️ Delete this debug file after fixing the login issue for security.</p>";
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    background: #f5f5f5; 
    color: #333; 
}
table { 
    border-collapse: collapse; 
    width: 100%; 
}
th, td { 
    border: 1px solid #ddd; 
    padding: 8px; 
    text-align: left; 
}
th { 
    background: #007bff; 
    color: white; 
}
</style>