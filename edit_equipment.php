<?php
require 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM equipment WHERE equipment_id = ?');
$stmt->execute([$id]);
$eq = $stmt->fetch();

if (!$eq) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update equipment table (removed ics_par_no)
        $upd = $pdo->prepare(
            'UPDATE equipment SET
                po_no=?, quantity=?, unit=?, equipment_category=?, equipment_status=?, locator=?, equipment_type=?, brand=?, model=?, description_specification=?, inventory_item_no_property_no=?, serial_number=?, estimate_useful_life=?, date_acquired=?, amount_unit_cost=?, fund_source=?
             WHERE equipment_id=?'
        );
        $equipment_result = $upd->execute([
            $_POST['po_no'], $_POST['quantity'], $_POST['unit'], $_POST['equipment_category'], $_POST['equipment_status'], $_POST['locator'], $_POST['equipment_type'], $_POST['brand'], $_POST['model'], $_POST['description_specification'], $_POST['inventory_item_no_property_no'], $_POST['serial_number'], $_POST['estimate_useful_life'], $_POST['date_acquired'], $_POST['amount_unit_cost'], $_POST['fund_source'], $id
        ]);
        
        if (!$equipment_result) {
            throw new Exception('Failed to update equipment');
        }
        
        $success = "Equipment updated successfully!";
        
        // Refresh the data
        $stmt->execute([$id]);
        $eq = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get the latest ICS/PAR No. from deployment history for display only
$latest_deployment_stmt = $pdo->prepare('
    SELECT ics_par_no, date_deployed, custodian, office_custodian
    FROM deployment_transactions 
    WHERE equipment_id = ? 
    ORDER BY date_deployed DESC, time_deployed DESC 
    LIMIT 1
');
$latest_deployment_stmt->execute([$id]);
$latest_deployment = $latest_deployment_stmt->fetch();
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
    
    .current-deployment {
      background-color: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
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
    <h1>Edit Equipment #<?= $eq['equipment_id'] ?></h1>
    
    <div class="info-note">
      ℹ️ <strong>Note:</strong> ICS/PAR assignments are managed through the Deployment system. Use "Transfer Equipment" to change deployments.
    </div>
    
    <?php if ($latest_deployment): ?>
    <div class="current-deployment">
      📋 <strong>Current Deployment:</strong> ICS/PAR <?= htmlspecialchars($latest_deployment['ics_par_no']) ?> 
      - <?= htmlspecialchars($latest_deployment['custodian']) ?> 
      (<?= htmlspecialchars($latest_deployment['office_custodian']) ?>) 
      - <?= htmlspecialchars($latest_deployment['date_deployed']) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert success">
        ✅ <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert error">
        ❌ <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    
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
      <button type="submit">Update Equipment</button>
    </form>
    <a href="index.php" class="back-link">← Back to list</a>
  </div>
</body>
</html>