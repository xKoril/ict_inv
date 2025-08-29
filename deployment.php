<?php
require 'db.php';

// Pagination settings
$page_s = max(1, (int)($_GET['page_s'] ?? 1)); // ICS/PAR summary page
$page_d = max(1, (int)($_GET['page_d'] ?? 1)); // Deployed equipment page
$page_a = max(1, (int)($_GET['page_a'] ?? 1)); // Available equipment page
$limit   = 10;
$offset_s = ($page_s - 1) * $limit;
$offset_d = ($page_d - 1) * $limit;
$offset_a = ($page_a - 1) * $limit;

// Offices list for filters
$offices = [
    'DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo',
    'DTI-Negros Occ','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD',
    'DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'
];

// === ICS/PAR Summary ===
$s_ics  = $_GET['s_ics']  ?? '';
$s_cust = $_GET['s_cust'] ?? '';
$s_off  = $_GET['s_off']  ?? '';
$s_filters = [];
$s_params  = [];
if ($s_ics)  { $s_filters[] = "ics_par_no LIKE ?";    $s_params[] = "%$s_ics%"; }
if ($s_cust) { $s_filters[] = "custodian LIKE ?";     $s_params[] = "%$s_cust%"; }
if ($s_off)  { $s_filters[] = "office_custodian = ?"; $s_params[] = $s_off; }

// Get total count for summary
$summary_count_sql = "SELECT COUNT(DISTINCT CONCAT(ics_par_no, custodian, office_custodian, date_deployed)) AS total
FROM deployment_transactions";
if ($s_filters) {
    $summary_count_sql .= " WHERE " . implode(' AND ', $s_filters);
}
$summary_count_stmt = $pdo->prepare($summary_count_sql);
$summary_count_stmt->execute($s_params);
$summary_total = $summary_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$summary_sql = "SELECT ics_par_no, custodian, office_custodian, date_deployed, COUNT(*) AS total_equipment
FROM deployment_transactions";
if ($s_filters) {
    $summary_sql .= " WHERE " . implode(' AND ', $s_filters);
}
$summary_sql .= " GROUP BY ics_par_no, custodian, office_custodian, date_deployed
ORDER BY date_deployed DESC
LIMIT {$limit} OFFSET {$offset_s}";
$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute($s_params);
$summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);

// === Deployed Equipment ===
$d_ics  = $_GET['d_ics']  ?? '';
$d_type = $_GET['d_type'] ?? '';
$d_cust = $_GET['d_cust'] ?? '';
$d_off  = $_GET['d_off']  ?? '';

// build filters
$d_filters = [];
$d_params  = [];
if ($d_ics)  { $d_filters[] = "dt.ics_par_no LIKE ?";    $d_params[] = "%$d_ics%"; }
if ($d_type) { $d_filters[] = "e.equipment_type LIKE ?"; $d_params[] = "%$d_type%"; }
if ($d_cust) { $d_filters[] = "dt.custodian LIKE ?";     $d_params[] = "%$d_cust%"; }
if ($d_off)  { $d_filters[] = "dt.office_custodian = ?"; $d_params[] = $d_off; }

// Get total count for deployed equipment
$deployed_count_sql = "SELECT COUNT(*) AS total
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
          WHERE e.equipment_status = 'Deployed'";
if ($d_filters) {
    $deployed_count_sql .= " AND " . implode(' AND ', $d_filters);
}
$deployed_count_stmt = $pdo->prepare($deployed_count_sql);
$deployed_count_stmt->execute($d_params);
$deployed_total = $deployed_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// query based on equipment table but only latest deployment per item
$d_sql = "SELECT e.equipment_type, e.brand, e.model, e.serial_number,
                 dt.ics_par_no, dt.custodian, dt.office_custodian, dt.date_deployed
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
          WHERE e.equipment_status = 'Deployed'";
if ($d_filters) {
    $d_sql .= " AND " . implode(' AND ', $d_filters);
}
$d_sql .= " ORDER BY dt.date_deployed DESC
LIMIT {$limit} OFFSET {$offset_d}";
$d_stmt = $pdo->prepare($d_sql);
$d_stmt->execute($d_params);
$deployed = $d_stmt->fetchAll(PDO::FETCH_ASSOC);

// === Available Equipment ===
$a_type  = $_GET['a_type']  ?? '';
$a_brand = $_GET['a_brand'] ?? '';
$a_model = $_GET['a_model'] ?? '';
$a_filters = [];
$a_params  = [];
if ($a_type)  { $a_filters[] = "equipment_type LIKE ?"; $a_params[] = "%$a_type%"; }
if ($a_brand) { $a_filters[] = "brand LIKE ?";          $a_params[] = "%$a_brand%"; }
if ($a_model) { $a_filters[] = "model LIKE ?";          $a_params[] = "%$a_model%"; }

// Get total count for available equipment
$available_count_sql = "SELECT COUNT(*) AS total
FROM equipment
WHERE equipment_status = 'Available for Deployment'";
if ($a_filters) {
    $available_count_sql .= " AND " . implode(' AND ', $a_filters);
}
$available_count_stmt = $pdo->prepare($available_count_sql);
$available_count_stmt->execute($a_params);
$available_total = $available_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$a_sql = "SELECT equipment_type, brand, model, locator, serial_number, description_specification
FROM equipment
WHERE equipment_status = 'Available for Deployment'";
if ($a_filters) {
    $a_sql .= " AND " . implode(' AND ', $a_filters);
}
$a_sql .= " ORDER BY model ASC
LIMIT {$limit} OFFSET {$offset_a}";
$a_stmt = $pdo->prepare($a_sql);
$a_stmt->execute($a_params);
$available = $a_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Deployment Dashboard</title>
  <style>
    * {
      box-sizing: border-box;
    }
    
    body { 
      font-family: 'Segoe UI', Arial, sans-serif; 
      margin: 0;
      padding: 20px;
      font-size: 0.9em; 
      background-color: #f8f9fa;
      line-height: 1.4;
    }
    
    .container {
      max-width: 1400px;
      margin: 0 auto;
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .back-link { 
      color: #0056b3; 
      text-decoration: none; 
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      margin-bottom: 20px;
    }
    .back-link:hover { text-decoration: underline; }
    
    h1 { 
      margin: 0 0 20px; 
      color: #2c3e50;
      font-size: 2em;
    }
    
    h2 { 
      margin: 32px 0 16px; 
      color: #34495e;
      font-size: 1.3em;
      border-bottom: 2px solid #3498db;
      padding-bottom: 8px;
    }
    
    .btn-primary {
      background: #3498db;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 0.9em;
      margin-bottom: 20px;
      text-decoration: none;
      display: inline-block;
    }
    
    .btn-primary:hover {
      background: #2980b9;
    }
    
    .box { 
      margin-bottom: 40px; 
      background: #fff;
      border: 1px solid #e0e6ed;
      border-radius: 8px;
      overflow: hidden;
    }
    
    .box-header {
      background: #f8f9fa;
      padding: 20px;
      border-bottom: 1px solid #e0e6ed;
    }
    
    .filter-form {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      align-items: end;
      margin-bottom: 0;
    }
    
    .filter-group {
      display: flex;
      flex-direction: column;
      min-width: 120px;
    }
    
    .filter-form label {
      font-weight: 500;
      margin-bottom: 5px;
      color: #555;
      font-size: 0.85em;
    }
    
    .filter-form input,
    .filter-form select {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 0.9em;
    }
    
    .filter-form button {
      padding: 8px 16px;
      background: #28a745;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.9em;
      height: fit-content;
    }
    
    .filter-form button:hover {
      background: #218838;
    }
    
    .filter-form a {
      color: #6c757d;
      text-decoration: none;
      padding: 8px 12px;
      height: fit-content;
    }
    
    .table-container {
      overflow-x: auto;
      max-height: 500px;
      overflow-y: auto;
      border: 1px solid #e0e6ed;
      border-radius: 0 0 8px 8px;
    }
    
    table { 
      width: 100%; 
      border-collapse: collapse; 
      margin: 0;
      min-width: 800px;
    }
    
    th, td { 
      border: 1px solid #e0e6ed; 
      padding: 12px 8px; 
      text-align: left; 
      vertical-align: middle;
    }
    
    th { 
      background: #f8f9fa; 
      font-weight: 600;
      position: sticky;
      top: 0;
      z-index: 10;
      color: #2c3e50;
      font-size: 0.85em;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      cursor: pointer;
      user-select: none;
      transition: background-color 0.2s ease;
      position: relative;
    }
    
    th:hover {
      background: #e9ecef;
    }
    
    th.sortable::after {
      content: ' ↕';
      opacity: 0.5;
      font-size: 0.8em;
      margin-left: 5px;
    }
    
    th.sort-asc::after {
      content: ' ↑';
      opacity: 1;
      color: #3498db;
    }
    
    th.sort-desc::after {
      content: ' ↓';
      opacity: 1;
      color: #3498db;
    }
    
    td {
      font-size: 0.9em;
    }
    
    tr:nth-child(even) {
      background-color: #f8f9fa;
    }
    
    tr:hover {
      background-color: #e3f2fd;
    }
    
    .action-btn {
      background: #17a2b8;
      color: white;
      border: none;
      padding: 6px 12px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.8em;
      margin: 2px;
    }
    
    .action-btn:hover {
      background: #138496;
    }
    
    .action-btn.transfer {
      background: #fd7e14;
    }
    
    .action-btn.transfer:hover {
      background: #e8650d;
    }
    
    .action-btn.return {
      background: #dc3545;
    }
    
    .action-btn.return:hover {
      background: #c82333;
    }
    
    .table-footer {
      padding: 15px 20px;
      background: #f8f9fa;
      border-top: 1px solid #e0e6ed;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }
    
    .records-info {
      color: #6c757d;
      font-size: 0.9em;
      font-weight: 500;
    }
    
    .pagination {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .pagination a,
    .pagination span {
      padding: 8px 12px;
      border: 1px solid #dee2e6;
      border-radius: 4px;
      text-decoration: none;
      color: #495057;
      font-size: 0.9em;
      display: flex;
      align-items: center;
      min-width: 40px;
      justify-content: center;
      transition: all 0.2s ease;
    }
    
    .pagination a:hover {
      background: #e9ecef;
      border-color: #adb5bd;
      transform: translateY(-1px);
    }
    
    .pagination .current {
      background: #3498db;
      color: white;
      border-color: #3498db;
      font-weight: 600;
    }
    
    .pagination .disabled {
      color: #adb5bd;
      cursor: not-allowed;
      background: #f8f9fa;
    }
    
    .pagination .disabled:hover {
      background: #f8f9fa;
      border-color: #dee2e6;
      transform: none;
    }
    
    @media (max-width: 768px) {
      body {
        padding: 10px;
      }
      
      .container {
        padding: 15px;
      }
      
      h1 {
        font-size: 1.5em;
      }
      
      .filter-form {
        flex-direction: column;
        align-items: stretch;
      }
      
      .filter-group {
        min-width: auto;
      }
      
      .table-footer {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
      }
      
      .pagination {
        justify-content: center;
      }
      
      th, td {
        padding: 8px 6px;
        font-size: 0.8em;
      }
      
      .table-container {
        max-height: 400px;
      }
    }
    
    @media (max-width: 480px) {
      .pagination a,
      .pagination span {
        padding: 6px 8px;
        font-size: 0.8em;
        min-width: 32px;
      }
      
      .action-btn {
        padding: 4px 8px;
        font-size: 0.75em;
      }
      
      .table-container {
        max-height: 350px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="index.php" class="back-link">← Back to Inventory</a>
    <h1>Deployment Dashboard</h1>
    <button class="btn-primary" onclick="location.href='deploy_equipment.php'">➕ Create Deployment Form</button>

    <!-- ICS/PAR Summary -->
    <div class="box">
      <div class="box-header">
        <h2 style="margin: 0 0 16px; border: none; padding: 0;">ICS/PAR Summary</h2>
        <form method="get" class="filter-form">
          <input type="hidden" name="page_d" value="<?= $page_d ?>">
          <input type="hidden" name="page_a" value="<?= $page_a ?>">
          <div class="filter-group">
            <label>ICS/PAR No.:</label>
            <input type="text" name="s_ics" value="<?= htmlspecialchars($s_ics) ?>">
          </div>
          <div class="filter-group">
            <label>Custodian:</label>
            <input type="text" name="s_cust" value="<?= htmlspecialchars($s_cust) ?>">
          </div>
          <div class="filter-group">
            <label>Office:</label>
            <select name="s_off">
              <option value="">--All--</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= $o ?>"<?= $o === $s_off ? ' selected' : '' ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit">Filter</button>
          <a href="deployment.php">Clear</a>
        </form>
      </div>
      
      <div class="table-container">
        <table id="summary-table">
          <tr>
            <th class="sortable" data-column="0">ICS/PAR No.</th>
            <th class="sortable" data-column="1">Custodian</th>
            <th class="sortable" data-column="2">Office</th>
            <th class="sortable" data-column="3">Date Deployed</th>
            <th class="sortable" data-column="4">Total</th>
            <th>Action</th>
          </tr>
          <?php foreach ($summary as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['ics_par_no']) ?></td>
            <td><?= htmlspecialchars($row['custodian']) ?></td>
            <td><?= htmlspecialchars($row['office_custodian']) ?></td>
            <td><?= $row['date_deployed'] ?></td>
            <td><?= $row['total_equipment'] ?></td>
            <td><button class="action-btn" onclick="viewDeploy('<?= $row['ics_par_no'] ?>')">View Equipment</button></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      
      <div class="table-footer">
        <div class="records-info">
          Showing <?= count($summary) ?> of <?= $summary_total ?> records
        </div>
        <div class="pagination">
          <?php if ($page_s > 1): ?>
            <?php 
            $prev_url = "?page_s=" . ($page_s - 1) . "&page_d=$page_d&page_a=$page_a";
            if ($s_ics) $prev_url .= "&s_ics=" . urlencode($s_ics);
            if ($s_cust) $prev_url .= "&s_cust=" . urlencode($s_cust);
            if ($s_off) $prev_url .= "&s_off=" . urlencode($s_off);
            ?>
            <a href="<?= $prev_url ?>">← Prev</a>
          <?php else: ?>
            <span class="disabled">← Prev</span>
          <?php endif; ?>
          
          <span class="current"><?= $page_s ?></span>
          
          <?php if (count($summary) === $limit && ($offset_s + $limit) < $summary_total): ?>
            <?php 
            $next_url = "?page_s=" . ($page_s + 1) . "&page_d=$page_d&page_a=$page_a";
            if ($s_ics) $next_url .= "&s_ics=" . urlencode($s_ics);
            if ($s_cust) $next_url .= "&s_cust=" . urlencode($s_cust);
            if ($s_off) $next_url .= "&s_off=" . urlencode($s_off);
            ?>
            <a href="<?= $next_url ?>">Next →</a>
          <?php else: ?>
            <span class="disabled">Next →</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Deployed Equipment -->
    <div class="box">
      <div class="box-header">
        <h2 style="margin: 0 0 16px; border: none; padding: 0;">Deployed Equipment</h2>
        <form method="get" class="filter-form">
          <input type="hidden" name="page_s" value="<?= $page_s ?>">
          <input type="hidden" name="page_a" value="<?= $page_a ?>">
          <div class="filter-group">
            <label>ICS/PAR No.:</label>
            <input type="text" name="d_ics" value="<?= htmlspecialchars($d_ics) ?>">
          </div>
          <div class="filter-group">
            <label>Type:</label>
            <input type="text" name="d_type" value="<?= htmlspecialchars($d_type) ?>">
          </div>
          <div class="filter-group">
            <label>Custodian:</label>
            <input type="text" name="d_cust" value="<?= htmlspecialchars($d_cust) ?>">
          </div>
          <div class="filter-group">
            <label>Office:</label>
            <select name="d_off">
              <option value="">--All--</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= $o ?>"<?= $o === $d_off ? ' selected' : '' ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit">Filter</button>
          <a href="deployment.php">Clear</a>
        </form>
      </div>
      
      <div class="table-container">
        <table id="deployed-table">
          <tr>
            <th class="sortable" data-column="0">ICS/PAR</th>
            <th class="sortable" data-column="1">Custodian</th>
            <th class="sortable" data-column="2">Office</th>
            <th class="sortable" data-column="3">Date Deployed</th>
            <th class="sortable" data-column="4">Type</th>
            <th class="sortable" data-column="5">Brand</th>
            <th class="sortable" data-column="6">Model</th>
            <th class="sortable" data-column="7">Serial</th>
            <th>Actions</th>
          </tr>
          <?php foreach ($deployed as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['ics_par_no']) ?></td>
            <td><?= htmlspecialchars($row['custodian']) ?></td>
            <td><?= htmlspecialchars($row['office_custodian']) ?></td>
            <td><?= $row['date_deployed'] ?></td>
            <td><?= htmlspecialchars($row['equipment_type']) ?></td>
            <td><?= htmlspecialchars($row['brand']) ?></td>
            <td><?= htmlspecialchars($row['model']) ?></td>
            <td><?= htmlspecialchars($row['serial_number']) ?></td>
            <td>
              <button class="action-btn transfer" onclick="openTransfer('<?= $row['ics_par_no'] ?>','<?= $row['serial_number'] ?>')">Transfer</button>
              <button class="action-btn return" onclick="returnEquipment('<?= $row['serial_number'] ?>')">Return</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      
      <div class="table-footer">
        <div class="records-info">
          Showing <?= count($deployed) ?> of <?= $deployed_total ?> records
        </div>
        <div class="pagination">
          <?php if ($page_d > 1): ?>
            <?php 
            $prev_url = "?page_s=$page_s&page_d=" . ($page_d - 1) . "&page_a=$page_a";
            if ($d_ics) $prev_url .= "&d_ics=" . urlencode($d_ics);
            if ($d_type) $prev_url .= "&d_type=" . urlencode($d_type);
            if ($d_cust) $prev_url .= "&d_cust=" . urlencode($d_cust);
            if ($d_off) $prev_url .= "&d_off=" . urlencode($d_off);
            ?>
            <a href="<?= $prev_url ?>">← Prev</a>
          <?php else: ?>
            <span class="disabled">← Prev</span>
          <?php endif; ?>
          
          <span class="current"><?= $page_d ?></span>
          
          <?php if (count($deployed) === $limit && ($offset_d + $limit) < $deployed_total): ?>
            <?php 
            $next_url = "?page_s=$page_s&page_d=" . ($page_d + 1) . "&page_a=$page_a";
            if ($d_ics) $next_url .= "&d_ics=" . urlencode($d_ics);
            if ($d_type) $next_url .= "&d_type=" . urlencode($d_type);
            if ($d_cust) $next_url .= "&d_cust=" . urlencode($d_cust);
            if ($d_off) $next_url .= "&d_off=" . urlencode($d_off);
            ?>
            <a href="<?= $next_url ?>">Next →</a>
          <?php else: ?>
            <span class="disabled">Next →</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Available Equipment -->
    <div class="box">
      <div class="box-header">
        <h2 style="margin: 0 0 16px; border: none; padding: 0;">Available Equipment</h2>
        <form method="get" class="filter-form">
          <input type="hidden" name="page_s" value="<?= $page_s ?>">
          <input type="hidden" name="page_d" value="<?= $page_d ?>">
          <div class="filter-group">
            <label>Type:</label>
            <input type="text" name="a_type" value="<?= htmlspecialchars($a_type) ?>">
          </div>
          <div class="filter-group">
            <label>Brand:</label>
            <input type="text" name="a_brand" value="<?= htmlspecialchars($a_brand) ?>">
          </div>
          <div class="filter-group">
            <label>Model:</label>
            <input type="text" name="a_model" value="<?= htmlspecialchars($a_model) ?>">
          </div>
          <button type="submit">Filter</button>
          <a href="deployment.php">Clear</a>
        </form>
      </div>
      
      <div class="table-container">
        <table id="available-table">
          <tr>
            <th class="sortable" data-column="0">Type</th>
            <th class="sortable" data-column="1">Brand</th>
            <th class="sortable" data-column="2">Model</th>
            <th class="sortable" data-column="3">Locator</th>
            <th class="sortable" data-column="4">Serial</th>
            <th class="sortable" data-column="5">Description</th>
          </tr>
          <?php foreach ($available as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['equipment_type']) ?></td>
            <td><?= htmlspecialchars($row['brand']) ?></td>
            <td><?= htmlspecialchars($row['model']) ?></td>
            <td><?= htmlspecialchars($row['locator']) ?></td>
            <td><?= htmlspecialchars($row['serial_number']) ?></td>
            <td><?= htmlspecialchars($row['description_specification']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      
      <div class="table-footer">
        <div class="records-info">
          Showing <?= count($available) ?> of <?= $available_total ?> records
        </div>
        <div class="pagination">
          <?php if ($page_a > 1): ?>
            <?php 
            $prev_url = "?page_s=$page_s&page_d=$page_d&page_a=" . ($page_a - 1);
            if ($a_type) $prev_url .= "&a_type=" . urlencode($a_type);
            if ($a_brand) $prev_url .= "&a_brand=" . urlencode($a_brand);
            if ($a_model) $prev_url .= "&a_model=" . urlencode($a_model);
            ?>
            <a href="<?= $prev_url ?>">← Prev</a>
          <?php else: ?>
            <span class="disabled">← Prev</span>
          <?php endif; ?>
          
          <span class="current"><?= $page_a ?></span>
          
          <?php if (count($available) === $limit && ($offset_a + $limit) < $available_total): ?>
            <?php 
            $next_url = "?page_s=$page_s&page_d=$page_d&page_a=" . ($page_a + 1);
            if ($a_type) $next_url .= "&a_type=" . urlencode($a_type);
            if ($a_brand) $next_url .= "&a_brand=" . urlencode($a_brand);
            if ($a_model) $next_url .= "&a_model=" . urlencode($a_model);
            ?>
            <a href="<?= $next_url ?>">Next →</a>
          <?php else: ?>
            <span class="disabled">Next →</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript Functions -->
  <script>
    function viewDeploy(icsParNo) {
        window.location.href = `view_deployment.php?ics_par_no=${encodeURIComponent(icsParNo)}`;
    }

    function openTransfer(icsParNo, serialNumber) {
        window.location.href = `transfer_equipment.php?ics_par_no=${encodeURIComponent(icsParNo)}&serial_number=${encodeURIComponent(serialNumber)}`;
    }

    function returnEquipment(serialNumber) {
        if (confirm('Are you sure you want to return this equipment to inventory?')) {
            window.location.href = `return_equipment.php?serial_number=${encodeURIComponent(serialNumber)}`;
        }
    }

    // Table sorting functionality
    class TableSorter {
        constructor(tableId) {
            this.table = document.getElementById(tableId);
            this.headers = this.table.querySelectorAll('th.sortable');
            this.tbody = this.table.querySelector('tbody') || this.table; // Handle tables without tbody
            this.rows = Array.from(this.tbody.querySelectorAll('tr')).slice(1); // Skip header row
            this.currentSort = null;
            this.currentDirection = 'asc';
            
            this.init();
        }

        init() {
            this.headers.forEach((header, index) => {
                header.addEventListener('click', () => {
                    this.sortTable(index, header);
                });
            });
        }

        sortTable(columnIndex, header) {
            // Reset all headers
            this.headers.forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
            });

            // Determine sort direction
            if (this.currentSort === columnIndex) {
                this.currentDirection = this.currentDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.currentDirection = 'asc';
            }

            this.currentSort = columnIndex;

            // Add appropriate class to current header
            header.classList.add(this.currentDirection === 'asc' ? 'sort-asc' : 'sort-desc');

            // Sort rows
            this.rows.sort((a, b) => {
                const aVal = this.getCellValue(a, columnIndex);
                const bVal = this.getCellValue(b, columnIndex);
                
                let result = this.compareValues(aVal, bVal);
                
                return this.currentDirection === 'asc' ? result : -result;
            });

            // Re-append sorted rows
            this.rows.forEach(row => {
                this.tbody.appendChild(row);
            });
        }

        getCellValue(row, columnIndex) {
            const cell = row.cells[columnIndex];
            if (!cell) return '';
            
            let text = cell.textContent || cell.innerText || '';
            text = text.trim();
            
            // Check if it's a number
            if (!isNaN(text) && text !== '') {
                return parseFloat(text);
            }
            
            // Check if it's a date (YYYY-MM-DD format)
            if (/^\d{4}-\d{2}-\d{2}/.test(text)) {
                return new Date(text);
            }
            
            return text.toLowerCase();
        }

        compareValues(a, b) {
            if (a instanceof Date && b instanceof Date) {
                return a.getTime() - b.getTime();
            }
            
            if (typeof a === 'number' && typeof b === 'number') {
                return a - b;
            }
            
            if (typeof a === 'string' && typeof b === 'string') {
                return a.localeCompare(b);
            }
            
            return 0;
        }
    }

    // Initialize sorting for all tables when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize sorters for each table
        const tables = ['summary-table', 'deployed-table', 'available-table'];
        
        tables.forEach(tableId => {
            const table = document.getElementById(tableId);
            if (table) {
                new TableSorter(tableId);
            }
        });
    });
  </script>
</body>
</html>