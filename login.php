<?php
// === login.php - Login Page ===
require_once 'auth.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if ($auth->login($username, $password)) {
            // Successful login - redirect to main page
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Handle logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MIS Equipment Inventory</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 450px;
            margin: 20px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102,126,234,0.2);
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-1px);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }
        
        .demo-accounts {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .demo-accounts h3 {
            font-size: 0.9rem;
            margin-bottom: 15px;
            color: #333;
            text-align: center;
        }
        
        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 5px;
            font-size: 0.85rem;
            border: 1px solid #e0e0e0;
        }
        
        .demo-account:last-child {
            margin-bottom: 0;
        }
        
        .demo-account .role {
            font-weight: 600;
            color: #555;
        }
        
        .demo-account .credentials {
            color: #777;
            font-family: monospace;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .quick-login {
            margin-top: 15px;
            text-align: center;
        }
        
        .quick-login-btn {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 6px 12px;
            margin: 0 3px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .quick-login-btn:hover {
            background: #e9ecef;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .login-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🏢 MIS Inventory</h1>
            <p>Please sign in to your account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">👤 Username</label>
                <input type="text" id="username" name="username" 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                       required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">🔒 Password</label>
                <input type="password" id="password" name="password" 
                       required autocomplete="current-password">
            </div>
            
            <button type="submit" class="login-btn">🚀 Sign In</button>
        </form>
        
        <div class="demo-accounts">
            <h3>🧪 Demo Accounts (Password: admin123)</h3>
            <div class="demo-account">
                <span class="role">👑 Administrator</span>
                <span class="credentials">admin</span>
            </div>
            <div class="demo-account">
                <span class="role">📋 Manager</span>
                <span class="credentials">manager</span>
            </div>
            <div class="demo-account">
                <span class="role">👤 User</span>
                <span class="credentials">user</span>
            </div>
            <div class="demo-account">
                <span class="role">👀 Viewer</span>
                <span class="credentials">viewer</span>
            </div>
            
            <div class="quick-login">
                <p style="font-size: 0.8rem; color: #666; margin-bottom: 8px;">Quick Login:</p>
                <button type="button" class="quick-login-btn" onclick="quickLogin('admin')">Admin</button>
                <button type="button" class="quick-login-btn" onclick="quickLogin('manager')">Manager</button>
                <button type="button" class="quick-login-btn" onclick="quickLogin('user')">User</button>
                <button type="button" class="quick-login-btn" onclick="quickLogin('viewer')">Viewer</button>
            </div>
        </div>
    </div>
    
    <script>
        function quickLogin(username) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = 'admin123';
            document.getElementById('loginForm').submit();
        }
        
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Handle Enter key in password field
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>