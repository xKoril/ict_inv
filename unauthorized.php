<?php
// === unauthorized.php - Unauthorized Access Page ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        
        .unauthorized-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            text-align: center;
            max-width: 500px;
        }
        
        .unauthorized-container h1 {
            color: #dc3545;
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .unauthorized-container p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .back-btn {
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            transition: background 0.3s ease;
        }
        
        .back-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="unauthorized-container">
        <h1>🚫 Access Denied</h1>
        <p>You don't have permission to access this resource. Please contact your administrator if you believe this is an error.</p>
        <a href="index.php" class="back-btn">← Back to Dashboard</a>
    </div>
</body>
</html>