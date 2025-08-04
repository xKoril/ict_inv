<?php
require 'db.php';

// Pagination settings
$page_s = max(1, (int)($_GET['page_s'] ?? 1)); // ICS/PAR summary page
$page_d = max(1, (int)($_GET['page_d'] ?? 1)); // deployed equipment page
$page_a = max(1, (int)($_GET['page_a'] ?? 1)); // available equipment page
$limit  = 10;
$offset_s = ($page_s - 1) * $limit;
$offset_d = ($page_d - 1) * $limit;
$offset_a = ($page_a - 1) * $limit;

// ICS/PAR Summary Query
$summary_sql = "
SELECT ics_par_no,
       custodian,
       office_custodian,
       date_deployed,
       COUNT(*) AS total_equipment
FROM deployment_transactions
GROUP BY ics_par_no, custodian, office_custodian, date_deployed
ORDER BY date_deployed DESC
LIMIT {$limit} OFFSET {$offset_s}";
$summary = $pdo->query($summary_sql)->fetchAll();

// Deployed Equipment Query with filters
$d_ics  = $_GET['d_ics']  ?? '';
$d_type = $_GET['d_type'] ?? '';
$d_cust = $_GET['d_cust'] ?? '';
$d_off  = $_GET['d_off']  ?? '';
$d_filters = [];
$d_params  = [];
if ($d_ics)  { $d_filters[] = "dt.ics_par_no = ?";        $d_params[] = $d_ics; }
if ($d_type) { $d_filters[] = "e.equipment_type LIKE ?";  $d_params[] = "%$d_type%"; }
if ($d_cust) { $d_filters[] = "dt.custodian LIKE ?";     $d_params[] = "%$d_cust%"; }
if ($d_off)  { $d_filters[] = "dt.office_custodian = ?";  $d_params[] = $d_off; }

$d_sql = "
SELECT dt.ics_par_no,
       dt.custodian,
       dt.office_custodian,
       e.equipment_type,
       e.brand,
       e.model,
       e.description_specification
FROM deployment_transactions dt
JOIN equipment e ON dt.equipment_id = e.equipment_id";
if ($d_filters) {
    $d_sql .= " WHERE " . implode(' AND ', $d_filters);
}
$d_sql .= " ORDER BY dt.date_deployed DESC LIMIT {$limit} OFFSET {$offset_d}";
$d_stmt = $pdo->prepare($d_sql);
$d_stmt->execute($d_params);
$deployed = $d_stmt->fetchAll();

// Available Equipment Query with filters
$a_type  = $_GET['a_type']  ?? '';
$a_brand = $_GET['a_brand'] ?? '';
$a_model = $_GET['a_model'] ?? '';
$a_filters = [];
$a_params  = [];
if ($a_type)  { $a_filters[] = "equipment_type LIKE ?"; $a_params[] = "%$a_type%"; }
if ($a_brand) { $a_filters[] = "brand LIKE ?";          $a_params[] = "%$a_brand%"; }
if ($a_model) { $a_filters[] = "model LIKE ?";          $a_params[] = "%$a_model%"; }

$a_sql = "
SELECT equipment_type, brand, model, locator, serial_number, description_specification
FROM equipment
WHERE equipment_status = 'Available for Deployment'";
if ($a_filters) {
    $a_sql .= " AND " . implode(' AND ', $a_filters);
}
$a_sql .= " ORDER BY equipment_type, brand LIMIT {$limit} OFFSET {$offset_a}";
$a_stmt = $pdo->prepare($a_sql);
$a_stmt->execute($a_params);
$available = $a_stmt->fetchAll();

// Offices list for filters
$offices = ['DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo','DTI-Negros Occ','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD','DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Deployment Dashboard</title>
  <style>
    body { font-family:Arial,sans-serif; margin:20px; font-size:0.9em; }
    .back-link { display:inline-block; margin-bottom:12px; color:#0056b3; text-decoration:none; }
    .back-link:hover { text-decoration:underline; }
    h1,h2 { margin-top:24px; margin-bottom:8px; }
    .box { border:1px solid #ccc; padding:16px; margin-bottom:24px; border-radius:4px; background:#fafafa; }
    table { width:100%; border-collapse:collapse; margin-bottom:12px; }
    th,td { border:1px solid #ccc; padding:8px; text-align:left; }
    th { background:#f4f4f4; }
    form label { margin-right:12px; }
    input, select, button { padding:6px; }
    .pagination a { margin-right:8px; text-decoration:none; color:#0056b3; }
    .pagination span { margin-right:8px; }
    .modal { display:none; position:fixed; top:10%; left:10%; width:80%; background:#fff; padding:20px; border:1px solid #ccc; z-index:1000; }
  </style>
</head>
<body>
  <a href="index.php" class="back-link">← Back to Inventory</a>
  <h1>Deployment Dashboard</h1>
  <button onclick="location.href='deploy_equipment.php'">➕ Create Deployment Form</button>

  <div class="box">
    <h2>ICS/PAR Summary</h2>
    <table>
      <tr><th>ICS/PAR No.</th><th>Custodian</th><th>Office</th><th>Date Deployed</th><th>Action</th></tr>
      <?php foreach($summary as $row): ?>
      <tr>
        <td><?=htmlspecialchars($row['ics_par_no'])?></td>
        <td><?=htmlspecialchars($row['custodian'])?></td>
        <td><?=htmlspecialchars($row['office_custodian'])?></td>
        <td><?=htmlspecialchars($row['date_deployed'])?></td>
        <td><button onclick="viewDeploy('<?=htmlspecialchars($row['ics_par_no'])?>')">View Equipment</button></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="pagination">
      <?php if($page_s>1): ?><a href="?page_s=<?=$page_s-1?>">← Prev</a><?php endif; ?>
      <span>Page <?=$page_s?></span>
      <?php if(count($summary)==$limit): ?><a href="?page_s=<?=$page_s+1?>">Next →</a><?php endif; ?>
    </div>
  </div>

  <div class="box">
    <h2>Deployed Equipment</h2>
    <form method="get">
      <input type="hidden" name="page_s" value="<?=$page_s?>">
      <input type="hidden" name="page_a" value="<?=$page_a?>">
      <label>ICS/PAR:<input type="text" name="d_ics" value="<?=htmlspecialchars($d_ics)?>"></label>
      <label>Type:<input type="text" name="d_type" value="<?=htmlspecialchars($d_type)?>"></label>
      <label>Custodian:<input type="text" name="d_cust" value="<?=htmlspecialchars($d_cust)?>"></label>
      <label>Office:<select name="d_off"><option value="">--All--</option><?php foreach($offices as $o): ?><option value="<?=htmlspecialchars($o)?>" <?= $o==$d_off?'selected':''; ?>><?=htmlspecialchars($o)?></option><?php endforeach; ?></select></label>
      <button type="submit">Filter</button>
    </form>
    <table>
      <tr><th>ICS/PAR</th><th>Custodian</th><th>Office</th><th>Type</th><th>Brand</th><th>Model</th><th>Description</th></tr>
      <?php foreach($deployed as $row): ?>
      <tr>
        <td><?=htmlspecialchars($row['ics_par_no'])?></td>
        <td><?=htmlspecialchars($row['custodian'])?></td>
        <td><?=htmlspecialchars($row['office_custodian'])?></td>
        <td><?=htmlspecialchars($row['equipment_type'])?></td>
        <td><?=htmlspecialchars($row['brand'])?></td>
        <td><?=htmlspecialchars($row['model'])?></td>
        <td><?=htmlspecialchars($row['description_specification'])?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="pagination">
      <?php if($page_d>1): ?><a href="?page_s=<?=$page_s?>&page_d=<?=$page_d-1?>&d_ics=<?=urlencode($d_ics)?>&d_type=<?=urlencode($d_type)?>&d_cust=<?=urlencode($d_cust)?>&d_off=<?=urlencode($d_off)?>">← Prev</a><?php endif; ?>
      <span>Page <?=$page_d?></span>
      <?php if(count($deployed)==$limit): ?><a href="?page_s=<?=$page_s?>&page_d=<?=$page_d+1?>&d_ics=<?=urlencode($d_ics)?>&d_type=<?=urlencode($d_type)?>&d_cust=<?=urlencode($d_cust)?>&d_off=<?=urlencode($d_off)?>">Next →</a><?php endif; ?>
    </div>
  </div>

  <div class="box">
    <h2>Available Equipment</h2>
    <form method="get">
      <input type="hidden" name="page_s" value="<?=$page_s?>">
      <input type="hidden" name="page_d" value="<?=$page_d?>">
      <label>Type:<input type="text" name="a_type" value="<?=htmlspecialchars($a_type)?>"></label>
      <label>Brand:<input type="text" name="a_brand" value="<?=htmlspecialchars($a_brand)?>"></label>
      <label>Model:<input type="text" name="a_model" value="<?=htmlspecialchars($a_model)?>"></label>
      <button type="submit">Filter</button>
    </form>
    <table>
      <tr><th>Type</th><th>Brand</th><th>Model</th><th>Locator</th><th>Serial</th><th>Description</th></tr>
      <?php foreach($available as $row): ?>
      <tr>
        <td><?=htmlspecialchars($row['equipment_type'])?></td>
        <td><?=htmlspecialchars($row['brand'])?></td>
        <td><?=htmlspecialchars($row['model'])?></td>
        <td><?=htmlspecialchars($row['locator'])?></td>
        <td><?=htmlspecialchars($row['serial_number'])?></td>
        <td><?=htmlspecialchars($row['description_specification'])?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="pagination">
      <?php if($page_a>1): ?><a href="?page_s=<?=$page_s?>&page_d=<?=$page_d?>&page_a=<?=$page_a-1?>&a_type=<?=urlencode($a_type)?>&a_brand=<?=urlencode($a_brand)?>&a_model=<?=urlencode($a_model)?>">← Prev</a><?php endif; ?>
      <span>Page <?=$page_a?></span>
      <?php if(count($available)==$limit): ?><a href="?page_s=<?=$page_s?>&page_d=<?=$page_d?>&page_a=<?=$page_a+1?>&a_type=<?=urlencode($a_type)?>&a_brand=<?=urlencode($a_brand)?>&a_model=<?=urlencode($a_model)?>">Next →</a><?php endif; ?>
    </div>
  </div>

  <!-- Modal for Viewing Equipment under an ICS/PAR -->
  <div id="deployModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('deployModal').style.display='none'">&times;</span>
      <h3>Equipment for ICS/PAR <span id="modalICS"></span></h3>
      <div id="modalDeployContent">Loading...</div>
    </div>
  </div>

  <script>
    function viewDeploy(ics) {
      document.getElementById('modalICS').innerText = ics;
      document.getElementById('modalDeployContent').innerHTML = 'Loading...';
      document.getElementById('deployModal').style.display = 'block';
      fetch('get_deployed_equipment.php?ics=' + encodeURIComponent(ics))
        .then(res => res.text())
        .then(html => document.getElementById('modalDeployContent').innerHTML = html);
    }
  </script>
</body>
</html>
