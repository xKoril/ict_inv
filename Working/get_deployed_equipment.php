<?php
require 'db.php';

// get_deployed_equipment.php
// Fetch all equipment entries for a given ICS/PAR number
$ics = $_GET['ics'] ?? '';
if (!$ics) {
    echo '<p>Invalid ICS/PAR number.</p>';
    exit;
}

// Prepare and execute query
$sql = <<<SQL
SELECT dt.deploy_id_seq,
       e.equipment_id,
       e.equipment_type,
       e.brand,
       e.model,
       e.serial_number,
       e.description_specification
FROM deployment_transactions dt
JOIN equipment e ON dt.equipment_id = e.equipment_id
WHERE dt.ics_par_no = ?
SQL;
$stmt = $pdo->prepare($sql);
$stmt->execute([$ics]);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo '<p>No equipment found for ICS/PAR ' . htmlspecialchars($ics) . '.</p>';
    exit;
}

// Render equipment list
?>
<table>
  <tr>
    <th>Seq</th>
    <th>Equipment ID</th>
    <th>Type</th>
    <th>Brand</th>
    <th>Model</th>
    <th>Serial</th>
    <th>Description</th>
  </tr>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td><?= htmlspecialchars($row['deploy_id_seq']) ?></td>
    <td><?= htmlspecialchars($row['equipment_id']) ?></td>
    <td><?= htmlspecialchars($row['equipment_type']) ?></td>
    <td><?= htmlspecialchars($row['brand']) ?></td>
    <td><?= htmlspecialchars($row['model']) ?></td>
    <td><?= htmlspecialchars($row['serial_number']) ?></td>
    <td><?= htmlspecialchars($row['description_specification']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
