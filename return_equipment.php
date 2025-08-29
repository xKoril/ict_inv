<?php
require 'db.php';

$serial_number = $_GET['serial_number'] ?? '';

if (!$serial_number) {
    header('Location: deployment.php?error=no_serial');
    exit;
}

// Get current equipment info
$equipment_sql = "SELECT e.*, dt.custodian, dt.office_custodian, dt.date_deployed, dt.ics_par_no
                  FROM equipment e
                  LEFT JOIN (
                    SELECT t1.*
                    FROM deployment_transactions t1
                    JOIN (
                      SELECT equipment_id, MAX(date_deployed) AS max_date
                      FROM deployment_transactions
                      GROUP BY equipment_id
                    ) t2 ON t1.equipment_id = t2.equipment_id AND t1.date_deployed = t2.max_date
                  ) dt ON e.equipment_id = dt.equipment_id
                  WHERE e.serial_number = ?";
$equipment_stmt = $pdo->prepare($equipment_sql);
$equipment_stmt->execute([$serial_number]);
$equipment = $equipment_stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    header('Location: deployment.php?error=equipment_not_found');
    exit;
}

// Define authorized personnel who can receive returned equipment (matches ENUM)
$authorized_receivers = ['Angelo', 'Nonie', 'Bemy', 'Kristopher', 'Dan'];

$success = '';
$error = '';

// Handle form submission
if ($_POST) {
    $return_date = $_POST['return_date'] ?? date('Y-m-d');
    $return_remarks = trim($_POST['return_remarks'] ?? '');
    $received_by = trim($_POST['received_by'] ?? '');
    
    if (empty($received_by)) {
        $error = 'Please select who received the equipment.';
    } elseif (!in_array($received_by, $authorized_receivers)) {
        $error = 'Invalid authorized personnel selected.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update equipment status and locator
            $update_sql = "UPDATE equipment 
                          SET equipment_status = 'Available for Deployment', 
                              locator = 'DTI RO - MIS'
                          WHERE equipment_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$equipment['equipment_id']]);
            
            // Insert return transaction record (for audit trail)
            $return_sql = "INSERT INTO return_transactions (equipment_id, previous_ics_par, previous_custodian, previous_office, return_date, return_remarks, received_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $return_stmt = $pdo->prepare($return_sql);
            $return_stmt->execute([
                $equipment['equipment_id'],
                $equipment['ics_par_no'] ?? '',
                $equipment['custodian'] ?? '',
                $equipment['office_custodian'] ?? '',
                $return_date,
                $return_remarks,
                $received_by
            ]);
            
            $pdo->commit();
            $success = 'Equipment returned successfully! Equipment is now available for deployment at DTI RO - MIS. Received by: ' . htmlspecialchars($received_by);
            
            // Update the equipment data to show new status
            $equipment['equipment_status'] = 'Available for Deployment';
            $equipment['locator'] = 'DTI RO - MIS';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            
            // If return_transactions table doesn't exist or doesn't have received_by column, try without it
            try {
                $pdo->beginTransaction();
                
                // Update equipment status and locator only
                $update_sql = "UPDATE equipment 
                              SET equipment_status = 'Available for Deployment', 
                                  locator = 'DTI RO - MIS'
                              WHERE equipment_id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$equipment['equipment_id']]);
                
                $pdo->commit();
                $success = 'Equipment returned successfully! Equipment is now available for deployment at DTI RO - MIS. Received by: ' . htmlspecialchars($received_by);
                
                // Update the equipment data to show new status
                $equipment['equipment_status'] = 'Available for Deployment';
                $equipment['locator'] = 'DTI RO - MIS';
                
            } catch (Exception $e2) {
                $pdo->rollBack();
                $error = 'Error returning equipment: ' . $e2->getMessage();
            }
        }
    }
}

// Get the ICS/PAR for back navigation
$back_ics_par = $equipment['ics_par_no'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Equipment</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 0.9em; }
        .back-link { color: #0056b3; text-decoration: none; margin-bottom: 20px; display: inline-block; }
        .back-link:hover { text-decoration: underline; }
        h1, h2 { margin: 24px 0 8px; }
        .info-card { 
            background: #f8f9fa; 
            border: 1px solid #dee2e6; 
            border-radius: 5px; 
            padding: 20px; 
            margin-bottom: 20px; 
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ccc; 
            border-radius: 3px; 
            box-sizing: border-box;
            font-size: 0.9em;
        }
        .form-group textarea { resize: vertical; height: 80px; }
        .form-group select {
            background-color: white;
            cursor: pointer;
        }
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
        }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; margin-left: 10px; }
        .btn-secondary:hover { background: #545b62; }
        .alert { 
            padding: 12px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f1aeb5; }
        .info-row { margin-bottom: 8px; }
        .info-label { font-weight: bold; display: inline-block; width: 140px; }
        .warning-card { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            border-radius: 5px; 
            padding: 15px; 
            margin-bottom: 20px;
            color: #856404;
        }
        .required { color: #dc3545; }
    </style>
</head>
<body>
    <?php if ($back_ics_par): ?>
        <a href="view_deployment.php?ics_par_no=<?= urlencode($back_ics_par) ?>" class="back-link">← Back to Deployment Details</a>
    <?php else: ?>
        <a href="deployment.php" class="back-link">← Back to Deployment Dashboard</a>
    <?php endif; ?>
    
    <h1>Return Equipment</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
        <p><a href="deployment.php" class="btn btn-secondary">← Back to Dashboard</a></p>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (!$success): ?>
    <div class="info-card">
        <h2>Equipment Information</h2>
        <div class="info-row">
            <span class="info-label">Equipment Type:</span>
            <span><?= htmlspecialchars($equipment['equipment_type']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Brand:</span>
            <span><?= htmlspecialchars($equipment['brand']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Model:</span>
            <span><?= htmlspecialchars($equipment['model']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Serial Number:</span>
            <span><?= htmlspecialchars($equipment['serial_number']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Current Status:</span>
            <span><?= htmlspecialchars($equipment['equipment_status']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Current Locator:</span>
            <span><?= htmlspecialchars($equipment['locator']) ?></span>
        </div>
        <?php if ($equipment['ics_par_no']): ?>
        <div class="info-row">
            <span class="info-label">Current ICS/PAR:</span>
            <span><?= htmlspecialchars($equipment['ics_par_no']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Current Custodian:</span>
            <span><?= htmlspecialchars($equipment['custodian']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Current Office:</span>
            <span><?= htmlspecialchars($equipment['office_custodian']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="warning-card">
        <h3 style="margin-top: 0;">⚠️ Return Equipment</h3>
        <p><strong>This action will:</strong></p>
        <ul style="margin-bottom: 0;">
            <li>Change equipment status to: <strong>"Available for Deployment"</strong></li>
            <li>Set equipment locator to: <strong>"DTI RO - MIS"</strong></li>
            <li>Remove equipment from current deployment</li>
            <li>Make equipment available for new deployments</li>
        </ul>
    </div>
    
    <div class="info-card">
        <h2>Return Details</h2>
        <form method="POST">
            <div class="form-group">
                <label for="return_date">Return Date <span class="required">*</span></label>
                <input type="date" id="return_date" name="return_date" 
                       value="<?= $_POST['return_date'] ?? date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="received_by_select">Received By <span class="required">*</span></label>
                <select id="received_by_select" name="received_by_select" onchange="updateHiddenInput()" required>
                    <option value="">-- Select Authorized Personnel --</option>
                    <?php foreach ($authorized_receivers as $receiver): ?>
                        <option value="<?= htmlspecialchars($receiver) ?>" 
                                <?= (($_POST['received_by'] ?? '') === $receiver) ? 'selected' : '' ?>>
                            👤 <?= htmlspecialchars($receiver) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="received_by" name="received_by" 
                       value="<?= htmlspecialchars($_POST['received_by'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="return_remarks">Return Remarks</label>
                <textarea id="return_remarks" name="return_remarks" 
                          placeholder="Optional: Add any notes about why this equipment is being returned"><?= htmlspecialchars($_POST['return_remarks'] ?? '') ?></textarea>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-danger" onclick="return confirmReturn()">↩️ Return Equipment</button>
                <?php if ($back_ics_par): ?>
                    <a href="view_deployment.php?ics_par_no=<?= urlencode($back_ics_par) ?>" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <a href="deployment.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <script>
    function updateHiddenInput() {
        const select = document.getElementById('received_by_select');
        const hiddenInput = document.getElementById('received_by');
        
        hiddenInput.value = select.value;
    }
    
    // Update hidden input when select changes
    document.getElementById('received_by_select').addEventListener('change', updateHiddenInput);
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateHiddenInput();
    });
    
    function confirmReturn() {
        const equipmentInfo = '<?= htmlspecialchars($equipment['equipment_type']) ?> - <?= htmlspecialchars($equipment['brand']) ?> <?= htmlspecialchars($equipment['model']) ?> (SN: <?= htmlspecialchars($equipment['serial_number']) ?>)';
        const receivedBy = document.getElementById('received_by').value;
        
        if (!receivedBy) {
            alert('Please select who received the equipment.');
            return false;
        }
        
        const message = `Are you sure you want to return this equipment?\n\n${equipmentInfo}\n\nReceived by: ${receivedBy}\n\nThis will:\n• Set status to "Available for Deployment"\n• Set locator to "DTI RO - MIS"\n• Remove from current deployment`;
        
        return confirm(message);
    }
    </script>
</body>
</html>