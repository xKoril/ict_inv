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
$sql .= " ORDER BY equipment_id DESC LIMIT $limit OFFSET $offset";

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
    input[type="text"], input[type="number"], select { padding: 6px; font-size: 1em; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #f4f4f4; }
    .action-btn { margin-right: 4px; padding: 4px 8px; font-size: 0.9em; border: 1px solid #ccc; background: #fff; cursor: pointer; }
    .action-btn:hover { background: #f0f0f0; }
    .pagination a { margin-right: 8px; color: #0056b3; text-decoration: none; }
    .pagination span { margin-right: 8px; }
    .pagination .disabled { color: #aaa; cursor: default; text-decoration: none; }
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
    <label>Model:
      <input type="text" name="model" value="<?= htmlspecialchars($fm) ?>" placeholder="Search model">
    </label>
    <button type="submit">Filter</button>
    <a href="index.php">Clear</a>
  </form>
  <table>
    <tr>
      <th>Equipment ID</th>
      <th>Category</th>
      <th>Status</th>
      <th>Equipment Type</th>
      <th>Brand</th>
      <th>Model</th>
      <th>Description</th>
      <th>Unit</th>
      <th>Quantity</th>
      <th>Serial Number</th>
      <th>Actions</th>
    </tr>
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
  </table>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Prev</a>
    <?php else: ?>
      <span class="disabled">← Prev</span>
    <?php endif; ?>
    <span>Page <?= $page ?></span>
    <?php if (count($equipment) === $limit): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
    <?php else: ?>
      <span class="disabled">Next →</span>
    <?php endif; ?>
  </div>
</body>
</html>
