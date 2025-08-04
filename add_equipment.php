<?php
require 'db.php';

/**
 * Retrieve all ENUM values for a given table column
 */
function getEnumValues(PDO $pdo, string $table, string $column): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!preg_match("/^enum\\('(.*)'\\)$/", $row['Type'] ?? '', $matches)) {
        return [];
    }
    return array_map(fn($val) => trim($val, "'"), explode("','", $matches[1]));
}

// Fetch dynamic dropdown data
$categories = getEnumValues($pdo, 'equipment', 'equipment_category');
$statuses   = getEnumValues($pdo, 'equipment', 'equipment_status');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... insertion logic here ...
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add New Equipment</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 0; }
    .form-container {
      max-width: 700px;
      margin: 40px auto;
      padding: 20px;
      background: #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border-radius: 8px;
    }
    h1 { text-align: center; margin-bottom: 20px; }
    form label { display: block; margin-bottom: 12px; font-weight: 600; }
    form input, form select, form textarea {
      width: 100%; padding: 10px; font-size: 1em; margin-top: 4px;
      border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
    }
    form button {
      display: block; width: 100%; padding: 12px; margin-top: 20px;
      font-size: 1em; background: #0056b3; color: #fff; border: none; border-radius: 4px; cursor: pointer;
    }
    form button:hover { background: #004494; }
    .back-link { display: block; text-align: center; margin-top: 16px; color: #0056b3; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Add New Equipment</h1>
    <form method="post">
      <label>PO No.:<input type="text" name="po_no" required></label>
      <label>Quantity:<input type="number" name="quantity" required></label>
      <label>Unit:<input type="text" name="unit"></label>

      <label>Equipment Category:
        <select name="equipment_category" required>
          <option value="">--Select Category--</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>" <?= ($cat===($_POST['equipment_category']??''))?'selected':'' ?>>
            <?= htmlspecialchars($cat) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Equipment Status:
        <select name="equipment_status" required>
          <?php foreach ($statuses as $st): ?>
          <option value="<?= htmlspecialchars($st) ?>" <?= ($st===($_POST['equipment_status']??''))?'selected':'' ?>>
            <?= htmlspecialchars($st) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Locator:<input type="text" name="locator"></label>
      <label>Equipment Type:<input type="text" name="equipment_type" required></label>
      <label>Brand:<input type="text" name="brand"></label>
      <label>Model:<input type="text" name="model"></label>

      <label>Description / Specification:<textarea name="description_specification" rows="3"></textarea></label>
      <label>Inventory / Property No.:<input type="text" name="inventory_item_no_property_no"></label>
      <label>Serial Number:<input type="text" name="serial_number"></label>
      <label>Estimated Useful Life:<input type="number" name="estimate_useful_life"></label>
      <label>Date Acquired:<input type="date" name="date_acquired"></label>
      <label>Unit Cost:<input type="number" step="0.01" name="amount_unit_cost"></label>
      <label>Fund Source:<input type="text" name="fund_source"></label>
      <label>ICS / PAR No.:<input type="text" name="ics_par_no"></label>

      <button type="submit">Add Equipment</button>
    </form>

    <a href="index.php" class="back-link">← Back to list</a>
  </div>
</body>
</html>
