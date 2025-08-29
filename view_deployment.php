<?php
require 'db.php';

$ics_par_no = $_GET['ics_par_no'] ?? '';

if (!$ics_par_no) {
    header('Location: deployment.php');
    exit;
}

// Get deployment summary
$summary_sql = "SELECT ics_par_no, custodian, office_custodian, date_deployed, COUNT(*) AS total_equipment
                FROM deployment_transactions 
                WHERE ics_par_no = ?
                GROUP BY ics_par_no, custodian, office_custodian, date_deployed
                ORDER BY date_deployed DESC";
$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute([$ics_par_no]);
$deployment_info = $summary_stmt->fetch(PDO::FETCH_ASSOC);

if (!$deployment_info) {
    header('Location: deployment.php?error=not_found');
    exit;
}

// Modified query to track transfer/return status and include amount_unit_cost
$equipment_sql = "SELECT e.equipment_type, e.brand, e.model, e.serial_number, e.locator,
                         e.description_specification, dt.date_deployed, e.equipment_status,
                         e.equipment_id, e.amount_unit_cost,
                         -- Check if equipment was transferred (has newer deployment)
                         CASE 
                             WHEN EXISTS (
                                 SELECT 1 FROM deployment_transactions dt2 
                                 WHERE dt2.equipment_id = e.equipment_id 
                                 AND (dt2.date_deployed > dt.date_deployed 
                                      OR (dt2.date_deployed = dt.date_deployed AND dt2.time_deployed > dt.time_deployed))
                             ) THEN 'Transferred'
                             -- Check if equipment was returned
                             WHEN EXISTS (
                                 SELECT 1 FROM return_transactions rt 
                                 WHERE rt.equipment_id = e.equipment_id 
                                 AND rt.return_date >= dt.date_deployed
                                 AND rt.previous_ics_par = dt.ics_par_no
                             ) THEN 'Returned'
                             -- Check current status
                             WHEN e.equipment_status = 'Deployed' AND e.ics_par_no = dt.ics_par_no THEN 'Active'
                             ELSE 'Inactive'
                         END as deployment_status,
                         -- Get transfer/return details
                         CASE 
                             WHEN EXISTS (
                                 SELECT 1 FROM deployment_transactions dt2 
                                 WHERE dt2.equipment_id = e.equipment_id 
                                 AND (dt2.date_deployed > dt.date_deployed 
                                      OR (dt2.date_deployed = dt.date_deployed AND dt2.time_deployed > dt.time_deployed))
                             ) THEN (
                                 SELECT CONCAT('To: ', dt2.ics_par_no, ' (', dt2.date_deployed, ')')
                                 FROM deployment_transactions dt2 
                                 WHERE dt2.equipment_id = e.equipment_id 
                                 AND (dt2.date_deployed > dt.date_deployed 
                                      OR (dt2.date_deployed = dt.date_deployed AND dt2.time_deployed > dt.time_deployed))
                                 ORDER BY dt2.date_deployed DESC, dt2.time_deployed DESC
                                 LIMIT 1
                             )
                             WHEN EXISTS (
                                 SELECT 1 FROM return_transactions rt 
                                 WHERE rt.equipment_id = e.equipment_id 
                                 AND rt.return_date >= dt.date_deployed
                                 AND rt.previous_ics_par = dt.ics_par_no
                             ) THEN (
                                 SELECT CONCAT('Returned on ', rt.return_date, 
                                        CASE WHEN rt.received_by IS NOT NULL 
                                             THEN CONCAT(' by ', rt.received_by) 
                                             ELSE '' END)
                                 FROM return_transactions rt 
                                 WHERE rt.equipment_id = e.equipment_id 
                                 AND rt.return_date >= dt.date_deployed
                                 AND rt.previous_ics_par = dt.ics_par_no
                                 ORDER BY rt.return_date DESC
                                 LIMIT 1
                             )
                             ELSE NULL
                         END as status_details
                  FROM equipment e
                  JOIN deployment_transactions dt ON e.equipment_id = dt.equipment_id
                  WHERE dt.ics_par_no = ?
                  ORDER BY dt.date_deployed DESC, e.equipment_type";
$equipment_stmt = $pdo->prepare($equipment_sql);
$equipment_stmt->execute([$ics_par_no]);
$equipment_list = $equipment_stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine which print buttons to show based on equipment costs
$has_ics_equipment = false;  // Equipment with cost < 50000
$has_par_equipment = false;  // Equipment with cost >= 50000

foreach ($equipment_list as $item) {
    if ($item['amount_unit_cost'] >= 50000) {
        $has_par_equipment = true;
    } else {
        $has_ics_equipment = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Deployment - <?= htmlspecialchars($ics_par_no) ?></title>
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
        .info-row { margin-bottom: 10px; }
        .info-label { font-weight: bold; display: inline-block; width: 150px; }
        .edit-btn { 
            background: #17a2b8; 
            color: white; 
            border: none; 
            padding: 6px 12px; 
            border-radius: 3px; 
            cursor: pointer; 
            font-size: 0.8em;
            margin-left: 10px;
        }
        .edit-btn:hover { background: #138496; }
        .edit-form { display: none; margin-top: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 3px; 
            max-width: 300px;
        }
        .form-buttons { margin-top: 15px; }
        .save-btn { 
            background: #28a745; 
            color: white; 
            border: none; 
            padding: 8px 16px; 
            border-radius: 3px; 
            cursor: pointer; 
            margin-right: 10px;
        }
        .save-btn:hover { background: #218838; }
        .cancel-btn { 
            background: #6c757d; 
            color: white; 
            border: none; 
            padding: 8px 16px; 
            border-radius: 3px; 
            cursor: pointer; 
        }
        .cancel-btn:hover { background: #545b62; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        
        /* Status styling */
        .status-active { 
            color: #28a745; 
            font-weight: bold; 
            background-color: #d4edda;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-transferred { 
            color: #fd7e14; 
            font-weight: bold; 
            background-color: #fff3cd;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-returned { 
            color: #6c757d; 
            font-weight: bold; 
            background-color: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-inactive { 
            color: #dc3545; 
            font-weight: bold; 
            background-color: #f8d7da;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .status-details {
            font-size: 0.8em;
            color: #666;
            font-style: italic;
            margin-top: 4px;
        }
        
        .print-btn { 
            background: #28a745; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 3px; 
            cursor: pointer; 
            margin-right: 10px;
        }
        .print-btn:hover { background: #218838; }
        
        .legend {
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .legend h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #495057;
        }
        .legend-item {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 5px;
        }
        
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <a href="deployment.php" class="back-link">← Back to Deployment Dashboard</a>
    </div>
    
    <h1>Deployment Details</h1>
    
    <div class="info-card">
        <div style="display: flex; justify-content: between; align-items: center;">
            <h2 style="margin: 0;">ICS/PAR Information</h2>
            <button class="edit-btn no-print" onclick="toggleEditForm()">✏️ Edit Deployment</button>
        </div>
        
        <div id="deployment-info" style="margin-top: 15px;">
            <div class="info-row">
                <span class="info-label">ICS/PAR No.:</span>
                <span><?= htmlspecialchars($deployment_info['ics_par_no']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Custodian:</span>
                <span><?= htmlspecialchars($deployment_info['custodian']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Office:</span>
                <span><?= htmlspecialchars($deployment_info['office_custodian']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date Deployed:</span>
                <span><?= $deployment_info['date_deployed'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Equipment:</span>
                <span><?= $deployment_info['total_equipment'] ?> items</span>
            </div>
        </div>

        <div id="edit-form" class="edit-form">
            <form id="deploymentUpdateForm">
                <input type="hidden" name="original_ics_par_no" value="<?= htmlspecialchars($deployment_info['ics_par_no']) ?>">
                
                <div class="form-group">
                    <label for="ics_par_no">ICS/PAR No.:</label>
                    <input type="text" id="ics_par_no" name="ics_par_no" value="<?= htmlspecialchars($deployment_info['ics_par_no']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="custodian">Custodian:</label>
                    <input type="text" id="custodian" name="custodian" value="<?= htmlspecialchars($deployment_info['custodian']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="office_custodian">Office:</label>
                    <input type="text" id="office_custodian" name="office_custodian" value="<?= htmlspecialchars($deployment_info['office_custodian']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="date_deployed">Date Deployed:</label>
                    <input type="date" id="date_deployed" name="date_deployed" value="<?= $deployment_info['date_deployed'] ?>" required>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="save-btn" onclick="updateDeployment()">💾 Save Changes</button>
                    <button type="button" class="cancel-btn" onclick="toggleEditForm()">❌ Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Status Legend -->
    <div class="legend no-print">
        <h3>📊 Equipment Status Legend</h3>
        <div class="legend-item">
            <span class="status-active">Active</span> - Currently deployed under this ICS/PAR
        </div>
        <div class="legend-item">
            <span class="status-transferred">Transferred</span> - Moved to another ICS/PAR
        </div>
        <div class="legend-item">
            <span class="status-returned">Returned</span> - Returned to inventory
        </div>
        <div class="legend-item">
            <span class="status-inactive">Inactive</span> - Status changed or reassigned
        </div>
    </div>
    
    <div class="no-print">
        <?php if ($has_ics_equipment): ?>
            <button class="print-btn" onclick="printDeployment()">Print ICS Report</button>
        <?php endif; ?>
        
        <?php if ($has_par_equipment): ?>
            <button class="print-btn" onclick="printPAR()" style="background: #17a2b8;">Print PAR Report</button>
        <?php endif; ?>
        
        <button class="print-btn" onclick="exportToCSV()" style="background: #007bff;">Export CSV</button>
        
        <?php if ($has_ics_equipment && $has_par_equipment): ?>
            <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                Note: ICS for equipment &lt; ₱50,000 | PAR for equipment ≥ ₱50,000
            </div>
        <?php endif; ?>
    </div>
    
    <h2>Equipment List</h2>
    <table id="equipmentTable">
        <thead>
            <tr>
                <th>Type</th>
                <th>Brand</th>
                <th>Model</th>
                <th>Serial Number</th>
                <th>Unit Cost</th>
                <th>Locator</th>
                <th>Description</th>
                <th>Status</th>
                <th class="no-print">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($equipment_list as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['equipment_type']) ?></td>
                <td><?= htmlspecialchars($item['brand']) ?></td>
                <td><?= htmlspecialchars($item['model']) ?></td>
                <td><?= htmlspecialchars($item['serial_number']) ?></td>
                <td style="text-align: right; <?= $item['amount_unit_cost'] >= 50000 ? 'color: #dc3545; font-weight: bold;' : 'color: #28a745;' ?>">
                    ₱<?= number_format($item['amount_unit_cost'], 2) ?>
                    <?= $item['amount_unit_cost'] >= 50000 ? ' (PAR)' : ' (ICS)' ?>
                </td>
                <td><?= htmlspecialchars($item['locator']) ?></td>
                <td><?= htmlspecialchars($item['description_specification']) ?></td>
                <td>
                    <span class="status-<?= strtolower($item['deployment_status']) ?>">
                        <?= htmlspecialchars($item['deployment_status']) ?>
                    </span>
                    <?php if ($item['status_details']): ?>
                        <div class="status-details">
                            <?= htmlspecialchars($item['status_details']) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="no-print">
                    <?php if ($item['deployment_status'] === 'Active'): ?>
                        <button onclick="transferEquipment('<?= $deployment_info['ics_par_no'] ?>', '<?= $item['serial_number'] ?>')" 
                                style="background: #ffc107; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                            Transfer
                        </button>
                        <button onclick="returnEquipment('<?= $item['serial_number'] ?>')" 
                                style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-left: 5px;">
                            Return
                        </button>
                    <?php elseif ($item['deployment_status'] === 'Transferred'): ?>
                        <span style="color: #fd7e14; font-style: italic;">Transferred</span>
                    <?php elseif ($item['deployment_status'] === 'Returned'): ?>
                        <span style="color: #6c757d; font-style: italic;">Returned</span>
                    <?php else: ?>
                        <span style="color: #6c757d; font-style: italic;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if (empty($equipment_list)): ?>
        <p>No equipment found for this ICS/PAR number.</p>
    <?php endif; ?>

    <script>
    function toggleEditForm() {
        const infoDiv = document.getElementById('deployment-info');
        const editForm = document.getElementById('edit-form');
        
        if (editForm.style.display === 'none' || editForm.style.display === '') {
            infoDiv.style.display = 'none';
            editForm.style.display = 'block';
        } else {
            infoDiv.style.display = 'block';
            editForm.style.display = 'none';
        }
    }
    
    function updateDeployment() {
        const form = document.getElementById('deploymentUpdateForm');
        const formData = new FormData(form);
        
        // Show loading state
        const saveBtn = document.querySelector('.save-btn');
        const originalText = saveBtn.textContent;
        saveBtn.textContent = '🔄 Updating...';
        saveBtn.disabled = true;
        
        fetch('update_deployment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response as text first to check what we're getting
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text); // Debug log
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response from server');
            }
            
            if (data.success) {
                alert('Deployment updated successfully!');
                // If ICS/PAR number changed, redirect to new URL
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    location.reload(); // Refresh page to show updated data
                }
            } else {
                alert('Error updating deployment: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating deployment: ' + error.message + '. Please check the console for more details.');
        })
        .finally(() => {
            saveBtn.textContent = originalText;
            saveBtn.disabled = false;
        });
    }
    
    function printDeployment() {
        // Open dedicated print page in a new window
        const printWindow = window.open(`print_deployment.php?ics_par_no=${encodeURIComponent('<?= $ics_par_no ?>')}`, '_blank', 'width=800,height=600');
        
        // Optional: Auto-print when window loads
        printWindow.onload = function() {
            setTimeout(() => {
                printWindow.print();
            }, 500); // Small delay to ensure content is fully loaded
        };
    }
    
    function printPAR() {
        // Open dedicated PAR print page in a new window
        const printWindow = window.open(`print_par.php?ics_par_no=${encodeURIComponent('<?= $ics_par_no ?>')}`, '_blank', 'width=800,height=600');
        
        // Optional: Auto-print when window loads
        printWindow.onload = function() {
            setTimeout(() => {
                printWindow.print();
            }, 500); // Small delay to ensure content is fully loaded
        };
    }
    
    function transferEquipment(icsParNo, serialNumber) {
        window.location.href = `transfer_equipment.php?ics_par_no=${encodeURIComponent(icsParNo)}&serial_number=${encodeURIComponent(serialNumber)}`;
    }
    
    function returnEquipment(serialNumber) {
        if (confirm('Are you sure you want to return this equipment?')) {
            window.location.href = `return_equipment.php?serial_number=${encodeURIComponent(serialNumber)}`;
        }
    }
    
    function exportToCSV() {
        const table = document.getElementById('equipmentTable');
        const rows = table.querySelectorAll('tr');
        let csv = [];
        
        // Get headers (excluding Action column)
        const headers = [];
        const headerCells = rows[0].querySelectorAll('th');
        for (let i = 0; i < headerCells.length - 1; i++) {
            headers.push(headerCells[i].textContent);
        }
        csv.push(headers.join(','));
        
        // Get data rows (excluding Action column and status details)
        for (let i = 1; i < rows.length; i++) {
            const cells = rows[i].querySelectorAll('td');
            const row = [];
            for (let j = 0; j < cells.length - 1; j++) {
                // For status column, get only the main status text, not the details
                if (j === 7) { // Status column (now moved to position 7)
                    const statusElement = cells[j].querySelector('span[class^="status-"]');
                    const statusText = statusElement ? statusElement.textContent : cells[j].textContent;
                    row.push('"' + statusText.replace(/"/g, '""') + '"');
                } else {
                    row.push('"' + cells[j].textContent.replace(/"/g, '""') + '"');
                }
            }
            csv.push(row.join(','));
        }
        
        // Download CSV
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `deployment_${<?= json_encode($ics_par_no) ?>}_${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>