<?php
require 'db.php';

$ics_par_no = $_GET['ics_par_no'] ?? '';
$serial_number = $_GET['serial_number'] ?? '';

if (!$ics_par_no || !$serial_number) {
    header('Location: deployment.php');
    exit;
}

// Get current equipment info
$equipment_sql = "SELECT e.*, dt.custodian, dt.office_custodian, dt.date_deployed, dt.ics_par_no
                  FROM equipment e
                  JOIN (
                    SELECT t1.*
                    FROM deployment_transactions t1
                    JOIN (
                      SELECT equipment_id, MAX(date_deployed) AS max_date
                      FROM deployment_transactions
                      GROUP BY equipment_id
                    ) t2 ON t1.equipment_id = t2.equipment_id AND t1.date_deployed = t2.max_date
                  ) dt ON e.equipment_id = dt.equipment_id
                  WHERE e.serial_number = ? AND dt.ics_par_no = ?";
$equipment_stmt = $pdo->prepare($equipment_sql);
$equipment_stmt->execute([$serial_number, $ics_par_no]);
$equipment = $equipment_stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    header('Location: deployment.php?error=equipment_not_found');
    exit;
}

// Offices list
$offices = [
    'DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo',
    'DTI-Negros Occ','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD',
    'DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'
];

$success = '';
$error = '';

// Handle form submission
if ($_POST) {
    $new_ics_par = trim($_POST['new_ics_par'] ?? '');
    $new_custodian = trim($_POST['new_custodian'] ?? '');
    $new_office = $_POST['new_office'] ?? '';
    $transfer_date = $_POST['transfer_date'] ?? date('Y-m-d');
    $remarks = trim($_POST['remarks'] ?? '');
    
    if (!$new_ics_par || !$new_custodian || !$new_office) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert new deployment transaction
            $insert_sql = "INSERT INTO deployment_transactions (equipment_id, ics_par_no, custodian, office_custodian, date_deployed, remarks) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                $equipment['equipment_id'],
                $new_ics_par,
                $new_custodian,
                $new_office,
                $transfer_date,
                $remarks
            ]);
            
            // Update equipment status if needed
            if ($equipment['equipment_status'] !== 'Deployed') {
                $update_sql = "UPDATE equipment SET equipment_status = 'Deployed' WHERE equipment_id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$equipment['equipment_id']]);
            }
            
            $pdo->commit();
            $success = 'Equipment transferred successfully!';
            
            // Update the equipment data to show new info
            $equipment['ics_par_no'] = $new_ics_par;
            $equipment['custodian'] = $new_custodian;
            $equipment['office_custodian'] = $new_office;
            $equipment['date_deployed'] = $transfer_date;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error transferring equipment: ' . $e->getMessage();
        }
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
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ccc; 
            border-radius: 3px; 
            box-sizing: border-box;
        }
        .form-group textarea { resize: vertical; height: 80px; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
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
        .required { color: red; }
    </style>
</head>
<body>
    <a href="view_deployment.php?ics_par_no=<?= urlencode($ics_par_no) ?>" class="back-link">← Back to Deployment Details</a>
    
    <h1>Transfer Equipment</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="info-card">
        <h2>Current Equipment Information</h2>
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
        <div class="info-row">
            <span class="info-label">Date Deployed:</span>
            <span><?= $equipment['date_deployed'] ?></span>
        </div>
    </div>
    
    <div class="info-card">
        <h2>Transfer To</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="new_ics_par">New ICS/PAR No. <span class="required">*</span></label>
                    <input type="text" id="new_ics_par" name="new_ics_par" required 
                           value="<?= htmlspecialchars($_POST['new_ics_par'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="transfer_date">Transfer Date <span class="required">*</span></label>
                    <input type="date" id="transfer_date" name="transfer_date" required 
                           value="<?= $_POST['transfer_date'] ?? date('Y-m-d') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="new_custodian">New Custodian <span class="required">*</span></label>
                    <input type="text" id="new_custodian" name="new_custodian" required 
                           value="<?= htmlspecialchars($_POST['new_custodian'] ?? '') ?>"
                           placeholder="Full name of new custodian">
                </div>
                <div class="form-group">
                    <label for="new_office">New Office <span class="required">*</span></label>
                    <select id="new_office" name="new_office" required>
                        <option value="">-- Select Office --</option>
                        <?php foreach ($offices as $office): ?>
                            <option value="<?= htmlspecialchars($office) ?>" 
                                    <?= (($_POST['new_office'] ?? '') === $office) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($office) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="remarks">Transfer Remarks</label>
                <textarea id="remarks" name="remarks" 
                          placeholder="Optional: Add any notes about this transfer"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">🔄 Transfer Equipment</button>
                <a href="view_deployment.php?ics_par_no=<?= urlencode($ics_par_no) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    
    <div class="info-card" style="background: #fff3cd; border-color: #ffeaa7;">
        <h3 style="color: #856404; margin-top: 0;">Transfer Information</h3>
        <ul style="color: #856404; margin-bottom: 0;">
            <li>This will create a new deployment record for the equipment</li>
            <li>The equipment will be transferred to the new custodian and office</li>
            <li>The original deployment record will remain for audit purposes</li>
            <li>Equipment status will be updated to "Deployed" if not already</li>
        </ul>
    </div>
    
    <script>
    // Auto-generate ICS/PAR suggestion based on office and date
    document.getElementById('new_office').addEventListener('change', function() {
        const office = this.value;
        const date = document.getElementById('transfer_date').value;
        const icsField = document.getElementById('new_ics_par');
        
        if (office && date && !icsField.value) {
            const datePart = date.replace(/-/g, '');
            const officePart = office.replace(/[^A-Z]/g, '');
            const suggestion = `${officePart}-${datePart}-001`;
            icsField.placeholder = `Suggestion: ${suggestion}`;
        }
    });
    
    // Confirm before transfer
    document.querySelector('form').addEventListener('submit', function(e) {
        const newCustodian = document.getElementById('new_custodian').value;
        const newOffice = document.getElementById('new_office').value;
        const newIcsPar = document.getElementById('new_ics_par').value;
        
        const message = `Are you sure you want to transfer this equipment to:\n\nCustodian: ${newCustodian}\nOffice: ${newOffice}\nICS/PAR: ${newIcsPar}`;
        
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
    </script>
</body>
</html>