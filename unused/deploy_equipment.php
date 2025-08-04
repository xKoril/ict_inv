<?php
require 'db.php';

// 1. Get equipment ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die('❌ No equipment selected.');
}

// 2. Fetch equipment master data
$stmt = $pdo->prepare("SELECT * FROM equipment WHERE equipment_id = ?");
$stmt->execute([$id]);
$eq = $stmt->fetch();
if (!$eq) {
    die('❌ Equipment not found.');
}

// 3. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Insert deployment record
    $ins = $pdo->prepare(
      "INSERT INTO deployment_records (
         equipment_id, recipient_name, position, office_recipient, received_date, issued_released_by
       ) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([
      $id,
      $_POST['recipient_name'],
      $_POST['position'],
      $_POST['office_recipient'],
      $_POST['received_date'],
      $_POST['issued_released_by']
    ]);

    // Update equipment locator & status
    $upd = $pdo->prepare(
      "UPDATE equipment SET locator = ?, equipment_status = 'Deployed' WHERE equipment_id = ?"
    );
    $upd->execute([
      $_POST['office_recipient'],
      $id
    ]);

    header('Location: inspect_equipment.php?id=' . $id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Deploy Equipment #<?= $eq['equipment_id'] ?></title>
</head>
<body>
  <h1>Deploy Equipment #<?= $eq['equipment_id'] ?> (<?= htmlspecialchars($eq['equipment_type']) ?>)</h1>

  <form method="post">
    <label>Recipient Name:<br>
      <input type="text" name="recipient_name" required>
    </label><br><br>

    <label>Position:<br>
      <input type="text" name="position" required>
    </label><br><br>

    <label>Office Recipient:<br>
      <select name="office_recipient" required>
        <option>DTI-Aklan</option>
        <option>DTI-Antique</option>
        <option>DTI-Capiz</option>
        <option>DTI-Guimaras</option>
        <option>DTI-Iloilo</option>
        <option>DTI-Negros Occidental</option>
        <option>DTI RO - ORD</option>
        <option>DTI RO - MIS</option>
        <option>DTI RO - BDD</option>
        <option>DTI RO - CPD</option>
        <option>DTI RO - FAD</option>
        <option>DTI RO - IDD</option>
        <option>COA</option>
        <option>SBCorp</option>
      </select>
    </label><br><br>

    <label>Received Date:<br>
      <input type="date" name="received_date" required>
    </label><br><br>

    <label>Issued/Released By:<br>
      <input type="text" name="issued_released_by" required>
    </label><br><br>

    <button type="submit">Submit Deployment</button>
    <p><a href="inspect_equipm
