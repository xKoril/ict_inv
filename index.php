<?php
// Add authentication check at the top of index.php
require_once 'auth.php';

// Require user to be logged in
$auth->requireLogin();

// Get current user info for display
$currentUser = $auth->getCurrentUser();

// Or require specific permission
$auth->requirePermission('add'); // for add pages
$auth->requirePermission('edit'); // for edit pages
$auth->requirePermission('delete'); // for delete pages

// Original index.php code continues here...
require 'db.php';

function getEnumValues(PDO $pdo, string $table, string $column): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!preg_match("/^enum\\('(.*)'\\)$/", $row['Type'] ?? '', $m)) {
        return [];
    }
    return array_map(fn($v) => trim($v, "'"), explode("','", $m[1]));
}

$categories = getEnumValues($pdo, 'equipment', 'equipment_category');
$statuses   = getEnumValues($pdo, 'equipment', 'equipment_status');

$fc = $_GET['equipment_category'] ?? '';
$fs = $_GET['equipment_status']   ?? '';
$ft = $_GET['equipment_type']     ?? '';
$fm = $_GET['model']              ?? '';
$fsn = $_GET['serial_number']     ?? '';
$fb = $_GET['brand']              ?? '';

// Sorting parameters
$sort = $_GET['sort'] ?? 'equipment_id';
$order = $_GET['order'] ?? 'desc';
$validSorts = ['equipment_id', 'equipment_category', 'equipment_status', 'equipment_type', 'brand', 'model', 'unit', 'quantity', 'serial_number'];
$sort = in_array($sort, $validSorts) ? $sort : 'equipment_id';
$order = ($order === 'asc') ? 'asc' : 'desc';

// Pagination vars
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$filters = [];
$params  = [];
if ($fc) { $filters[] = "equipment_category = ?"; $params[] = $fc; }
if ($fs) { $filters[] = "equipment_status = ?";   $params[] = $fs; }
if ($ft) { $filters[] = "equipment_type LIKE ?";  $params[] = "%$ft%"; }
if ($fm) { $filters[] = "model LIKE ?";           $params[] = "%$fm%"; }
if ($fsn) { $filters[] = "serial_number LIKE ?"; $params[] = "%$fsn%"; }
if ($fb) { $filters[] = "brand LIKE ?";           $params[] = "%$fb%"; }

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM equipment";
if ($filters) {
    $countSql .= " WHERE " . implode(' AND ', $filters);
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT
  equipment_id,
  equipment_category,
  equipment_status,
  equipment_type,
  brand,
  model,
  description_specification,
  unit,
  quantity,
  serial_number
FROM equipment";

if ($filters) {
    $sql .= " WHERE " . implode(' AND ', $filters);
}
$sql .= " ORDER BY $sort $order LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipment = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Equipment Inventory</title>
  <style>
    * {
      box-sizing: border-box;
    }
    
    body { 
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      margin: 0;
      padding: 20px;
      background-color: #f8f9fa;
      color: #333;
      line-height: 1.6;
    }
    
    .container {
      max-width: 1400px;
      margin: 0 auto;
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    
    .header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px;
      text-align: center;
      position: relative;
    }
    
    .header h1 {
      margin: 0;
      font-size: 2.5rem;
      font-weight: 300;
    }
    
    .user-info {
      position: absolute;
      top: 15px;
      right: 30px;
      font-size: 0.9rem;
      opacity: 0.9;
    }
    
    .user-info .user-role {
      background: rgba(255,255,255,0.2);
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.8rem;
      margin-left: 8px;
    }
    
    .logout-btn {
      background: rgba(255,255,255,0.2);
      color: white;
      border: none;
      padding: 6px 12px;
      border-radius: 4px;
      margin-left: 10px;
      cursor: pointer;
      font-size: 0.8rem;
      transition: background 0.3s;
    }
    
    .logout-btn:hover {
      background: rgba(255,255,255,0.3);
    }
    
    .content {
      padding: 30px;
    }
    
    .top-actions { 
      margin-bottom: 30px;
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    
    .top-actions button { 
      padding: 12px 20px;
      font-size: 1rem;
      border: none;
      border-radius: 6px;
      background: #007bff;
      color: white;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: 500;
    }
    
    .top-actions button:hover {
      background: #0056b3;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,123,255,0.3);
    }
    
    .top-actions button:disabled {
      background: #6c757d;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }
    
    .filter-form { 
      background: #f8f9fa;
      padding: 25px;
      border-radius: 8px;
      margin-bottom: 30px;
      border: 1px solid #e9ecef;
    }
    
    .filter-form label { 
      margin-right: 15px;
      font-weight: 500;
      color: #495057;
    }
    
    .filter-row { 
      margin-bottom: 15px;
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      align-items: center;
    }
    
    input[type="text"], input[type="number"], select { 
      padding: 10px 12px;
      font-size: 1rem;
      border: 1px solid #ced4da;
      border-radius: 4px;
      transition: border-color 0.3s ease;
      min-width: 140px;
    }
    
    input[type="text"]:focus, input[type="number"]:focus, select:focus {
      outline: none;
      border-color: #007bff;
      box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
    }
    
    .filter-form button {
      padding: 10px 20px;
      background: #28a745;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 500;
      transition: background 0.3s ease;
    }
    
    .filter-form button:hover {
      background: #218838;
    }
    
    .filter-form a {
      padding: 10px 15px;
      color: #6c757d;
      text-decoration: none;
      border: 1px solid #6c757d;
      border-radius: 4px;
      transition: all 0.3s ease;
    }
    
    .filter-form a:hover {
      background: #6c757d;
      color: white;
    }
    
    .records-info {
      background: #e9ecef;
      padding: 12px 20px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-weight: 500;
      color: #495057;
      text-align: center;
    }
    
    .table-container {
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }
    
    .table-wrapper {
      overflow-x: auto;
      max-height: 600px;
      overflow-y: auto;
    }
    
    table { 
      width: 100%;
      border-collapse: collapse;
      min-width: 1200px;
    }
    
    th, td { 
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #e9ecef;
      white-space: nowrap;
    }
    
    th { 
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      font-weight: 600;
      color: #495057;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    
    tr:hover {
      background-color: #f8f9fa;
    }
    
    .action-btn { 
      margin-right: 6px;
      margin-bottom: 4px;
      padding: 6px 12px;
      font-size: 0.85rem;
      border: 1px solid #dee2e6;
      background: #fff;
      cursor: pointer;
      border-radius: 4px;
      transition: all 0.3s ease;
      white-space: nowrap;
      display: inline-block;
    }
    
    .action-btn:hover { 
      background: #f8f9fa;
      border-color: #adb5bd;
      transform: translateY(-1px);
    }
    
    .action-btn:first-child { background: #e3f2fd; border-color: #2196f3; }
    .action-btn:nth-child(2) { background: #fff3e0; border-color: #ff9800; }
    .action-btn:last-child { background: #ffebee; border-color: #f44336; }
    
    .action-btn:disabled {
      background: #f8f9fa;
      color: #6c757d;
      cursor: not-allowed;
      border-color: #dee2e6;
    }
    
    .pagination-container {
      background: white;
      padding: 25px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .pagination { 
      display: flex;
      justify-content: center;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 20px;
    }
    
    .pagination a, .pagination span { 
      padding: 10px 15px;
      text-decoration: none;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 44px;
      height: 44px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .pagination a { 
      color: #007bff;
      background: #fff;
    }
    
    .pagination a:hover { 
      background: #007bff;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,123,255,0.3);
    }
    
    .pagination .current { 
      background: #007bff;
      color: white;
      border-color: #007bff;
      font-weight: 600;
    }
    
    .pagination .disabled { 
      color: #6c757d;
      cursor: not-allowed;
      background: #f8f9fa;
      border-color: #dee2e6;
    }
    
    .page-jump { 
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #e9ecef;
    }
    
    .page-jump input { 
      width: 70px;
      text-align: center;
      padding: 8px;
      border-radius: 4px;
    }
    
    .page-jump button {
      padding: 8px 16px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 500;
      transition: background 0.3s ease;
    }
    
    .page-jump button:hover {
      background: #0056b3;
    }
    
    .sortable { 
      cursor: pointer;
      user-select: none;
      position: relative;
      padding-right: 25px !important;
      transition: background 0.3s ease;
    }
    
    .sortable:hover { 
      background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%) !important;
    }
    
    .sort-arrow { 
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 14px;
      color: #6c757d;
      transition: color 0.3s ease;
    }
    
    .sort-arrow.asc::after { 
      content: "▲";
      color: #007bff;
    }
    
    .sort-arrow.desc::after { 
      content: "▼";
      color: #007bff;
    }
    
    .sort-arrow.none::after { 
      content: "⇅";
      opacity: 0.4;
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }
    
    .empty-state h3 {
      margin: 0 0 10px 0;
      font-size: 1.5rem;
      font-weight: 300;
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
      body {
        padding: 10px;
      }
      
      .header {
        padding: 20px;
      }
      
      .header h1 {
        font-size: 1.8rem;
      }
      
      .user-info {
        position: static;
        text-align: center;
        margin-top: 15px;
      }
      
      .content {
        padding: 20px;
      }
      
      .top-actions {
        justify-content: center;
      }
      
      .filter-row {
        flex-direction: column;
        align-items: stretch;
      }
      
      .filter-row label {
        display: flex;
        flex-direction: column;
        margin-bottom: 10px;
      }
      
      input[type="text"], input[type="number"], select {
        min-width: 100%;
        margin-top: 5px;
      }
      
      .table-wrapper {
        max-height: 400px;
      }
      
      th, td {
        padding: 8px 10px;
        font-size: 0.9rem;
      }
      
      .action-btn {
        padding: 4px 8px;
        font-size: 0.8rem;
        margin-bottom: 4px;
      }
      
      .pagination {
        gap: 4px;
      }
      
      .pagination a, .pagination span {
        padding: 8px 12px;
        min-width: 40px;
        height: 40px;
        font-size: 0.9rem;
      }
      
      .page-jump {
        flex-direction: column;
        gap: 8px;
      }
    }
    
    @media (max-width: 480px) {
      .header h1 {
        font-size: 1.5rem;
      }
      
      .top-actions button {
        font-size: 0.9rem;
        padding: 10px 16px;
      }
      
      .pagination a, .pagination span {
        padding: 6px 10px;
        min-width: 36px;
        height: 36px;
        font-size: 0.85rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="user-info">
        👤 Welcome, <?= htmlspecialchars($currentUser['full_name']) ?>
        <span class="user-role"><?= htmlspecialchars($currentUser['user_role']) ?></span>
        <button onclick="location.href='logout.php'" class="logout-btn">🚪 Logout</button>
      </div>
      <h1>MIS Equipment Inventory</h1>
    </div>
    
<div class="top-actions">
        <button onclick="location.href='add_equipment.php'" 
                <?= !$auth->hasPermission('add') ? 'disabled title="No permission to add equipment"' : '' ?>>
          ➕ Add New Equipment
        </button>
        <button onclick="location.href='borrower.php'"
                <?= !$auth->hasPermission('borrow') ? 'disabled title="No permission to borrow equipment"' : '' ?>>
          📦 Borrow Equipment
        </button>
        <button onclick="location.href='deployment.php'"
                <?= !$auth->hasPermission('deploy') ? 'disabled title="No permission to deploy equipment"' : '' ?>>
          🚚 Deploy Equipment
        </button>
        <?php if ($auth->hasPermission('manage_users')): ?>
        <button onclick="location.href='manage_users.php'" style="background: #dc3545;">
          👥 Manage Users
        </button>
        <?php endif; ?>
      </div>
      
      <form method="get" class="filter-form">
        <div class="filter-row">
          <label>Category:
            <select name="equipment_category">
              <option value="">--All--</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $fc ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Status:
            <select name="equipment_status">
              <option value="">--All--</option>
              <?php foreach ($statuses as $st): ?>
              <option value="<?= htmlspecialchars($st) ?>" <?= $st === $fs ? 'selected' : '' ?>>
                <?= htmlspecialchars($st) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Equipment Type:
            <input type="text" name="equipment_type" value="<?= htmlspecialchars($ft) ?>" placeholder="Search type">
          </label>
          <label>Brand:
            <input type="text" name="brand" value="<?= htmlspecialchars($fb) ?>" placeholder="Search brand">
          </label>
        </div>
        <div class="filter-row">
          <label>Model:
            <input type="text" name="model" value="<?= htmlspecialchars($fm) ?>" placeholder="Search model">
          </label>
          <label>Serial Number:
            <input type="text" name="serial_number" value="<?= htmlspecialchars($fsn) ?>" placeholder="Search serial number">
          </label>
          <button type="submit">Filter</button>
          <a href="index.php">Clear</a>
        </div>
      </form>
      
      <?php if ($totalRecords > 0): ?>
        <div class="records-info">
          Showing <?= count($equipment) ?> of <?= number_format($totalRecords) ?> records
        </div>
      <?php endif; ?>
      
      <div class="table-container">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <?php 
                function getSortLink($column, $title) {
                  global $sort, $order;
                  $newOrder = ($sort === $column && $order === 'asc') ? 'desc' : 'asc';
                  $params = array_merge($_GET, ['sort' => $column, 'order' => $newOrder, 'page' => 1]);
                  $url = '?' . http_build_query($params);
                  
                  $arrowClass = 'none';
                  if ($sort === $column) {
                    $arrowClass = $order;
                  }
                  
                  return "<th class=\"sortable\" onclick=\"location.href='$url'\">
                            $title
                            <span class=\"sort-arrow $arrowClass\"></span>
                          </th>";
                }
                ?>
                <th>Actions</th>
                <?= getSortLink('equipment_id', 'Equipment ID') ?>
                <?= getSortLink('equipment_category', 'Category') ?>
                <?= getSortLink('equipment_status', 'Status') ?>
                <?= getSortLink('equipment_type', 'Equipment Type') ?>
                <?= getSortLink('brand', 'Brand') ?>
                <?= getSortLink('model', 'Model') ?>
                <th>Description</th>
                <?= getSortLink('unit', 'Unit') ?>
                <?= getSortLink('quantity', 'Quantity') ?>
                <?= getSortLink('serial_number', 'Serial Number') ?>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($equipment)): ?>
              <tr>
                <td colspan="11">
                  <div class="empty-state">
                    <h3>No Equipment Found</h3>
                    <p>No equipment found matching your filters. Try adjusting your search criteria.</p>
                  </div>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($equipment as $e): ?>
                <tr>
                  <td>
                    <button class="action-btn" onclick="location.href='inspect_equipment.php?equipment_id=<?= $e['equipment_id'] ?>'">🔍 Inspect</button>
                    <button class="action-btn" onclick="location.href='edit_equipment.php?id=<?= $e['equipment_id'] ?>'"
                            <?= !$auth->hasPermission('edit') ? 'disabled title="No permission to edit"' : '' ?>>✏️ Edit</button>
                    <button class="action-btn" onclick="<?= $auth->hasPermission('delete') ? "if(confirm('Delete this item?')) location.href='delete_equipment.php?id={$e['equipment_id']}'" : "alert('No permission to delete')" ?>"
                            <?= !$auth->hasPermission('delete') ? 'disabled title="No permission to delete"' : '' ?>>🗑️ Delete</button>
                  </td>
                  <td><?= $e['equipment_id'] ?></td>
                  <td><?= htmlspecialchars($e['equipment_category']) ?></td>
                  <td><?= htmlspecialchars($e['equipment_status']) ?></td>
                  <td><?= htmlspecialchars($e['equipment_type']) ?></td>
                  <td><?= htmlspecialchars($e['brand']) ?></td>
                  <td><?= htmlspecialchars($e['model']) ?></td>
                  <td><?= htmlspecialchars($e['description_specification']) ?></td>
                  <td><?= htmlspecialchars($e['unit']) ?></td>
                  <td><?= htmlspecialchars($e['quantity']) ?></td>
                  <td><?= htmlspecialchars($e['serial_number']) ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <?php if ($totalPages > 1): ?>
      <div class="pagination-container">
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" title="First Page">First</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" title="Previous Page">← Prev</a>
          <?php else: ?>
            <span class="disabled">First</span>
            <span class="disabled">← Prev</span>
          <?php endif; ?>
          
          <?php
          // Show page numbers with current page highlighted
          $start = max(1, $page - 2);
          $end = min($totalPages, $page + 2);
          
          if ($start > 1) {
            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
            if ($start > 2) {
              echo '<span class="disabled">...</span>';
            }
          }
          
          for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) {
              echo '<span class="current">' . $i . '</span>';
            } else {
              echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a>';
            }
          }
          
          if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
              echo '<span class="disabled">...</span>';
            }
            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '">' . $totalPages . '</a>';
          }
          ?>
          
          <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" title="Next Page">Next →</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" title="Last Page">Last</a>
          <?php else: ?>
            <span class="disabled">Next →</span>
            <span class="disabled">Last</span>
          <?php endif; ?>
        </div>
        
        <div class="page-jump">
          <span>Jump to page:</span>
          <input type="number" id="pageJump" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" onkeypress="if(event.key==='Enter') jumpToPage()">
          <button onclick="jumpToPage()">Go</button>
          <span style="color: #6c757d; font-size: 0.9rem;">of <?= $totalPages ?> pages</span>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  
  <script>
  function jumpToPage() {
    const pageNum = document.getElementById('pageJump').value;
    const maxPage = <?= $totalPages ?>;
    
    if (pageNum >= 1 && pageNum <= maxPage) {
      const currentUrl = new URL(window.location);
      currentUrl.searchParams.set('page', pageNum);
      window.location.href = currentUrl.toString();
    } else {
      alert('Please enter a page number between 1 and ' + maxPage);
    }
  }
  </script>
</body>
</html>