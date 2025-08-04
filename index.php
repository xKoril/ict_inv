<?php
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
$fsn = $_GET['serial_number']     ?? ''; // Added serial number filter
$fb = $_GET['brand']              ?? ''; // Added brand filter

// Sorting parameters
$sort = $_GET['sort'] ?? 'equipment_id';
$order = $_GET['order'] ?? 'desc';
$validSorts = ['equipment_id', 'equipment_category', 'equipment_status', 'equipment_type', 'brand', 'model', 'unit', 'quantity', 'serial_number'];
$sort = in_array($sort, $validSorts) ? $sort : 'equipment_id';
$order = ($order === 'asc') ? 'asc' : 'desc';

// --- PAGINATION VARS ---
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$filters = [];
$params  = [];
if ($fc) { $filters[] = "equipment_category = ?"; $params[] = $fc; }
if ($fs) { $filters[] = "equipment_status = ?";   $params[] = $fs; }
if ($ft) { $filters[] = "equipment_type LIKE ?";  $params[] = "%$ft%"; }
if ($fm) { $filters[] = "model LIKE ?";           $params[] = "%$fm%"; }
if ($fsn) { $filters[] = "serial_number LIKE ?"; $params[] = "%$fsn%"; } // Added serial number filter
if ($fb) { $filters[] = "brand LIKE ?";           $params[] = "%$fb%"; } // Added brand filter

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
  <title>Equipment Inventory</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .top-actions { margin-bottom: 20px; }
    .top-actions button { margin-right: 8px; padding: 8px 12px; font-size: 1em; }
    .filter-form { margin-bottom: 16px; }
    .filter-form label { margin-right: 12px; }
    .filter-row { margin-bottom: 10px; }
    input[type="text"], input[type="number"], select { padding: 6px; font-size: 1em; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #f4f4f4; }
    .action-btn { margin-right: 4px; padding: 4px 8px; font-size: 0.9em; border: 1px solid #ccc; background: #fff; cursor: pointer; }
    .action-btn:hover { background: #f0f0f0; }
    .pagination { margin-top: 20px; }
    .pagination a, .pagination span { 
      margin-right: 8px; 
      padding: 6px 12px; 
      text-decoration: none; 
      border: 1px solid #ddd; 
      display: inline-block; 
    }
    .pagination a { 
      color: #0056b3; 
      background: #fff; 
    }
    .pagination a:hover { 
      background: #f0f0f0; 
    }
    .pagination .current { 
      background: #0056b3; 
      color: white; 
      border-color: #0056b3; 
    }
    .pagination .disabled { 
      color: #aaa; 
      cursor: default; 
      background: #f9f9f9; 
    }
    .page-jump { 
      margin-left: 20px; 
    }
    .page-jump input { 
      width: 50px; 
      text-align: center; 
    }
    .sortable { 
      cursor: pointer; 
      user-select: none; 
      position: relative; 
      padding-right: 20px; 
    }
    .sortable:hover { 
      background: #e8e8e8; 
    }
    .sort-arrow { 
      position: absolute; 
      right: 5px; 
      top: 50%; 
      transform: translateY(-50%); 
      font-size: 12px; 
      color: #666; 
    }
    .sort-arrow.asc::after { 
      content: "▲"; 
    }
    .sort-arrow.desc::after { 
      content: "▼"; 
    }
    .sort-arrow.none::after { 
      content: "⇅"; 
      opacity: 0.3; 
    }
  </style>
</head>
<body>
  <h1>Equipment Inventory</h1>
  <div class="top-actions">
    <button onclick="location.href='add_equipment.php'">➕ Add New Equipment</button>
    <button onclick="location.href='borrower.php'">📦 Borrow Equipment</button>
    <button onclick="location.href='deployment.php'">🚚 Deploy Equipment</button>
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
    <p>Showing <?= count($equipment) ?> of <?= $totalRecords ?> records</p>
  <?php endif; ?>
  
  <table>
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
      <th>Actions</th>
    </tr>
    <?php if (empty($equipment)): ?>
    <tr>
      <td colspan="11" style="text-align: center; padding: 20px; color: #666;">
        No equipment found matching your filters.
      </td>
    </tr>
    <?php else: ?>
      <?php foreach ($equipment as $e): ?>
      <tr>
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
        <td>
          <button class="action-btn" onclick="location.href='inspect_equipment.php?equipment_id=<?= $e['equipment_id'] ?>'">🔍 Inspect</button>
          <button class="action-btn" onclick="location.href='edit_equipment.php?id=<?= $e['equipment_id'] ?>'">✏️ Edit</button>
          <button class="action-btn" onclick="if(confirm('Delete this item?')) location.href='delete_equipment.php?id=<?= $e['equipment_id'] ?>'">🗑️ Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </table>
  
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">First</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Prev</a>
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
        echo '<span>...</span>';
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
        echo '<span>...</span>';
      }
      echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '">' . $totalPages . '</a>';
    }
    ?>
    
    <?php if ($page < $totalPages): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">Last</a>
    <?php else: ?>
      <span class="disabled">Next →</span>
      <span class="disabled">Last</span>
    <?php endif; ?>
    
    <span class="page-jump">
      Jump to page: 
      <input type="number" id="pageJump" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" onkeypress="if(event.key==='Enter') jumpToPage()">
      <button onclick="jumpToPage()">Go</button>
    </span>
  </div>
  <?php endif; ?>
  
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