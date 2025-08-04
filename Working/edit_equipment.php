<?php
require 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM equipment WHERE equipment_id = ?');
$stmt->execute([$id]);
$eq = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $upd = $pdo->prepare(
        'UPDATE equipment SET
            po_no=?, quantity=?, unit=?, equipment_category=?, equipment_status=?, locator=?, equipment_type=?, brand=?, model=?, description_specification=?, inventory_item_no_property_no=?, serial_number=?, estimate_useful_life=?, date_acquired=?, amount_unit_cost=?, fund_source=?, ics_par_no=?
         WHERE equipment_id=?'
    );
    $upd->execute([
        $_POST['po_no'], $_POST['quantity'], $_POST['unit'], $_POST['equipment_category'], $_POST['equipment_status'], $_POST['locator'], $_POST['equipment_type'], $_POST['brand'], $_POST['model'], $_POST['description_specification'], $_POST['inventory_item_no_property_no'], $_POST['serial_number'], $_POST['estimate_useful_life'], $_POST['date_acquired'], $_POST['amount_unit_cost'], $_POST['fund_source'], $_POST['ics_par_no'], $id
    ]);
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Equipment #<?= $eq['equipment_id'] ?></title>
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
    <h1>Edit Equipment #<?= $eq['equipment_id'] ?></h1>
    <form method="post">
      <label>PO No.:<input type="text" name="po_no" value="<?= htmlspecialchars($eq['po_no']) ?>" required></label>
      <label>Quantity:<input type="number" name="quantity" value="<?= htmlspecialchars($eq['quantity']) ?>" required></label>
      <label>Unit:<input type="text" name="unit" value="<?= htmlspecialchars($eq['unit']) ?>"></label>
      <label>Equipment Category:<select name="equipment_category" required>
        <option<?= $eq['equipment_category']==='MIS Equipment'? ' selected':'' ?>>MIS Equipment</option>
        <option<?= $eq['equipment_category']==='For DTI Personnel'? ' selected':'' ?>>For DTI Personnel</option>
        <option<?= $eq['equipment_category']==='For Disposal'? ' selected':'' ?>>For Disposal</option>
        <option<?= $eq['equipment_category']==='Disposed'? ' selected':'' ?>>Disposed</option>
      </select></label>
      <label>Equipment Status:<select name="equipment_status" required>
        <option<?= $eq['equipment_status']==='Available for Deployment'? ' selected':'' ?>>Available for Deployment</option>
        <option<?= $eq['equipment_status']==='Borrowed'? ' selected':'' ?>>Borrowed</option>
        <option<?= $eq['equipment_status']==='Deployed'? ' selected':'' ?>>Deployed</option>
        <option<?= $eq['equipment_status']==='Under Repair'? ' selected':'' ?>>Under Repair</option>
        <option<?= $eq['equipment_status']==='For Disposal'? ' selected':'' ?>>For Disposal</option>
        <option<?= $eq['equipment_status']==='Disposed'? ' selected':'' ?>>Disposed</option>
      </select></label>
      <label>Locator:<input type="text" name="locator" value="<?= htmlspecialchars($eq['locator']) ?>"></label>
      <label>Equipment Type:<input type="text" name="equipment_type" value="<?= htmlspecialchars($eq['equipment_type']) ?>" required></label>
      <label>Brand:<input type="text" name="brand" value="<?= htmlspecialchars($eq['brand']) ?>"></label>
      <label>Model:<input type="text" name="model" value="<?= htmlspecialchars($eq['model']) ?>"></label>
      <label>Description / Specification:<textarea name="description_specification"><?= htmlspecialchars($eq['description_specification']) ?></textarea></label>
      <label>Inventory / Property No.:<input type="text" name="inventory_item_no_property_no" value="<?= htmlspecialchars($eq['inventory_item_no_property_no']) ?>"></label>
      <label>Serial Number:<input type="text" name="serial_number" value="<?= htmlspecialchars($eq['serial_number']) ?>"></label>
      <label>Estimated Useful Life:<input type="number" name="estimate_useful_life" value="<?= htmlspecialchars($eq['estimate_useful_life']) ?>"></label>
      <label>Date Acquired:<input type="date" name="date_acquired" value="<?= htmlspecialchars($eq['date_acquired']) ?>"></label>
      <label>Unit Cost:<input type="number" step="0.01" name="amount_unit_cost" value="<?= htmlspecialchars($eq['amount_unit_cost']) ?>"></label>
      <label>Fund Source:<input type="text" name="fund_source" value="<?= htmlspecialchars($eq['fund_source']) ?>"></label>
      <label>ICS / PAR No.:<input type="text" name="ics_par_no" value="<?= htmlspecialchars($eq['ics_par_no']) ?>"></label>
      <button type="submit">Update Equipment</button>
    </form>
    <a href="index.php" class="back-link">← Back to list</a>
  </div>
</body>
</html>