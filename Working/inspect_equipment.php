<?php
require 'db.php';

// inspect_equipment.php
// Display equipment details, deployment history, and borrow history for a specific equipment

// Validate and fetch equipment_id as integer
$equipment_id = filter_input(INPUT_GET, 'equipment_id', FILTER_VALIDATE_INT);
if ($equipment_id === null || $equipment_id === false) {
    echo '<p>Invalid or missing equipment ID.</p>';
    exit;
}

// Fetch equipment details with all required fields
$stmt = $pdo->prepare(
    "SELECT
        equipment_id,
        po_no,
        equipment_category,
        equipment_status,
        equipment_type,
        brand,
        model,
        description_specification,
        serial_number,
        inventory_item_no_property_no AS inventory_property_no,
        date_acquired,
        fund_source,
        amount_unit_cost AS unit_cost,
        locator AS location
     FROM equipment
     WHERE equipment_id = ?"
);
$stmt->execute([$equipment_id]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$equipment) {
    echo '<p>Equipment not found.</p>';
    exit;
}

// Fetch deployment history
$deployStmt = $pdo->prepare(
    "SELECT
        ics_par_no,
        date_deployed,
        time_deployed,
        custodian,
        office_custodian AS office,
        remarks
     FROM deployment_transactions
     WHERE equipment_id = ?
     ORDER BY date_deployed DESC, time_deployed DESC"
);
$deployStmt->execute([$equipment_id]);
$deployHistory = $deployStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch borrow history
$borrowStmt = $pdo->prepare(
    "SELECT
        slip_no,
        borrower,
        office_borrower AS office,
        date_borrowed,
        time_borrowed,
        purpose,
        due_date,
        date_returned,
        equipment_returned_status AS return_status
     FROM borrow_transactions
     WHERE equipment_id = ?
     ORDER BY date_borrowed DESC, time_borrowed DESC"
);
$borrowStmt->execute([$equipment_id]);
$borrowHistory = $borrowStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Inspect Equipment #<?=htmlspecialchars($equipment['equipment_id'])?></title>
  <style>
    body { 
      font-family: Arial, sans-serif; 
      margin: 20px; 
      background-color: #f5f5f5;
    }
    .back-link { 
      text-decoration: none; 
      color: #0056b3; 
      margin-bottom: 16px; 
      display: inline-block;
      padding: 8px 12px;
      background: white;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-weight: bold;
    }
    .back-link:hover {
      background: #f0f0f0;
      text-decoration: underline;
    }
    .container { 
      display: flex; 
      flex-direction: column; 
      gap: 24px; 
      max-width: 1200px;
    }
    .box { 
      border: 1px solid #ccc; 
      padding: 20px; 
      border-radius: 8px; 
      background: white;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    h1 { 
      margin-bottom: 8px; 
      color: #333;
      border-bottom: 3px solid #0056b3;
      padding-bottom: 10px;
    }
    h2 {
      margin-top: 0;
      margin-bottom: 16px;
      color: #444;
      font-size: 1.4em;
      border-bottom: 2px solid #e0e0e0;
      padding-bottom: 8px;
    }
    table { 
      width: 100%; 
      border-collapse: collapse; 
      margin-top: 8px; 
    }
    th, td { 
      border: 1px solid #ddd; 
      padding: 12px 8px; 
      text-align: left; 
      vertical-align: top;
    }
    th { 
      background: #f8f9fa; 
      font-weight: bold;
      color: #555;
    }
    .details-table th {
      width: 25%;
      background: #e9ecef;
    }
    .details-table td {
      background: white;
    }
    .no-data {
      text-align: center;
      padding: 20px;
      color: #666;
      font-style: italic;
      background: #f8f9fa;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
    }
    .history-table th {
      background: #007bff;
      color: white;
    }
    .history-table tr:nth-child(even) {
      background: #f8f9fa;
    }
    .history-table tr:hover {
      background: #e7f3ff;
    }
    .status-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.9em;
      font-weight: bold;
    }
    .status-deployed { background: #d4edda; color: #155724; }
    .status-available { background: #d1ecf1; color: #0c5460; }
    .status-maintenance { background: #fff3cd; color: #856404; }
    .status-disposed { background: #f8d7da; color: #721c24; }
  </style>
</head>
<body>
  <a href="index.php" class="back-link">← Back to Inventory</a>
  <h1>🔍 Inspect Equipment #<?=htmlspecialchars($equipment['equipment_id'])?></h1>
  
  <div class="container">
    <!-- Equipment Details -->
    <div class="box">
      <h2>📋 Equipment Information</h2>
      <table class="details-table">
        <tr><th>Equipment ID</th><td><?=htmlspecialchars($equipment['equipment_id'])?></td></tr>
        <tr><th>PO No.</th><td><?=htmlspecialchars($equipment['po_no'] ?: 'N/A')?></td></tr>
        <tr><th>Equipment Category</th><td><?=htmlspecialchars($equipment['equipment_category'] ?: 'N/A')?></td></tr>
        <tr><th>Status</th><td>
          <span class="status-badge status-<?=strtolower(str_replace(' ', '-', $equipment['equipment_status']))?>">
            <?=htmlspecialchars($equipment['equipment_status'] ?: 'N/A')?>
          </span>
        </td></tr>
        <tr><th>Equipment Type</th><td><?=htmlspecialchars($equipment['equipment_type'] ?: 'N/A')?></td></tr>
        <tr><th>Brand</th><td><?=htmlspecialchars($equipment['brand'] ?: 'N/A')?></td></tr>
        <tr><th>Model</th><td><?=htmlspecialchars($equipment['model'] ?: 'N/A')?></td></tr>
        <tr><th>Description</th><td><?=htmlspecialchars($equipment['description_specification'] ?: 'N/A')?></td></tr>
        <tr><th>Serial Number</th><td><?=htmlspecialchars($equipment['serial_number'] ?: 'N/A')?></td></tr>
        <tr><th>Inventory/Property No.</th><td><?=htmlspecialchars($equipment['inventory_property_no'] ?: 'N/A')?></td></tr>
        <tr><th>Date Acquired</th><td><?=htmlspecialchars($equipment['date_acquired'] ?: 'N/A')?></td></tr>
        <tr><th>Fund Source</th><td><?=htmlspecialchars($equipment['fund_source'] ?: 'N/A')?></td></tr>
        <tr><th>Unit Cost</th><td><?=htmlspecialchars($equipment['unit_cost'] ? '₱' . number_format($equipment['unit_cost'], 2) : 'N/A')?></td></tr>
        <tr><th>Location</th><td><?=htmlspecialchars($equipment['location'] ?: 'N/A')?></td></tr>
      </table>
    </div>

    <!-- Deployment History -->
    <div class="box">
      <h2>🚚 Deployment History</h2>
      <?php if (count($deployHistory) > 0): ?>
      <table class="history-table">
        <tr>
          <th>ICS/PAR No.</th>
          <th>Custodian</th>
          <th>Office</th>
          <th>Date Deployed</th>
          <th>Time Deployed</th>
          <th>Remarks</th>
        </tr>
        <?php foreach ($deployHistory as $d): ?>
        <tr>
          <td><?=htmlspecialchars($d['ics_par_no'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($d['custodian'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($d['office'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($d['date_deployed'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($d['time_deployed'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($d['remarks'] ?: 'No remarks')?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <div class="no-data">📝 No deployment history found for this equipment.</div>
      <?php endif; ?>
    </div>

    <!-- Borrow History -->
    <div class="box">
      <h2>📦 Borrow History</h2>
      <?php if (count($borrowHistory) > 0): ?>
      <table class="history-table">
        <tr>
          <th>Slip No.</th>
          <th>Borrower</th>
          <th>Office</th>
          <th>Date Borrowed</th>
          <th>Purpose</th>
          <th>Due Date</th>
          <th>Date Returned</th>
          <th>Return Status</th>
        </tr>
        <?php foreach ($borrowHistory as $b): ?>
        <tr>
          <td><?=htmlspecialchars($b['slip_no'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($b['borrower'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($b['office'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($b['date_borrowed'] ?: 'N/A')?> <?=htmlspecialchars($b['time_borrowed'] ?: '')?></td>
          <td><?=htmlspecialchars($b['purpose'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($b['due_date'] ?: 'N/A')?></td>
          <td><?=htmlspecialchars($b['date_returned'] ?: 'Not returned')?></td>
          <td><?=htmlspecialchars($b['return_status'] ?: 'N/A')?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <div class="no-data">📝 No borrow history found for this equipment.</div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>