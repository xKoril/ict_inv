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
    body { font-family: Arial, sans-serif; margin: 20px; font-size: 0.9em; }
    .back-link { color: #0056b3; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    h1, h2 { margin: 24px 0 8px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #f4f4f4; }
    .pagination a { margin-right: 8px; color: #0056b3; text-decoration: none; }
    .pagination span { margin-right: 8px; }
    .pagination .disabled { color: #aaa; cursor: default; text-decoration: none; }
    .box { margin-bottom: 24px; }
  </style>
</head>
<body>
  <a href="index.php" class="back-link">← Back to Inventory</a>
  <h1>Deployment Dashboard</h1>
  <button onclick="location.href='deploy_equipment.php'">➕ Create Deployment Form</button>

  <!-- ICS/PAR Summary -->
  <div class="box">
    <h2>ICS/PAR Summary</h2>
    <form method="get">
      <input type="hidden" name="page_d" value="<?= $page_d ?>">
      <input type="hidden" name="page_a" value="<?= $page_a ?>">
      <label>ICS/PAR No.: <input type="text" name="s_ics" value="<?= htmlspecialchars($s_ics) ?>"></label>
      <label>Custodian: <input type="text" name="s_cust" value="<?= htmlspecialchars($s_cust) ?>"></label>
      <label>Office:
        <select name="s_off">
          <option value="">--All--</option>
          <?php foreach ($offices as $o): ?>
            <option value="<?= $o ?>"<?= $o === $s_off ? ' selected' : '' ?>><?= $o ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit">Filter</button>
      <a href="deployment.php" style="margin-left:8px;">Clear</a>
    </form>
    <table>
      <tr>
        <th>ICS/PAR No.</th>
        <th>Custodian</th>
        <th>Office</th>
        <th>Date Deployed</th>
        <th>Total</th>
        <th>Action</th>
      </tr>
      <?php foreach ($summary as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['ics_par_no']) ?></td>
        <td><?= htmlspecialchars($row['custodian']) ?></td>
        <td><?= htmlspecialchars($row['office_custodian']) ?></td>
        <td><?= $row['date_deployed'] ?></td>
        <td><?= $row['total_equipment'] ?></td>
        <td><button onclick="viewDeploy('<?= $row['ics_par_no'] ?>')">View Equipment</button></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="pagination">
      <?php if ($page_s > 1): ?>
        <a href="?page_s=<?= $page_s - 1 ?>&page_d=<?= $page_d ?>&page_a=<?= $page_a ?>">← Prev</a>
      <?php else: ?>
        <span class="disabled">← Prev</span>
      <?php endif; ?>
      <span>Page <?= $page_s ?></span>
      <?php if (count($summary) === $limit): ?>
        <a href="?page_s=<?= $page_s + 1 ?>&page_d=<?= $page_d ?>&page_a=<?= $page_a ?>">Next →</a>
      <?php else: ?>
        <span class="disabled">Next →</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Deployed Equipment -->
  <div class="box">
    <h2>Deployed Equipment</h2>
    <form method="get">
      <input type="hidden" name="page_s" value="<?= $page_s ?>">
      <input type="hidden" name="page_a" value="<?= $page_a ?>">
      <label>ICS/PAR No.: <input type="text" name="d_ics" value="<?= htmlspecialchars($d_ics) ?>"></label>
      <label>Type: <input type="text" name="d_type" value="<?= htmlspecialchars($d_type) ?>"></label>
      <label>Custodian: <input type="text" name="d_cust" value="<?= htmlspecialchars($d_cust) ?>"></label>
      <label>Office:
        <select name="d_off">
          <option value="">--All--</option>
          <?php foreach ($offices as $o): ?>
            <option value="<?= $o ?>"<?= $o === $d_off ? ' selected' : '' ?>><?= $o ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit">Filter</button>
      <a href="deployment.php" style="margin-left:8px;">Clear</a>
    </form>
    <table>
      <tr>
        <th>ICS/PAR</th>
        <th>Custodian</th>
        <th>Office</th>
        <th>Date Deployed</th>
        <th>Type</th>
        <th>Brand</th>
        <th>Model</th>
        <th>Serial</th>
        <th>Action</th>
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
        <td><button onclick="openTransfer('<?= $row['ics_par_no'] ?>','<?= $row['serial_number'] ?>')">Transfer</button></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="pagination">
      <?php if ($page_d > 1): ?>
        <a href="?page_s=<?= $page_s ?>&page_d=<?= $page_d - 1 ?>&page_a=<?= $page_a ?>&d_ics=<?= urlencode($d_ics) ?>&d_type=<?= urlencode($d_type) ?>&d_cust=<?= urlencode($d_cust) ?>&d_off=<?= urlencode($d_off) ?>">← Prev</a>
      <?php else: ?>
        <span class="disabled">← Prev</span>
      <?php endif; ?>
      <span>Page <?= $page_d ?></span>
      <?php if (count($deployed) === $limit): ?>
        <a href="?page_s=<?= $page_s ?>&page_d=<?= $page_d + 1 ?>&page_a=<?= $page_a ?>&d_ics=<?= urlencode($d_ics) ?>&d_type=<?= urlencode($d_type) ?>&d_cust=<?= urlencode($d_cust) ?>&d_off=<?= urlencode($d_off) ?>">Next →</a>
      <?php else: ?>
        <span class="disabled">Next →</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Available Equipment -->
  <div class="box">
    <h2>Available Equipment</h2>
    <form method="get">
      <input type="hidden" name="page_s" value="<?= $page_s ?>">
      <input type="hidden" name="page_d" value="<?= $page_d ?>">
      <label>Type: <input type="text" name="a_type" value="<?= htmlspecialchars($a_type) ?>"></label>
      <label>Brand: <input type="text" name="a_brand" value="<?= htmlspecialchars($a_brand) ?>"></label>
      <label>Model: <input type="text" name="a_model" value="<?= htmlspecialchars($a_model) ?>"></label>
      <button type="submit">Filter</button>
      <a href="deployment.php" style="margin-left:8px;">Clear</a>
    </form>
    <table>
      <tr>
        <th>Type</th>
        <th>Brand</th>
        <th>Model</th>
        <th>Locator</th>
        <th>Serial</th>
        <th>Description</th>
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
    <div class="pagination">
      <?php if ($page_a > 1): ?>
        <a href="?page_s=<?= $page_s ?>&page_d=<?= $page_d ?>&page_a=<?= $page_a - 1 ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>">← Prev</a>
      <?php else: ?>
        <span class="disabled">← Prev</span>
      <?php endif; ?>
      <span>Page <?= $page_a ?></span>
      <?php if (count($available) === $limit): ?>
        <a href="?page_s=<?= $page_s ?>&page_d=<?= $page_d ?>&page_a=<?= $page_a + 1 ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>">Next →</a>
      <?php else: ?>
        <span class="disabled">Next →</span>
      <?php endif; ?>
    </div>
  </div>

    <!-- Modals and Scripts removed as requested -->
</body>
</html>
