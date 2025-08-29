<?php
// === auth.php - Authentication Helper Functions ===

session_start();
require_once 'db.php';

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
    
    // Get current user info
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ? AND is_active = TRUE");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Check if user has permission
    public function hasPermission($action) {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        $role = $user['user_role'];
        
        switch ($action) {
            case 'view':
                return in_array($role, ['Admin', 'Manager', 'User', 'Viewer']);
            case 'add':
            case 'edit':
                return in_array($role, ['Admin', 'Manager', 'User']);
            case 'delete':
                return in_array($role, ['Admin']);
            case 'deploy':
            case 'borrow':
                return in_array($role, ['Admin', 'Manager', 'User']);
            case 'manage_users':
                return in_array($role, ['Admin']);
            default:
                return false;
        }
    }
    
    // Login user
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['user_role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Update last login
            $updateStmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            return true;
        }
        
        return false;
    }
    
    // Logout user
    public function logout() {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    // Require login (redirect if not logged in)
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    // Require specific permission
    public function requirePermission($action) {
        $this->requireLogin();
        if (!$this->hasPermission($action)) {
            header('Location: unauthorized.php');
            exit;
        }
    }
}

// Initialize Auth globally
$auth = new Auth($pdo);

// === login.php - Login Page ===
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
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 10px;
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
        
        .demo-accounts {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .demo-accounts h3 {
            font-size: 0.9rem;
            margin-bottom: 10px;
            color: #333;
        }
        
        .demo-account {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🏢 MIS Inventory</h1>
            <p>Please sign in to your account</p>
        </div>
        
        <?php
        $error = '';
        
        // Handle login submission
        if ($_POST && isset($_POST['username'], $_POST['password'])) {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            if (empty($username) || empty($password)) {
                $error = 'Please enter both username and password.';
            } else {
                if ($auth->login($username, $password)) {
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            }
        }
        ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">Sign In</button>
        </form>
        
        <div class="demo-accounts">
            <h3>Demo Accounts:</h3>
            <div class="demo-account">👑 Admin: admin / admin123</div>
            <div class="demo-account">📋 Manager: manager / admin123</div>
            <div class="demo-account">👤 User: user / admin123</div>
        </div>
    </div>
</body>
</html>
