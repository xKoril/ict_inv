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

// Sorting parameters
$s_sort = $_GET['s_sort'] ?? 'date_borrowed';
$s_order = $_GET['s_order'] ?? 'DESC';
$b_sort = $_GET['b_sort'] ?? 'date_borrowed';
$b_order = $_GET['b_order'] ?? 'DESC';
$a_sort = $_GET['a_sort'] ?? 'model';
$a_order = $_GET['a_order'] ?? 'ASC';

// Slip Summary filters
$s_slip = $_GET['s_slip'] ?? '';
$s_borrower = $_GET['s_borrower'] ?? '';
$s_office = $_GET['s_office'] ?? '';
$s_filters = [];
$s_params = [];
if ($s_slip) { $s_filters[] = "slip_no LIKE ?"; $s_params[] = "%$s_slip%"; }
if ($s_borrower) { $s_filters[] = "borrower LIKE ?"; $s_params[] = "%$s_borrower%"; }
if ($s_office) { $s_filters[] = "office_borrower LIKE ?"; $s_params[] = "%$s_office%"; }

// Get total counts for slip summary with filters
$total_summary_sql = "SELECT COUNT(DISTINCT slip_no) as total FROM borrow_transactions";
if ($s_filters) {
    $total_summary_sql .= " WHERE " . implode(' AND ', $s_filters);
}
$total_summary_stmt = $pdo->prepare($total_summary_sql);
$total_summary_stmt->execute($s_params);
$total_summary = $total_summary_stmt->fetch()['total'];

// Slip Summary Query with filters and sorting
$summary_sql = "
SELECT slip_no,
       borrower,
       office_borrower,
       purpose,
       date_borrowed,
       due_date,
       COUNT(*) AS total_equipment,
       SUM(CASE WHEN date_returned IS NOT NULL THEN 1 ELSE 0 END) AS returned_equipment
FROM borrow_transactions";
if ($s_filters) {
    $summary_sql .= " WHERE " . implode(' AND ', $s_filters);
}
$summary_sql .= " GROUP BY slip_no, borrower, office_borrower, purpose, date_borrowed, due_date
ORDER BY {$s_sort} {$s_order}
LIMIT {$limit} OFFSET {$offset_s}";
$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute($s_params);
$slip_summary = $summary_stmt->fetchAll();

// Borrowed Equipment Query (with enhanced filters)
$b_type = $_GET['b_type'] ?? '';
$b_from = $_GET['b_from'] ?? '';
$b_to   = $_GET['b_to']   ?? '';
$b_off  = $_GET['b_off']  ?? '';
$b_serial = $_GET['b_serial'] ?? '';
$b_borrower = $_GET['b_borrower'] ?? '';
$b_filters = [];
$b_params  = [];
if ($b_type) { $b_filters[] = "e.equipment_type LIKE ?"; $b_params[] = "%$b_type%"; }
if ($b_from) { $b_filters[] = "bt.date_borrowed >= ?";  $b_params[] = $b_from; }
if ($b_to)   { $b_filters[] = "bt.date_borrowed <= ?";  $b_params[] = $b_to; }
if ($b_off)  { $b_filters[] = "bt.office_borrower LIKE ?"; $b_params[] = "%$b_off%"; }
if ($b_serial) { $b_filters[] = "e.serial_number LIKE ?"; $b_params[] = "%$b_serial%"; }
if ($b_borrower) { $b_filters[] = "bt.borrower LIKE ?"; $b_params[] = "%$b_borrower%"; }

// Get total count for borrowed equipment with filters
$b_count_sql = "
SELECT COUNT(*) as total
FROM equipment e
JOIN borrow_transactions bt ON e.equipment_id = bt.equipment_id
WHERE e.equipment_status = 'Borrowed'";
if ($b_filters) {
    $b_count_sql .= " AND " . implode(' AND ', $b_filters);
}
$b_count_stmt = $pdo->prepare($b_count_sql);
$b_count_stmt->execute($b_params);
$total_borrowed = $b_count_stmt->fetch()['total'];

$b_sql = "
SELECT e.equipment_type, e.brand, e.model, e.serial_number,
       bt.slip_no, bt.date_borrowed, bt.due_date, bt.purpose, bt.borrower, bt.office_borrower
FROM equipment e
JOIN borrow_transactions bt ON e.equipment_id = bt.equipment_id
WHERE e.equipment_status = 'Borrowed'";
if ($b_filters) {
    $b_sql .= " AND " . implode(' AND ', $b_filters);
}
$b_sql .= " ORDER BY bt.{$b_sort} {$b_order} LIMIT {$limit} OFFSET {$offset_b}";
$b_stmt = $pdo->prepare($b_sql);
$b_stmt->execute($b_params);
$borrowed = $b_stmt->fetchAll();

// Available Equipment Query (with enhanced filters)
$a_type  = $_GET['a_type']  ?? '';
$a_brand = $_GET['a_brand'] ?? '';
$a_model = $_GET['a_model'] ?? '';
$a_serial = $_GET['a_serial'] ?? '';
$a_filters = [];
$a_params  = [];
if ($a_type)  { $a_filters[] = "equipment_type LIKE ?"; $a_params[] = "%$a_type%"; }
if ($a_brand) { $a_filters[] = "brand LIKE ?";          $a_params[] = "%$a_brand%"; }
if ($a_model) { $a_filters[] = "model LIKE ?";          $a_params[] = "%$a_model%"; }
if ($a_serial) { $a_filters[] = "serial_number LIKE ?"; $a_params[] = "%$a_serial%"; }

// Get total count for available equipment with filters
$a_count_sql = "
SELECT COUNT(*) as total
FROM equipment
WHERE equipment_status = 'Available for Deployment'";
if ($a_filters) {
    $a_count_sql .= " AND " . implode(' AND ', $a_filters);
}
$a_count_stmt = $pdo->prepare($a_count_sql);
$a_count_stmt->execute($a_params);
$total_available = $a_count_stmt->fetch()['total'];

$a_sql = "
SELECT equipment_type, brand, model, locator, serial_number, description_specification
FROM equipment
WHERE equipment_status = 'Available for Deployment'";
if ($a_filters) {
    $a_sql .= " AND " . implode(' AND ', $a_filters);
}
$a_sql .= " ORDER BY {$a_sort} {$a_order} LIMIT {$limit} OFFSET {$offset_a}";
$a_stmt = $pdo->prepare($a_sql);
$a_stmt->execute($a_params);
$available = $a_stmt->fetchAll();

// Helper function to generate sort URLs
function getSortUrl($section, $column, $current_sort, $current_order) {
    global $page_s, $page_b, $page_a, $b_type, $b_from, $b_to, $b_off, $b_serial, $b_borrower;
    global $a_type, $a_brand, $a_model, $a_serial, $s_slip, $s_borrower, $s_office;
    
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params = [
        'page_s' => $page_s,
        'page_b' => $page_b,
        'page_a' => $page_a,
        'b_type' => $b_type,
        'b_from' => $b_from,
        'b_to' => $b_to,
        'b_off' => $b_off,
        'b_serial' => $b_serial,
        'b_borrower' => $b_borrower,
        'a_type' => $a_type,
        'a_brand' => $a_brand,
        'a_model' => $a_model,
        'a_serial' => $a_serial,
        's_slip' => $s_slip,
        's_borrower' => $s_borrower,
        's_office' => $s_office
    ];
    
    $params[$section . '_sort'] = $column;
    $params[$section . '_order'] = $new_order;
    
    return '?' . http_build_query($params);
}

// Helper function to get sort arrow
function getSortArrow($section, $column, $current_sort, $current_order) {
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? ' ↑' : ' ↓';
    }
    return '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Borrower Dashboard</title>
  <style>
    body { 
      font-family: Arial, sans-serif; 
      margin: 20px; 
      font-size: 0.9em; 
      background-color: #f8f9fa;
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .back-link { 
      display: inline-block; 
      margin-bottom: 12px; 
      color: #0056b3; 
      text-decoration: none; 
      font-weight: 500;
    }
    .back-link:hover { text-decoration: underline; }
    
    h1, h2 { 
      margin-top: 24px; 
      margin-bottom: 8px; 
      color: #333;
    }
    
    .btn-primary {
      background-color: #007bff;
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
      background-color: #0056b3;
    }
    
    /* Table Container Styles */
    .table-section {
      margin-bottom: 40px;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      overflow: hidden;
      background: white;
    }
    
    .table-header {
      background: #f8f9fa;
      padding: 15px 20px;
      border-bottom: 1px solid #dee2e6;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }
    
    .table-header h2 {
      margin: 0;
      color: #495057;
      font-size: 1.2em;
    }
    
    .records-info {
      color: #6c757d;
      font-size: 0.85em;
      font-weight: normal;
    }
    
    .table-wrapper {
      height: 400px;
      overflow-y: auto;
      overflow-x: auto;
    }
    
    table { 
      width: 100%; 
      border-collapse: collapse; 
      margin: 0;
      min-width: 800px; /* Ensures horizontal scroll on small screens */
    }
    
    th, td { 
      border: 1px solid #dee2e6; 
      padding: 12px 8px; 
      text-align: left; 
      vertical-align: middle;
      font-size: 0.85em;
    }
    
    th { 
      background: #f8f9fa; 
      font-weight: 600;
      position: sticky;
      top: 0;
      z-index: 10;
      color: #495057;
      cursor: pointer;
      user-select: none;
    }
    
    th:hover {
      background: #e9ecef;
    }
    
    th a {
      color: #495057;
      text-decoration: none;
      display: block;
      width: 100%;
      height: 100%;
    }
    
    tbody tr:hover {
      background-color: #f8f9fa;
    }
    
    tbody tr:nth-child(even) {
      background-color: #fdfdfd;
    }
    
    /* Custom Scrollbar */
    .table-wrapper::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }
    
    .table-wrapper::-webkit-scrollbar-track {
      background: #f1f1f1;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb:hover {
      background: #a1a1a1;
    }
    
    /* Filter Form Styles */
    .filter-form {
      background: #f8f9fa;
      padding: 15px 20px;
      border-bottom: 1px solid #dee2e6;
      display: flex;
      gap: 15px;
      align-items: end;
      flex-wrap: wrap;
    }
    
    .filter-form label {
      display: block;
      font-size: 0.85em;
      color: #495057;
      margin-bottom: 5px;
      min-width: 120px;
    }
    
    .filter-form input,
    .filter-form select {
      padding: 6px 10px;
      border: 1px solid #ced4da;
      border-radius: 4px;
      font-size: 0.85em;
      width: 100%;
    }
    
    .filter-form button {
      background-color: #007bff;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.85em;
      height: fit-content;
    }
    
    .filter-form button:hover {
      background-color: #0056b3;
    }
    
    /* Enhanced Pagination */
    .pagination-container {
      padding: 15px 20px;
      background: #f8f9fa;
      border-top: 1px solid #dee2e6;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }
    
    .pagination {
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .pagination a,
    .pagination span {
      padding: 8px 12px;
      text-decoration: none;
      border: 1px solid #dee2e6;
      border-radius: 4px;
      font-size: 0.85em;
      min-width: 35px;
      text-align: center;
      display: inline-block;
    }
    
    .pagination a {
      color: #007bff;
      background-color: white;
      transition: all 0.2s;
    }
    
    .pagination a:hover {
      background-color: #007bff;
      color: white;
    }
    
    .pagination .current {
      background-color: #007bff;
      color: white;
      border-color: #007bff;
    }
    
    .pagination .disabled {
      color: #6c757d;
      background-color: #f8f9fa;
      cursor: not-allowed;
    }
    
    .page-info {
      color: #6c757d;
      font-size: 0.85em;
    }
    
    /* Action Buttons */
    .btn-action {
      background-color: #28a745;
      color: white;
      border: none;
      padding: 6px 12px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.8em;
    }
    
    .btn-action:hover {
      background-color: #218838;
    }
    
    /* Modal Styles */
    .modal { 
      display: none; 
      position: fixed; 
      top: 10%; 
      left: 10%; 
      width: 80%; 
      max-height: 80%; 
      overflow: auto; 
      background: #fff; 
      padding: 20px; 
      border: 1px solid #ccc; 
      z-index: 1000;
      border-radius: 8px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    
    .modal-content { 
      padding: 20px; 
    }
    
    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }
    
    .close:hover {
      color: #000;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      .container {
        margin: 10px;
        padding: 15px;
      }
      
      .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }
      
      .filter-form {
        flex-direction: column;
        align-items: stretch;
      }
      
      .filter-form label {
        min-width: auto;
      }
      
      .pagination-container {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
      }
      
      .table-wrapper {
        height: 300px;
      }
      
      th, td {
        padding: 8px 6px;
        font-size: 0.8em;
      }
    }
    
    @media (max-width: 480px) {
      body {
        margin: 10px;
        font-size: 0.85em;
      }
      
      .table-wrapper {
        height: 250px;
      }
      
      th, td {
        padding: 6px 4px;
        font-size: 0.75em;
      }
      
      .pagination a,
      .pagination span {
        padding: 6px 8px;
        font-size: 0.75em;
        min-width: 30px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="index.php" class="back-link">← Back to Inventory</a>
    <h1>Borrower Dashboard</h1>
    <button onclick="location.href='borrow_equipment.php'" class="btn-primary">➕ Create Borrow Form</button>

    <!-- Slip Summary Section -->
    <div class="table-section">
      <div class="table-header">
        <h2>Borrower Summary</h2>
        <div class="records-info">
          Showing <?= min($limit, count($slip_summary)) ?> of <?= $total_summary ?> records
        </div>
      </div>
      <form method="get" class="filter-form">
        <input type="hidden" name="page_b" value="<?= $page_b ?>">
        <input type="hidden" name="page_a" value="<?= $page_a ?>">
        <input type="hidden" name="s_sort" value="<?= htmlspecialchars($s_sort) ?>">
        <input type="hidden" name="s_order" value="<?= htmlspecialchars($s_order) ?>">
        <label>Slip No.: <input type="text" name="s_slip" value="<?= htmlspecialchars($s_slip) ?>"></label>
        <label>Borrower: <input type="text" name="s_borrower" value="<?= htmlspecialchars($s_borrower) ?>"></label>
        <label>Office: <input type="text" name="s_office" value="<?= htmlspecialchars($s_office) ?>"></label>
        <button type="submit">Filter</button>
      </form>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th><a href="<?= getSortUrl('s', 'slip_no', $s_sort, $s_order) ?>">Slip No.<?= getSortArrow('s', 'slip_no', $s_sort, $s_order) ?></a></th>
              <th>Returned/Total</th>
              <th><a href="<?= getSortUrl('s', 'borrower', $s_sort, $s_order) ?>">Borrower<?= getSortArrow('s', 'borrower', $s_sort, $s_order) ?></a></th>
              <th><a href="<?= getSortUrl('s', 'office_borrower', $s_sort, $s_order) ?>">Office<?= getSortArrow('s', 'office_borrower', $s_sort, $s_order) ?></a></th>
              <th><a href="<?= getSortUrl('s', 'purpose', $s_sort, $s_order) ?>">Purpose<?= getSortArrow('s', 'purpose', $s_sort, $s_order) ?></a></th>
              <th><a href="<?= getSortUrl('s', 'date_borrowed', $s_sort, $s_order) ?>">Date Borrowed<?= getSortArrow('s', 'date_borrowed', $s_sort, $s_order) ?></a></th>
              <th><a href="<?= getSortUrl('s', 'due_date', $s_sort, $s_order) ?>">Due Date<?= getSortArrow('s', 'due_date', $s_sort, $s_order) ?></a></th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($slip_summary as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['slip_no']) ?></td>
              <td><?= $row['returned_equipment'] ?>/<?= $row['total_equipment'] ?></td>
              <td><?= htmlspecialchars($row['borrower']) ?></td>
              <td><?= htmlspecialchars($row['office_borrower']) ?></td>
              <td><?= htmlspecialchars($row['purpose']) ?></td>
              <td><?= $row['date_borrowed'] ?></td>
              <td><?= $row['due_date'] ?></td>
              <td><button onclick="openReturnModal('<?= $row['slip_no'] ?>')" class="btn-action">Return Equipment</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="pagination-container">
        <div class="page-info">
          Page <?= $page_s ?> of <?= ceil($total_summary / $limit) ?>
        </div>
        <div class="pagination">
          <?php if ($page_s > 1): ?>
            <a href="?page_s=<?= $page_s - 1 ?>&page_b=<?= $page_b ?>&page_a=<?= $page_a ?>&b_type=<?= urlencode($b_type) ?>&b_from=<?= urlencode($b_from) ?>&b_to=<?= urlencode($b_to) ?>&b_off=<?= urlencode($b_off) ?>&b_serial=<?= urlencode($b_serial) ?>&b_borrower=<?= urlencode($b_borrower) ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>&a_serial=<?= urlencode($a_serial) ?>&s_slip=<?= urlencode($s_slip) ?>&s_borrower=<?= urlencode($s_borrower) ?>&s_office=<?= urlencode($s_office) ?>&s_sort=<?= urlencode($s_sort) ?>&s_order=<?= urlencode($s_order) ?>&b_sort=<?= urlencode($b_sort) ?>&b_order=<?= urlencode($b_order) ?>&a_sort=<?= urlencode($a_sort) ?>&a_order=<?= urlencode($a_order) ?>">← Prev</a>
          <?php else: ?>
            <span class="disabled">← Prev</span>
          <?php endif; ?>
          
          <?php for ($i = max(1, $page_s - 2); $i <= min(ceil($total_summary / $limit), $page_s + 2); $i++): ?>
            <?php if ($i == $page_s): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?page_s=<?= $i ?>&page_b=<?= $page_b ?>&page_a=<?= $page_a ?>&b_type=<?= urlencode($b_type) ?>&b_from=<?= urlencode($b_from) ?>&b_to=<?= urlencode($b_to) ?>&b_off=<?= urlencode($b_off) ?>&b_serial=<?= urlencode($b_serial) ?>&b_borrower=<?= urlencode($b_borrower) ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>&a_serial=<?= urlencode($a_serial) ?>&s_slip=<?= urlencode($s_slip) ?>&s_borrower=<?= urlencode($s_borrower) ?>&s_office=<?= urlencode($s_office) ?>&s_sort=<?= urlencode($s_sort) ?>&s_order=<?= urlencode($s_order) ?>&b_sort=<?= urlencode($b_sort) ?>&b_order=<?= urlencode($b_order) ?>&a_sort=<?= urlencode($a_sort) ?>&a_order=<?= urlencode($a_order) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($page_s < ceil($total_summary / $limit)): ?>
            <a href="?page_s=<?= $page_s + 1 ?>&page_b=<?= $page_b ?>&page_a=<?= $page_a ?>&b_type=<?= urlencode($b_type) ?>&b_from=<?= urlencode($b_from) ?>&b_to=<?= urlencode($b_to) ?>&b_off=<?= urlencode($b_off) ?>&b_serial=<?= urlencode($b_serial) ?>&b_borrower=<?= urlencode($b_borrower) ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>&a_serial=<?= urlencode($a_serial) ?>&s_slip=<?= urlencode($s_slip) ?>&s_borrower=<?= urlencode($s_borrower) ?>&s_office=<?= urlencode($s_office) ?>&s_sort=<?= urlencode($s_sort) ?>&s_order=<?= urlencode($s_order) ?>&b_sort=<?= urlencode($b_sort) ?>&b_order=<?= urlencode($b_order) ?>&a_sort=<?= urlencode($a_sort) ?>&a_order=<?= urlencode($a_order) ?>">Next →</a>
          <?php else: ?>
            <span class="disabled">Next →</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Borrowed Equipment Section -->
    <div class="table-section">
      <div class="table-header">
        <h2>Borrowed Equipment</h2>
        <div class="records-info">
          Showing <?= min($limit, count($borrowed)) ?> of <?= $total_borrowed ?> records
        </div>
      </div>
      <form method="get" class="filter-form">
        <input type="hidden" name="page_s" value="<?= $page_s ?>">
        <input type="hidden" name="page_a" value="<?= $page_a ?>">
        <input type="hidden" name="b_sort" value="<?= htmlspecialchars($b_sort) ?>">
        <input type="hidden" name="b_order" value="<?= htmlspecialchars($b_order) ?>">
        <label>Type: <input type="text" name="b_type" value="<?= htmlspecialchars($b_type) ?>"></label>
        <label>Date From: <input type="date" name="b_from" value="<?= htmlspecialchars($b_from) ?>"></label>
        <label>Date To: <input type="date" name="b_to" value="<?= htmlspecialchars($b_to) ?>"></label>
        <label>Office: <input type="text" name="b_off" value="<?= htmlspecialchars($b_off) ?>"></label>
        <label>Serial No.: <input type="text" name="b_serial" value="<?= htmlspecialchars($b_serial) ?>"></label>
        <label>Borrower: <input type="text" name="b_borrower" value="<?= htmlspecialchars($b_borrower) ?>"></label>
        <button type="submit">Filter</button>
      </form>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th><a href="<?= getSortUrl('b', 'slip_no', $b_sort, $b_order) ?>">Slip No.<?= getSortArrow('b', 'slip_no', $b_sort, $b_order) ?></a></th>
              <th><a href="<?= getSortUrl('b', 'equipment_type', $b_sort, $b_order) ?>">Type<?= getSortArrow('b', 'equipment_type', $b_sort, $b_order) ?></a></th>
              <th><a href="<?= getSortUrl('b', 'brand', $b_sort, $b_order) ?>">Brand<?= getSortArrow('b', 'brand', $b_sort, $b_order) ?></a></th>
              <th><a href="<?= getSortUrl('b', 'model', $b_sort, $b_order) ?>">Model<?= getSortArrow('b', 'model', $b_sort, $b_order) ?></a></th>
              <th><a href="<?= getSortUrl('b', 'serial_number', $b_sort, $b_order) ?>">Serial<?= getSortArrow('b', 'serial_number', $b_sort, $b_order) ?></a></th>
              <th><a href="<?= getSortUrl('b', 'date_borrowed', $b_sort, $b_order) ?>">Date Borrowed<?= getSortArrow('b', 'date_borrowed', $b_sort, $b_order) ?></a></th>
              <th><a href="<?= getSortUrl('b', 'due_date', $b_sort, $b_order) ?>">Due Date<?= getSortArrow('b', 'due_date', $b_sort, $b_order) ?></a></th>
              <th><a href="<?= getSortUrl('b', 'purpose', $b_sort, $b_order) ?>">Purpose<?= getSortArrow('b', 'purpose', $b_sort, $b_order) ?></a></th>
              <th><a href="<?= getSortUrl('b', 'borrower', $b_sort, $b_order) ?>">Borrower<?= getSortArrow('b', 'borrower', $b_sort, $b_order) ?></a></th>
              <th><a href="<?= getSortUrl('b', 'office_borrower', $b_sort, $b_order) ?>">Office<?= getSortArrow('b', 'office_borrower', $b_sort, $b_order) ?></a></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($borrowed as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['slip_no']) ?></td>
              <td><?= htmlspecialchars($row['equipment_type']) ?></td>
              <td><?= htmlspecialchars($row['brand']) ?></td>
              <td><?= htmlspecialchars($row['model']) ?></td>
              <td><?= htmlspecialchars($row['serial_number']) ?></td>
              <td><?= $row['date_borrowed'] ?></td>
              <td><?= $row['due_date'] ?></td>
              <td><?= htmlspecialchars($row['purpose']) ?></td>
              <td><?= htmlspecialchars($row['borrower']) ?></td>
              <td><?= htmlspecialchars($row['office_borrower']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="pagination-container">
        <div class="page-info">
          Page <?= $page_b ?> of <?= ceil($total_borrowed / $limit) ?>
        </div>
        <div class="pagination">
          <?php if ($page_b > 1): ?>
            <a href="?page_s=<?= $page_s ?>&page_b=<?= $page_b - 1 ?>&page_a=<?= $page_a ?>&b_type=<?= urlencode($b_type) ?>&b_from=<?= urlencode($b_from) ?>&b_to=<?= urlencode($b_to) ?>&b_off=<?= urlencode($b_off) ?>&b_serial=<?= urlencode($b_serial) ?>&b_borrower=<?= urlencode($b_borrower) ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>&a_serial=<?= urlencode($a_serial) ?>&s_slip=<?= urlencode($s_slip) ?>&s_borrower=<?= urlencode($s_borrower) ?>&s_office=<?= urlencode($s_office) ?>&s_sort=<?= urlencode($s_sort) ?>&s_order=<?= urlencode($s_order) ?>&b_sort=<?= urlencode($b_sort) ?>&b_order=<?= urlencode($b_order) ?>&a_sort=<?= urlencode($a_sort) ?>&a_order=<?= urlencode($a_order) ?>">← Prev</a>
          <?php else: ?>
            <span class="disabled">← Prev</span>
          <?php endif; ?>
          
          <?php for ($i = max(1, $page_b - 2); $i <= min(ceil($total_borrowed / $limit), $page_b + 2); $i++): ?>
            <?php if ($i == $page_b): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?page_s=<?= $page_s ?>&page_b=<?= $i ?>&page_a=<?= $page_a ?>&b_type=<?= urlencode($b_type) ?>&b_from=<?= urlencode($b_from) ?>&b_to=<?= urlencode($b_to) ?>&b_off=<?= urlencode($b_off) ?>&b_serial=<?= urlencode($b_serial) ?>&b_borrower=<?= urlencode($b_borrower) ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>&a_serial=<?= urlencode($a_serial) ?>&s_slip=<?= urlencode($s_slip) ?>&s_borrower=<?= urlencode($s_borrower) ?>&s_office=<?= urlencode($s_office) ?>&s_sort=<?= urlencode($s_sort) ?>&s_order=<?= urlencode($s_order) ?>&b_sort=<?= urlencode($b_sort) ?>&b_order=<?= urlencode($b_order) ?>&a_sort=<?= urlencode($a_sort) ?>&a_order=<?= urlencode($a_order) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($page_b < ceil($total_borrowed / $limit)): ?>
            <a href="?page_s=<?= $page_s ?>&page_b=<?= $page_b + 1 ?>&page_a=<?= $page_a ?>&b_type=<?= urlencode($b_type) ?>&b_from=<?= urlencode($b_from) ?>&b_to=<?= urlencode($b_to) ?>&b_off=<?= urlencode($b_off) ?>&b_serial=<?= urlencode($b_serial) ?>&b_borrower=<?= urlencode($b_borrower) ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>&a_serial=<?= urlencode($a_serial) ?>&s_slip=<?= urlencode($s_slip) ?>&s_borrower=<?= urlencode($s_borrower) ?>&s_office=<?= urlencode($s_office) ?>&s_sort=<?= urlencode($s_sort) ?>&s_order=<?= urlencode($s_order) ?>&b_sort=<?= urlencode($b_sort) ?>&b_order=<?= urlencode($b_order) ?>&a_sort=<?= urlencode($a_sort) ?>&a_order=<?= urlencode($a_order) ?>">Next →</a>
          <?php else: ?>
            <span class="disabled">Next →</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Available Equipment Section -->
    <div class="table-section">
      <div class="table-header">
        <h2>Available Equipment</h2>
        <div class="records-info">
          Showing <?= min($limit, count($available)) ?> of <?= $total_available ?> records
        </div>
      </div>
      <form method="get" class="filter-form">
        <input type="hidden" name="page_s" value="<?= $page_s ?>">
        <input type="hidden" name="page_b" value="<?= $page_b ?>">
        <input type="hidden" name="a_sort" value="<?= htmlspecialchars($a_sort) ?>">
        <input type="hidden" name="a_order" value="<?= htmlspecialchars($a_order) ?>">
        <label>Type: <input type="text" name="a_type" value="<?= htmlspecialchars($a_type) ?>"></label>
        <label>Brand: <input type="text" name="a_brand" value="<?= htmlspecialchars($a_brand) ?>"></label>
        <label>Model: <input type="text" name="a_model" value="<?= htmlspecialchars($a_model) ?>"></label>
        <label>Serial No.: <input type="text" name="a_serial" value="<?= htmlspecialchars($a_serial) ?>"></label>
        <button type="submit">Filter</button>
      </form>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th><a href="<?= getSortUrl('a', 'equipment_type', $a_sort, $a_order) ?>">Type<?= getSortArrow('a', 'equipment_type', $a_sort, $a_order) ?></a></th>
              <th><a href="<?= getSortUrl('a', 'brand', $a_sort, $a_order) ?>">Brand<?= getSortArrow('a', 'brand', $a_sort, $a_order) ?></a></th>
              <th><a href="<?= getSortUrl('a', 'model', $a_sort, $a_order) ?>">Model<?= getSortArrow('a', 'model', $a_sort, $a_order) ?></a></th>
              <th><a href="<?= getSortUrl('a', 'locator', $a_sort, $a_order) ?>">Locator<?= getSortArrow('a', 'locator', $a_sort, $a_order) ?></a></th>
              <th><a href="<?= getSortUrl('a', 'serial_number', $a_sort, $a_order) ?>">Serial<?= getSortArrow('a', 'serial_number', $a_sort, $a_order) ?></a></th>
              <th><a href="<?= getSortUrl('a', 'description_specification', $a_sort, $a_order) ?>">Description<?= getSortArrow('a', 'description_specification', $a_sort, $a_order) ?></a></th>
            </tr>
          </thead>
          <tbody>
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
          </tbody>
        </table>
      </div>
      <div class="pagination-container">
        <div class="page-info">
          Page <?= $page_a ?> of <?= ceil($total_available / $limit) ?>
        </div>
        <div class="pagination">
          <?php if ($page_a > 1): ?>
            <a href="?page_s=<?= $page_s ?>&page_b=<?= $page_b ?>&page_a=<?= $page_a - 1 ?>&b_type=<?= urlencode($b_type) ?>&b_from=<?= urlencode($b_from) ?>&b_to=<?= urlencode($b_to) ?>&b_off=<?= urlencode($b_off) ?>&b_serial=<?= urlencode($b_serial) ?>&b_borrower=<?= urlencode($b_borrower) ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>&a_serial=<?= urlencode($a_serial) ?>&s_slip=<?= urlencode($s_slip) ?>&s_borrower=<?= urlencode($s_borrower) ?>&s_office=<?= urlencode($s_office) ?>&s_sort=<?= urlencode($s_sort) ?>&s_order=<?= urlencode($s_order) ?>&b_sort=<?= urlencode($b_sort) ?>&b_order=<?= urlencode($b_order) ?>&a_sort=<?= urlencode($a_sort) ?>&a_order=<?= urlencode($a_order) ?>">← Prev</a>
          <?php else: ?>
            <span class="disabled">← Prev</span>
          <?php endif; ?>
          
          <?php for ($i = max(1, $page_a - 2); $i <= min(ceil($total_available / $limit), $page_a + 2); $i++): ?>
            <?php if ($i == $page_a): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?page_s=<?= $page_s ?>&page_b=<?= $page_b ?>&page_a=<?= $i ?>&b_type=<?= urlencode($b_type) ?>&b_from=<?= urlencode($b_from) ?>&b_to=<?= urlencode($b_to) ?>&b_off=<?= urlencode($b_off) ?>&b_serial=<?= urlencode($b_serial) ?>&b_borrower=<?= urlencode($b_borrower) ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>&a_serial=<?= urlencode($a_serial) ?>&s_slip=<?= urlencode($s_slip) ?>&s_borrower=<?= urlencode($s_borrower) ?>&s_office=<?= urlencode($s_office) ?>&s_sort=<?= urlencode($s_sort) ?>&s_order=<?= urlencode($s_order) ?>&b_sort=<?= urlencode($b_sort) ?>&b_order=<?= urlencode($b_order) ?>&a_sort=<?= urlencode($a_sort) ?>&a_order=<?= urlencode($a_order) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($page_a < ceil($total_available / $limit)): ?>
            <a href="?page_s=<?= $page_s ?>&page_b=<?= $page_b ?>&page_a=<?= $page_a + 1 ?>&b_type=<?= urlencode($b_type) ?>&b_from=<?= urlencode($b_from) ?>&b_to=<?= urlencode($b_to) ?>&b_off=<?= urlencode($b_off) ?>&b_serial=<?= urlencode($b_serial) ?>&b_borrower=<?= urlencode($b_borrower) ?>&a_type=<?= urlencode($a_type) ?>&a_brand=<?= urlencode($a_brand) ?>&a_model=<?= urlencode($a_model) ?>&a_serial=<?= urlencode($a_serial) ?>&s_slip=<?= urlencode($s_slip) ?>&s_borrower=<?= urlencode($s_borrower) ?>&s_office=<?= urlencode($s_office) ?>&s_sort=<?= urlencode($s_sort) ?>&s_order=<?= urlencode($s_order) ?>&b_sort=<?= urlencode($b_sort) ?>&b_order=<?= urlencode($b_order) ?>&a_sort=<?= urlencode($a_sort) ?>&a_order=<?= urlencode($a_order) ?>">Next →</a>
          <?php else: ?>
            <span class="disabled">Next →</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Return Modal -->
    <div id="returnModal" class="modal">
      <div class="modal-content">
        <span class="close" onclick="document.getElementById('returnModal').style.display='none'">&times;</span>
        <h3>Return Equipment for Slip <span id="modalSlip"></span></h3>
        <div id="modalContent">Loading...</div>
      </div>
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