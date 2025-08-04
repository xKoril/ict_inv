<?php
require 'db.php';

// Pagination settings
$page_s = max(1, (int)($_GET['page_s'] ?? 1)); // slip summary page
$page_b = max(1, (int)($_GET['page_b'] ?? 1)); // borrowed equipment page
$page_a = max(1, (int)($_GET['page_a'] ?? 1)); // available equipment page
$limit  = 10;
$offset_s = ($page_s - 1) * $limit;
$offset_b = ($page_b - 1) * $limit;
$offset_a = ($page_a - 1) * $limit;

// Slip Summary Query
$summary_sql = "
SELECT slip_no,
       borrower,
       office_borrower,
       purpose,
       date_borrowed,
       due_date,
       COUNT(*) AS total_equipment,
       SUM(CASE WHEN date_returned IS NOT NULL THEN 1 ELSE 0 END) AS returned_equipment
FROM borrow_transactions
GROUP BY slip_no, borrower, office_borrower, purpose, date_borrowed, due_date
ORDER BY date_borrowed DESC
LIMIT {$limit} OFFSET {$offset_s}"
;
$summary_stmt = $pdo->query($summary_sql);
$slip_summary  = $summary_stmt->fetchAll();

// Borrowed Equipment Query (with filters)
$b_type = $_GET['b_type'] ?? '';
$b_from = $_GET['b_from'] ?? '';
$b_to   = $_GET['b_to']   ?? '';
$b_off  = $_GET['b_off']  ?? '';
$b_filters = [];
$b_params  = [];
if ($b_type) { $b_filters[] = "e.equipment_type LIKE ?"; $b_params[] = "%$b_type%"; }
if ($b_from) { $b_filters[] = "bt.date_borrowed >= ?";  $b_params[] = $b_from; }
if ($b_to)   { $b_filters[] = "bt.date_borrowed <= ?";  $b_params[] = $b_to; }
if ($b_off)  { $b_filters[] = "bt.office_borrower = ?"; $b_params[] = $b_off; }
$b_sql = "
SELECT e.equipment_type, e.brand, e.model, e.serial_number,
       bt.date_borrowed, bt.due_date, bt.purpose, bt.borrower, bt.office_borrower
FROM equipment e
JOIN borrow_transactions bt ON e.equipment_id = bt.equipment_id
WHERE e.equipment_status = 'Borrowed'";
if ($b_filters) {
    $b_sql .= " AND " . implode(' AND ', $b_filters);
}
$b_sql .= " ORDER BY bt.date_borrowed DESC LIMIT {$limit} OFFSET {$offset_b}";
$b_stmt = $pdo->prepare($b_sql);
$b_stmt->execute($b_params);
$borrowed = $b_stmt->fetchAll();

// Available Equipment Query (with filters)
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

// Office list for filters and summary
$offices = ['DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo','DTI-Negros Occidental','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD','DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Borrower Dashboard</title>
  <style>
    body { font-family:Arial,sans-serif; margin:20px; font-size:0.9em; }
    .back-link{display:inline-block;margin-bottom:12px;color:#0056b3;text-decoration:none;}
    .back-link:hover{text-decoration:underline;}
    h1,h2{margin-top:24px;margin-bottom:8px;}
    table{width:100%;border-collapse:collapse;margin-bottom:12px;}
    th,td{border:1px solid #ccc;padding:8px;text-align:left;}
    th{background:#f4f4f4;}
    .pagination a{margin-right:8px;text-decoration:none;color:#0056b3;}
    .pagination span{margin-right:8px;}
    .modal{display:none;position:fixed;top:10%;left:10%;width:80%;background:#fff;padding:20px;border:1px solid #ccc;z-index:1000;}
  </style>
</head>
<body>
  <a href="index.php" class="back-link">← Back to Inventory</a>
  <h1>Borrower Dashboard</h1>
  <button onclick="location.href='borrow_equipment.php'">➕ Create Borrow Form</button>

  <!-- Slip Summary -->
  <h2>Slip Summary</h2>
  <table>
    <tr>
      <th>Slip No.</th><th>Returned/Total</th><th>Borrower</th><th>Office</th>
      <th>Purpose</th><th>Date Borrowed</th><th>Due Date</th><th>Action</th>
    </tr>
    <?php foreach($slip_summary as $row): ?>
    <tr>
      <td><?=htmlspecialchars($row['slip_no'])?></td>
      <td><?=$row['returned_equipment']?>/<?=$row['total_equipment']?></td>
      <td><?=htmlspecialchars($row['borrower'])?></td>
      <td><?=htmlspecialchars($row['office_borrower'])?></td>
      <td><?=htmlspecialchars($row['purpose'])?></td>
      <td><?=$row['date_borrowed']?></td>
      <td><?=$row['due_date']?></td>
      <td><button onclick="openReturnModal('<?= $row['slip_no'] ?>')">Return Equipment</button></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="pagination">
    <?php if($page_s>1): ?><a href="?page_s=<?=$page_s-1?>">← Prev</a><?php endif; ?>
    <span>Page <?=$page_s?></span>
    <?php if(count($slip_summary)==$limit): ?><a href="?page_s=<?=$page_s+1?>">Next →</a><?php endif; ?>
  </div>

  <!-- Borrowed Equipment -->
  <h2>Borrowed Equipment</h2>
  <form method="get">
    <input type="hidden" name="page_s" value="<?=$page_s?>">
    <label>Type:<input type="text" name="b_type" value="<?=htmlspecialchars($b_type)?>"></label>
    <label>Date From:<input type="date" name="b_from" value="<?=htmlspecialchars($b_from)?>"></label>
    <label>Date To:<input type="date" name="b_to" value="<?=htmlspecialchars($b_to)?>"></label>
    <label>Office:<select name="b_off">
      <option value="">--All--</option>
      <?php foreach($offices as $off): ?>
      <option value="<?=htmlspecialchars($off)?>"<?= $off===$b_off?' selected':'';?>><?=htmlspecialchars($off)?></option>
      <?php endforeach; ?>
    </select></label>
    <button type="submit">Filter</button>
  </form>
  <table>
    <tr><th>Type</th><th>Brand</th><th>Model</th><th>Serial</th><th>Date Borrowed</th><th>Due Date</th><th>Purpose</th><th>Borrower</th><th>Office</th></tr>
    <?php foreach($borrowed as $row): ?>
    <tr>
      <td><?=htmlspecialchars($row['equipment_type'])?></td>
      <td><?=htmlspecialchars($row['brand'])?></td>
      <td><?=htmlspecialchars($row['model'])?></td>
      <td><?=htmlspecialchars($row['serial_number'])?></td>
      <td><?=$row['date_borrowed']?></td>
      <td><?=$row['due_date']?></td>
      <td><?=htmlspecialchars($row['purpose'])?></td>
      <td><?=htmlspecialchars($row['borrower'])?></td>
      <td><?=htmlspecialchars($row['office_borrower'])?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="pagination">
    <?php if($page_b>1): ?><a href="?page_s=<?=$page_s?>&page_b=<?=$page_b-1?>&b_type=<?=urlencode($b_type)?>&b_from=<?=$b_from?>&b_to=<?=$b_to?>&b_off=<?=urlencode($b_off)?>">← Prev</a><?php endif; ?>
    <span>Page <?=$page_b?></span>
    <?php if(count($borrowed)==$limit): ?><a href="?page_s=<?=$page_s?>&page_b=<?=$page_b+1?>&b_type=<?=urlencode($b_type)?>&b_from=<?=$b_from?>&b_to=<?=$b_to?>&b_off=<?=urlencode($b_off)?>">Next →</a><?php endif; ?>
  </div>

  <!-- Available Equipment -->
  <h2>Available Equipment</h2>
  <form method="get">
    <input type="hidden" name="page_s" value="<?=$page_s?>">
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
    <?php if($page_a>1): ?><a href="?page_s=<?=$page_s?>&page_a=<?=$page_a-1?>&a_type=<?=urlencode($a_type)?>&a_brand=<?=urlencode($a_brand)?>&a_model=<?=urlencode($a_model)?>">← Prev</a><?php endif; ?>
    <span>Page <?=$page_a?></span>
    <?php if(count($available)==$limit): ?><a href="?page_s=<?=$page_s?>&page_a=<?=$page_a+1?>&a_type=<?=urlencode($a_type)?>&a_brand=<?=urlencode($a_brand)?>&a_model=<?=urlencode($a_model)?>">Next →</a><?php endif; ?>
  </div>

  <!-- Return Modal -->
  <div id="returnModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('returnModal').style.display='none'">&times;</span>
      <h3>Return Equipment for Slip <span id="modalSlip"></span></h3>
      <div id="modalContent">Loading...</div>
    </div>
  </div>

  <script>
  function openReturnModal(slipNo) {
    document.getElementById('modalSlip').innerText = slipNo;
    document.getElementById('modalContent').innerHTML = 'Loading...';
    document.getElementById('returnModal').style.display = 'block';
    fetch('get_borrowed_equipment.php?slip_no=' + encodeURIComponent(slipNo))
      .then(res => res.text())
      .then(html => document.getElementById('modalContent').innerHTML = html);
  }
  </script>
</body>
</html>
