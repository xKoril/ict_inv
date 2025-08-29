<?php
require 'db.php';

$current_ics_par_no = $_GET['ics_par_no'] ?? '';
$serial_number = $_GET['serial_number'] ?? '';

$success = '';
$error = '';

// Get equipment details
if ($serial_number) {
    $equipment_stmt = $pdo->prepare('
        SELECT e.*, dt.ics_par_no as current_ics_par_no, dt.custodian as current_custodian, dt.office_custodian as current_office
        FROM equipment e
        LEFT JOIN deployment_transactions dt ON e.equipment_id = dt.equipment_id
        WHERE e.serial_number = ?
        ORDER BY dt.date_deployed DESC, dt.time_deployed DESC
        LIMIT 1
    ');
    $equipment_stmt->execute([$serial_number]);
    $equipment = $equipment_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipment) {
        $error = 'Equipment not found';
    }
} else {
    $error = 'Serial number is required';
}

// Offices list for dropdown
$offices = [
    'DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo',
    'DTI-Negros Occ','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD',
    'DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipment) {
    try {
        $new_ics_par_no = trim($_POST['new_ics_par_no'] ?? '');
        $new_custodian = trim($_POST['new_custodian'] ?? '');
        $new_office = trim($_POST['new_office'] ?? '');
        $date_transferred = $_POST['date_transferred'] ?? '';
        $time_transferred = $_POST['time_transferred'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Validate required fields
        if (empty($new_ics_par_no) || empty($new_custodian) || empty($new_office) || empty($date_transferred) || empty($time_transferred)) {
            throw new Exception('All required fields must be filled');
        }
        
        // Validate date format
        if (!DateTime::createFromFormat('Y-m-d', $date_transferred)) {
            throw new Exception('Invalid date format');
        }
        
        // Validate time format
        if (!DateTime::createFromFormat('H:i', $time_transferred)) {
            throw new Exception('Invalid time format');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert new deployment transaction
        $insert_deployment_sql = "INSERT INTO deployment_transactions (
            equipment_id, 
            ics_par_no, 
            date_deployed, 
            time_deployed, 
            custodian, 
            office_custodian, 
            remarks
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = $pdo->prepare($insert_deployment_sql);
        $insert_result = $insert_stmt->execute([
            $equipment['equipment_id'],
            $new_ics_par_no,
            $date_transferred,
            $time_transferred,
            $new_custodian,
            $new_office,
            $remarks
        ]);
        
        if (!$insert_result) {
            throw new Exception('Failed to create new deployment record');
        }
        
        // Update equipment table with new ICS/PAR, locator, and ensure status is Deployed
        $update_equipment_sql = "UPDATE equipment 
                                SET ics_par_no = ?, equipment_status = 'Deployed', locator = ?
                                WHERE equipment_id = ?";
        
        $update_stmt = $pdo->prepare($update_equipment_sql);
        $update_result = $update_stmt->execute([$new_ics_par_no, $new_office, $equipment['equipment_id']]);
        
        if (!$update_result) {
            throw new Exception('Failed to update equipment record');
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success = "Equipment successfully transferred to ICS/PAR {$new_ics_par_no}!";
        
        // Clear form data
        $_POST = [];
        
        // Redirect after a short delay
        header("refresh:3;url=view_deployment.php?ics_par_no=" . urlencode($new_ics_par_no));
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Equipment</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f9f9f9; 
            margin: 0; 
            padding: 20px; 
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .back-link {
            color: #0056b3;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
            font-weight: 500;
        }
        .back-link:hover { text-decoration: underline; }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 3px solid #0056b3;
            padding-bottom: 10px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: 500;
        }
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .equipment-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .equipment-info h3 {
            margin-top: 0;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }
        .info-row {
            margin: 10px 0;
            display: flex;
        }
        .info-label {
            font-weight: bold;
            width: 180px;
            color: #495057;
        }
        .info-value {
            flex: 1;
            color: #212529;
        }
        
        .form-section {
            background: #fff;
            border: 2px solid #007bff;
            border-radius: 5px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin-top: 0;
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        input, select, textarea {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.2s;
            margin-right: 10px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .form-actions {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .info-row {
                flex-direction: column;
            }
            .info-label {
                width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?= $current_ics_par_no ? 'view_deployment.php?ics_par_no=' . urlencode($current_ics_par_no) : 'deployment.php' ?>" class="back-link">
            ← Back to <?= $current_ics_par_no ? 'Deployment View' : 'Dashboard' ?>
        </a>
        
        <h1>🔄 Transfer Equipment</h1>
        
        <?php if ($success): ?>
            <div class="alert success">
                ✅ <?= htmlspecialchars($success) ?>
                <br><small>Redirecting to new deployment view...</small>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($equipment && !$error): ?>
            <div class="equipment-info">
                <h3>📋 Equipment Information</h3>
                <div class="info-row">
                    <span class="info-label">Equipment Type:</span>
                    <span class="info-value"><?= htmlspecialchars($equipment['equipment_type']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Brand & Model:</span>
                    <span class="info-value"><?= htmlspecialchars($equipment['brand'] . ' ' . $equipment['model']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Serial Number:</span>
                    <span class="info-value"><?= htmlspecialchars($equipment['serial_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Current ICS/PAR:</span>
                    <span class="info-value"><?= htmlspecialchars($equipment['current_ics_par_no'] ?: 'Not assigned') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Current Custodian:</span>
                    <span class="info-value"><?= htmlspecialchars($equipment['current_custodian'] ?: 'Not assigned') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Current Office:</span>
                    <span class="info-value"><?= htmlspecialchars($equipment['current_office'] ?: 'Not assigned') ?></span>
                </div>
            </div>
            
            <form method="post">
                <div class="form-section">
                    <h3>🎯 New Deployment Details</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="new_ics_par_no" class="required">New ICS/PAR No.</label>
                            <input type="text" 
                                   id="new_ics_par_no" 
                                   name="new_ics_par_no" 
                                   value="<?= htmlspecialchars($_POST['new_ics_par_no'] ?? '') ?>" 
                                   required
                                   placeholder="e.g., 2025-01-001">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_custodian" class="required">New Custodian</label>
                            <input type="text" 
                                   id="new_custodian" 
                                   name="new_custodian" 
                                   value="<?= htmlspecialchars($_POST['new_custodian'] ?? '') ?>" 
                                   required
                                   placeholder="Full name of custodian">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_office" class="required">New Office</label>
                            <select id="new_office" name="new_office" required>
                                <option value="">--Select Office--</option>
                                <?php foreach ($offices as $office): ?>
                                    <option value="<?= htmlspecialchars($office) ?>" 
                                            <?= ($office === ($_POST['new_office'] ?? '')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($office) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_transferred" class="required">Date Transferred</label>
                            <input type="date" 
                                   id="date_transferred" 
                                   name="date_transferred" 
                                   value="<?= htmlspecialchars($_POST['date_transferred'] ?? date('Y-m-d')) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="time_transferred" class="required">Time Transferred</label>
                            <input type="time" 
                                   id="time_transferred" 
                                   name="time_transferred" 
                                   value="<?= htmlspecialchars($_POST['time_transferred'] ?? date('H:i')) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" 
                                      name="remarks" 
                                      placeholder="Optional: Transfer reason, conditions, etc."><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        🔄 Transfer Equipment
                    </button>
                    <a href="<?= $current_ics_par_no ? 'view_deployment.php?ics_par_no=' . urlencode($current_ics_par_no) : 'deployment.php' ?>" 
                       class="btn btn-secondary">
                        ❌ Cancel
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-focus on the first input field
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let hasErrors = false;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    hasErrors = true;
                } else {
                    field.style.borderColor = '#ced4da';
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>