<?php
// manage_users.php - User Management Interface (Admin Only)
require_once 'auth.php';

// Require admin permission
$auth->requirePermission('manage_users');
$currentUser = $auth->getCurrentUser();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_user') {
            // Create new user
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $user_role = $_POST['user_role'] ?? '';
            $email = trim($_POST['email'] ?? '');
            
            if (empty($username) || empty($password) || empty($full_name) || empty($user_role)) {
                throw new Exception('All required fields must be filled');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters long');
            }
            
            // Check if username already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Username already exists');
            }
            
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, user_role, email, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, $password_hash, $full_name, $user_role, $email]);
            
            $message = "User '$username' created successfully!";
            
        } elseif ($action === 'edit_user') {
            // Edit existing user
            $user_id = (int)($_POST['user_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $user_role = $_POST['user_role'] ?? '';
            $email = trim($_POST['email'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $new_password = trim($_POST['new_password'] ?? '');
            
            if (empty($username) || empty($full_name) || empty($user_role)) {
                throw new Exception('Username, full name, and role are required');
            }
            
            // Check if username exists for other users
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
            $checkStmt->execute([$username, $user_id]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Username already exists for another user');
            }
            
            // Update user info
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    throw new Exception('Password must be at least 6 characters long');
                }
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=?, password_hash=?, full_name=?, user_role=?, email=?, is_active=? WHERE user_id=?");
                $stmt->execute([$username, $password_hash, $full_name, $user_role, $email, $is_active, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, user_role=?, email=?, is_active=? WHERE user_id=?");
                $stmt->execute([$username, $full_name, $user_role, $email, $is_active, $user_id]);
            }
            
            $message = "User '$username' updated successfully!";
            
        } elseif ($action === 'delete_user') {
            // Delete user
            $user_id = (int)($_POST['user_id'] ?? 0);
            
            // Don't allow deleting yourself
            if ($user_id === $currentUser['user_id']) {
                throw new Exception('You cannot delete your own account');
            }
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            $message = "User deleted successfully!";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get user to edit if specified
$editUser = null;
if (isset($_GET['edit'])) {
    $editUserId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$editUserId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 300;
        }
        
        .user-info {
            position: absolute;
            top: 15px;
            right: 30px;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .back-link {
            position: absolute;
            top: 15px;
            left: 30px;
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 8px 12px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .content {
            padding: 30px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }
        
        .form-section h2 {
            margin: 0 0 20px 0;
            color: #495057;
            font-size: 1.3rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            margin-right: 10px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .table-wrapper {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .role-admin {
            background: #dc3545;
            color: white;
        }
        
        .role-manager {
            background: #fd7e14;
            color: white;
        }
        
        .role-user {
            background: #28a745;
            color: white;
        }
        
        .role-viewer {
            background: #6c757d;
            color: white;
        }
        
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        
        .required {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header {
                padding: 20px;
                position: relative;
            }
            
            .back-link, .user-info {
                position: static;
                display: block;
                text-align: center;
                margin: 5px 0;
            }
            
            .content {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .table-wrapper {
                max-height: 400px;
            }
            
            th, td {
                padding: 8px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="back-link">← Back to Dashboard</a>
            <div class="user-info">
                👤 <?= htmlspecialchars($currentUser['full_name']) ?> (<?= htmlspecialchars($currentUser['user_role']) ?>)
            </div>
            <h1>👥 User Management</h1>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- Create/Edit User Form -->
            <div class="form-section">
                <h2><?= $editUser ? '✏️ Edit User' : '➕ Create New User' ?></h2>
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editUser ? 'edit_user' : 'create_user' ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?= $editUser['user_id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username <span class="required">*</span></label>
                            <input type="text" name="username" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($editUser['full_name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>User Role <span class="required">*</span></label>
                            <select name="user_role" required>
                                <option value="">--Select Role--</option>
                                <option value="Admin" <?= ($editUser['user_role'] ?? '') === 'Admin' ? 'selected' : '' ?>>👑 Administrator</option>
                                <option value="Manager" <?= ($editUser['user_role'] ?? '') === 'Manager' ? 'selected' : '' ?>>📋 Manager</option>
                                <option value="User" <?= ($editUser['user_role'] ?? '') === 'User' ? 'selected' : '' ?>>👤 User</option>
                                <option value="Viewer" <?= ($editUser['user_role'] ?? '') === 'Viewer' ? 'selected' : '' ?>>👀 Viewer</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><?= $editUser ? 'New Password (leave blank to keep current)' : 'Password' ?> <?= !$editUser ? '<span class="required">*</span>' : '' ?></label>
                            <input type="password" name="<?= $editUser ? 'new_password' : 'password' ?>" <?= !$editUser ? 'required' : '' ?> minlength="6">
                        </div>
                        
                        <?php if ($editUser): ?>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" <?= $editUser['is_active'] ? 'checked' : '' ?>>
                                <label for="is_active">Account Active</label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-success">
                            <?= $editUser ? '💾 Update User' : '➕ Create User' ?>
                        </button>
                        <?php if ($editUser): ?>
                            <a href="manage_users.php" class="btn btn-secondary">❌ Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="table-container">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['user_id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                    <?= $user['user_id'] === $currentUser['user_id'] ? '<small style="color: #007bff;"> (You)</small>' : '' ?>
                                </td>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td>
                                    <span class="role-badge role-<?= strtolower($user['user_role']) ?>">
                                        <?php 
                                        $roleIcons = ['Admin' => '👑', 'Manager' => '📋', 'User' => '👤', 'Viewer' => '👀'];
                                        echo ($roleIcons[$user['user_role']] ?? '') . ' ' . $user['user_role'];
                                        ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['email'] ?: 'N/A') ?></td>
                                <td>
                                    <span class="<?= $user['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $user['is_active'] ? '✅ Active' : '❌ Inactive' ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                <td><?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                                <td>
                                    <a href="?edit=<?= $user['user_id'] ?>" class="btn btn-warning" style="padding: 6px 12px; font-size: 0.85rem;">
                                        ✏️ Edit
                                    </a>
                                    <?php if ($user['user_id'] !== $currentUser['user_id']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete user \'<?= htmlspecialchars($user['username']) ?>\'? This action cannot be undone.')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.85rem;">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div style="background: #e7f3ff; color: #0c5460; padding: 20px; border-radius: 5px; margin-top: 30px;">
                <h4 style="margin: 0 0 10px 0;">🔐 Role Permissions:</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong>👑 Administrator:</strong><br>
                        <small>Full system access including user management</small>
                    </div>
                    <div>
                        <strong>📋 Manager:</strong><br>
                        <small>Can add, edit, deploy, and borrow equipment</small>
                    </div>
                    <div>
                        <strong>👤 User:</strong><br>
                        <small>Can add, edit, deploy, and borrow equipment</small>
                    </div>
                    <div>
                        <strong>👀 Viewer:</strong><br>
                        <small>Can only view equipment (read-only access)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>