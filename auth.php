<?php
// === auth.php - Authentication Helper Functions ===

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ? AND is_active = TRUE");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Auth getCurrentUser error: " . $e->getMessage());
            return null;
        }
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
        try {
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
        } catch (PDOException $e) {
            error_log("Auth login error: " . $e->getMessage());
            return false;
        }
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
    
    // Create new user (Admin only)
    public function createUser($username, $password, $full_name, $user_role, $email = null) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, full_name, user_role, email) VALUES (?, ?, ?, ?, ?)");
            return $stmt->execute([$username, $password_hash, $full_name, $user_role, $email]);
        } catch (PDOException $e) {
            error_log("Auth createUser error: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize Auth globally
try {
    $auth = new Auth($pdo);
} catch (Exception $e) {
    error_log("Auth initialization error: " . $e->getMessage());
    die("Authentication system error. Please contact administrator.");
}
?>