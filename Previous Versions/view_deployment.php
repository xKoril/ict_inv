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

// Get all equipment for this ICS/PAR
$equipment_sql = "SELECT e.equipment_type, e.brand, e.model, e.serial_number, e.locator,
                         e.description_specification, dt.date_deployed, e.equipment_status
                  FROM equipment e
                  JOIN deployment_transactions dt ON e.equipment_id = dt.equipment_id
                  WHERE dt.ics_par_no = ?
                  ORDER BY dt.date_deployed DESC, e.equipment_type";
$equipment_stmt = $pdo->prepare($equipment_sql);
$equipment_stmt->execute([$ics_par_no]);
$equipment_list = $equipment_stmt->fetchAll(PDO::FETCH_ASSOC);
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
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        .status-deployed { color: #28a745; font-weight: bold; }
        .status-available { color: #007bff; font-weight: bold; }
        .status-returned { color: #6c757d; font-weight: bold; }
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
        <h2>ICS/PAR Information</h2>
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
    
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
        <button class="print-btn" onclick="exportToCSV()" style="background: #007bff;">📊 Export CSV</button>
    </div>
    
    <h2>Equipment List</h2>
    <table id="equipmentTable">
        <thead>
            <tr>
                <th>Type</th>
                <th>Brand</th>
                <th>Model</th>
                <th>Serial Number</th>
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
                <td><?= htmlspecialchars($item['locator']) ?></td>
                <td><?= htmlspecialchars($item['description_specification']) ?></td>
                <td>
                    <span class="status-<?= strtolower(str_replace(' ', '-', $item['equipment_status'])) ?>">
                        <?= htmlspecialchars($item['equipment_status']) ?>
                    </span>
                </td>
                <td class="no-print">
                    <?php if ($item['equipment_status'] === 'Deployed'): ?>
                        <button onclick="transferEquipment('<?= $deployment_info['ics_par_no'] ?>', '<?= $item['serial_number'] ?>')" 
                                style="background: #ffc107; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                            Transfer
                        </button>
                        <button onclick="returnEquipment('<?= $item['serial_number'] ?>')" 
                                style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-left: 5px;">
                            Return
                        </button>
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
        
        // Get data rows (excluding Action column)
        for (let i = 1; i < rows.length; i++) {
            const cells = rows[i].querySelectorAll('td');
            const row = [];
            for (let j = 0; j < cells.length - 1; j++) {
                row.push('"' + cells[j].textContent.replace(/"/g, '""') + '"');
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