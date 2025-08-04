<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare(
      "INSERT INTO equipment (
        po_no, quantity, unit,
        equipment_category, equipment_status, locator,
        equipment_type, brand, model,
        description_specification,
        inventory_item_no_property_no, serial_number,
        estimate_useful_life, date_acquired,
        amount_unit_cost, fund_source, ics_par_no
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([
      $_POST['po_no'],
      $_POST['quantity'],
      $_POST['unit'],
      $_POST['equipment_category'],
      $_POST['equipment_status'],
      $_POST['locator'],
      $_POST['equipment_type'],
      $_POST['brand'],
      $_POST['model'],
      $_POST['description_specification'],
      $_POST['inventory_item_no_property_no'],
      $_POST['serial_number'],
      $_POST['estimate_useful_life'],
      $_POST['date_acquired'],
      $_POST['amount_unit_cost'],
      $_POST['fund_source'],
      $_POST['ics_par_no']
    ]);

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
    form { max-width: 600px; margin: 0 auto; }
    label { display: block; margin-bottom: 8px; }
    input, select, textarea { width: 100%; padding: 6px; margin-top: 4px; }
    button { margin-top: 12px; padding: 8px 16px; }
  </style>
</head>
<body>
  <h1>Add New Equipment</h1>
  <form method="post">
    <label>
      PO No.:<br>
      <input type="text" name="po_no" required>
    </label>

    <label>
      Quantity:<br>
      <input type="number" name="quantity" required>
    </label>

    <label>
      Unit:<br>
      <input type="text" name="unit">
    </label>

    <label>
      Category:<br>
      <select name="equipment_category" required>
        <option value="MIS Equipment">MIS Equipment</option>
        <option value="For DTI Personnel">For DTI Personnel</option>
        <option value="For Disposal">For Disposal</option>
        <option value="Disposed">Disposed</option>
      </select>
    </label>

    <label>
      Status:<br>
      <select name="equipment_status" required>
        <option value="Available for Deployment">Available for Deployment</option>
        <option value="Borrowed">Borrowed</option>
        <option value="Under Repair">Under Repair</option>
        <option value="Disposed">Disposed</option>
      </select>
    </label>

    <label>
      Locator:<br>
      <input type="text" name="locator">
    </label>

    <label>
      Equipment Type:<br>
      <input type="text" name="equipment_type" required>
    </label>

    <label>
      Brand:<br>
      <input type="text" name="brand">
    </label>

    <label>
      Model:<br>
      <input type="text" name="model">
    </label>

    <label>
      Description / Specification:<br>
      <textarea name="description_specification" rows="4"></textarea>
    </label>

    <label>
      Inventory / Property No.:<br>
      <input type="text" name="inventory_item_no_property_no">
    </label>

    <label>
      Serial Number:<br>
      <input type="text" name="serial_number">
    </label>

    <label>
      Estimated Useful Life (years):<br>
      <input type="number" name="estimate_useful_life">
    </label>

    <label>
      Date Acquired:<br>
      <input type="date" name="date_acquired">
    </label>

    <label>
      Unit Cost (Amount):<br>
      <input type="number" step="0.01" name="amount_unit_cost">
    </label>

    <label>
      Fund Source:<br>
      <input type="text" name="fund_source">
    </label>

    <label>
      ICS / PAR No.:<br>
      <input type="text" name="ics_par_no">
    </label>

    <button type="submit">Add Equipment</button>
    <p><a href="index.php">← Back to list</a></p>
  </form>
</body>
</html>