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

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Prepare the SQL statement (removed ics_par_no)
        $sql = "INSERT INTO equipment (
            po_no, 
            quantity, 
            unit, 
            equipment_category, 
            equipment_status, 
            locator, 
            equipment_type, 
            brand, 
            model, 
            description_specification, 
            inventory_item_no_property_no, 
            serial_number, 
            estimate_useful_life, 
            date_acquired, 
            amount_unit_cost, 
            fund_source
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        
        // Execute with the form data (removed ics_par_no)
        $result = $stmt->execute([
            $_POST['po_no'] ?? '',
            $_POST['quantity'] ?? 0,
            $_POST['unit'] ?? '',
            $_POST['equipment_category'] ?? '',
            $_POST['equipment_status'] ?? '',
            $_POST['locator'] ?? '',
            $_POST['equipment_type'] ?? '',
            $_POST['brand'] ?? '',
            $_POST['model'] ?? '',
            $_POST['description_specification'] ?? '',
            $_POST['inventory_item_no_property_no'] ?? '',
            $_POST['serial_number'] ?? '',
            $_POST['estimate_useful_life'] ?? null,
            $_POST['date_acquired'] ?? null,
            $_POST['amount_unit_cost'] ?? null,
            $_POST['fund_source'] ?? ''
        ]);
        
        if ($result) {
            $success = true;
            // Clear form data after successful insertion
            $_POST = [];
            // Redirect after a short delay to show success message
            header("refresh:2;url=index.php");
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
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
    
    .alert {
      padding: 12px;
      margin-bottom: 20px;
      border-radius: 4px;
      text-align: center;
    }
    .alert.success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .alert.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .info-note {
      background-color: #d1ecf1;
      color: #0c5460;
      border: 1px solid #bee5eb;
      padding: 12px;
      margin-bottom: 20px;
      border-radius: 4px;
      font-size: 0.9em;
    }
    
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
    
    <div class="info-note">
      ℹ️ <strong>Note:</strong> ICS/PAR No. will be assigned when the equipment is deployed. New equipment should typically have status "Available for Deployment".
    </div>
    
    <?php if ($success): ?>
      <div class="alert success">
        ✅ Equipment added successfully! Redirecting to main page...
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert error">
        ❌ <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    
    <form method="post">
      <label>PO No.:<input type="text" name="po_no" value="<?= htmlspecialchars($_POST['po_no'] ?? '') ?>" required></label>
      <label>Quantity:<input type="number" name="quantity" value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>" required></label>
      <label>Unit:<input type="text" name="unit" value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>"></label>

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
          <option value="">--Select Status--</option>
          <?php foreach ($statuses as $st): ?>
          <option value="<?= htmlspecialchars($st) ?>" <?= ($st===($_POST['equipment_status']??''))?'selected':'' ?>>
            <?= htmlspecialchars($st) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Locator:<input type="text" name="locator" value="<?= htmlspecialchars($_POST['locator'] ?? '') ?>"></label>
      <label>Equipment Type:<input type="text" name="equipment_type" value="<?= htmlspecialchars($_POST['equipment_type'] ?? '') ?>" required></label>
      <label>Brand:<input type="text" name="brand" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>"></label>
      <label>Model:<input type="text" name="model" value="<?= htmlspecialchars($_POST['model'] ?? '') ?>"></label>

      <label>Description / Specification:<textarea name="description_specification" rows="3"><?= htmlspecialchars($_POST['description_specification'] ?? '') ?></textarea></label>
      <label>Inventory / Property No.:<input type="text" name="inventory_item_no_property_no" value="<?= htmlspecialchars($_POST['inventory_item_no_property_no'] ?? '') ?>"></label>
      <label>Serial Number:<input type="text" name="serial_number" value="<?= htmlspecialchars($_POST['serial_number'] ?? '') ?>"></label>
      <label>Estimated Useful Life:<input type="number" name="estimate_useful_life" value="<?= htmlspecialchars($_POST['estimate_useful_life'] ?? '') ?>"></label>
      <label>Date Acquired:<input type="date" name="date_acquired" value="<?= htmlspecialchars($_POST['date_acquired'] ?? '') ?>"></label>
      <label>Unit Cost:<input type="number" step="0.01" name="amount_unit_cost" value="<?= htmlspecialchars($_POST['amount_unit_cost'] ?? '') ?>"></label>
      <label>Fund Source:<input type="text" name="fund_source" value="<?= htmlspecialchars($_POST['fund_source'] ?? '') ?>"></label>

      <button type="submit">Add Equipment</button>
    </form>

    <a href="index.php" class="back-link">← Back to list</a>
  </div>
</body>
</html>