<?php
require 'db.php';

// deploy_equipment.php
// Offices list
$offices = [
    'DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo',
    'DTI-Negros Occ','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD',
    'DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'
];

// Handle deployment POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ics_par_no      = trim($_POST['ics_par_no'] ?? '');
    $date_deployed   = $_POST['date_deployed'] ?? '';
    $time_deployed   = $_POST['time_deployed'] ?? '';
    $custodian       = trim($_POST['custodian'] ?? '');
    $office_cust     = $_POST['office_custodian'] ?? '';
    $remarks         = trim($_POST['remarks'] ?? '');
    $equipment_ids   = $_POST['equipment_ids'] ?? [];

    if ($ics_par_no && $date_deployed && $time_deployed && $custodian && $office_cust && count($equipment_ids)) {
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare(
                "INSERT INTO deployment_transactions 
                 (equipment_id, ics_par_no, date_deployed, time_deployed, custodian, office_custodian, remarks)
                 VALUES (:eid, :ics, :d_dep, :t_dep, :cust, :off, :rem)"
            );
            $updEquip = $pdo->prepare(
                "UPDATE equipment 
                 SET equipment_status='Deployed', ics_par_no = :ics
                 WHERE equipment_id = :eid"
            );
            foreach ($equipment_ids as $eid) {
                $ins->execute([
                    ':eid'   => $eid,
                    ':ics'   => $ics_par_no,
                    ':d_dep' => $date_deployed,
                    ':t_dep' => $time_deployed,
                    ':cust'  => $custodian,
                    ':off'   => $office_cust,
                    ':rem'   => $remarks
                ]);
                $updEquip->execute([
                    ':ics' => $ics_par_no,
                    ':eid' => $eid
                ]);
            }
            $pdo->commit();
            header('Location: borrower.php?message=Deploy+successful');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    } else {
        $error = 'Please complete all deployment fields and select at least one equipment.';
    }
}

// GET filter values for Available list
$a_type_txt = $_GET['a_type_txt'] ?? '';
$a_brand    = $_GET['a_brand']   ?? '';  // Brand filter
$a_model    = $_GET['a_model']    ?? '';

// Fetch Available for Deployment
$where = [];
$params = [];
if ($a_type_txt) { $where[] = 'equipment_type LIKE ?'; $params[] = "%$a_type_txt%"; }
if ($a_brand)    { $where[] = 'brand LIKE ?';            $params[] = "%$a_brand%"; }
if ($a_model)    { $where[] = 'model LIKE ?';            $params[] = "%$a_model%"; }

$sql = "SELECT equipment_id, equipment_category, equipment_type, brand, model, serial_number
        FROM equipment
        WHERE equipment_status = 'Available for Deployment'";
if ($where) {
    $sql .= ' AND ' . implode(' AND ', $where);
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$available = $stmt->fetchAll();

// Defaults for deployment fields
$default_date = date('Y-m-d');
$default_time = date('H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Deploy Equipment</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .form-table { width:100%; border-spacing:20px; margin-bottom:20px; }
    .form-table td { vertical-align: top; }
    label { display:block; margin-bottom:4px; }
    input, select, textarea, button { font-size:0.9em; padding:6px; box-sizing:border-box; }
    .filter-form { margin-bottom:20px; display:flex; gap:12px; background:#f8f9fa; padding:10px; border-radius:5px; }
    .filter-form label { flex:1; }
    .filter-form button, .filter-form a { flex:1; text-align:center; background:#007bff; color:white; border:none; border-radius:3px; padding:6px; text-decoration:none; }
    .filter-form a { background:#6c757d; display:flex; align-items:center; justify-content:center; }
    .dual-container { display:flex; gap:20px; }
    table.lists { width:100%; border-collapse:collapse; font-size:0.85em; }
    table.lists th, table.lists td { border:1px solid #ccc; padding:6px; text-align:left; }
    table.lists th { background:#f4f4f4; }
    .dual-buttons { display:flex; flex-direction:column; justify-content:center; gap:10px; }
    .dual-buttons button { background:#28a745; color:white; border:none; border-radius:3px; padding:6px; cursor:pointer; }
    .dual-buttons button:hover { background:#218838; }
    .submit-btn { margin-top:20px; width:100%; padding:12px; background:#dc3545; color:white; border:none; font-size:1em; border-radius:3px; cursor:pointer; }
    .submit-btn:hover { background:#c82333; }
    .error { color:red; background:#f8d7da; padding:10px; border-radius:3px; margin-bottom:15px; }
  </style>
</head>
<body>
  <h1>Deploy Equipment</h1>
  <?php if (!empty($error)): ?>
    <div class="error"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <!-- Deployment Filter Form -->
  <form method="get" class="filter-form">
    <label>Category:<input name="a_type_txt" value="<?=htmlspecialchars($a_type_txt)?>" placeholder="Search type"></label>
    <label>Brand:<input type="text" name="a_brand" value="<?=htmlspecialchars($a_brand)?>" placeholder="Search brand"></label>
    <label>Model:<input type="text" name="a_model" value="<?=htmlspecialchars($a_model)?>" placeholder="Search model"></label>
    <button type="submit">Filter</button>
    <a href="deploy_equipment.php">Clear</a>
  </form>

  <!-- Deployment Form -->
  <form method="post">
    <table class="form-table">
      <tr>
        <td><label>ICS/PAR No.*<input type="text" name="ics_par_no" required value="<?=htmlspecialchars($_POST['ics_par_no']??'')?>"></label></td>
        <td><label>Date Deployed*<input type="date" name="date_deployed" required value="<?=htmlspecialchars($_POST['date_deployed']??$default_date)?>"></label></td>
        <td><label>Time Deployed*<input type="time" name="time_deployed" required value="<?=htmlspecialchars($_POST['time_deployed']??$default_time)?>"></label></td>
      </tr>
      <tr>
        <td><label>Custodian*<input type="text" name="custodian" required value="<?=htmlspecialchars($_POST['custodian']??'')?>"></label></td>
        <td colspan="2"><label>Office Custodian*<select name="office_custodian" required><option value="">--Select--</option><?php foreach($offices as $o): ?><option value="<?=htmlspecialchars($o)?>" <?=($o==($_POST['office_custodian']??''))?'selected':''?>><?=htmlspecialchars($o)?></option><?php endforeach; ?></select></label></td>
      </tr>
      <tr>
        <td colspan="3"><label>Remarks<textarea name="remarks"><?=htmlspecialchars($_POST['remarks']??'')?></textarea></label></td>
      </tr>
    </table>

    <!-- Dual List Selection -->
    <div class="dual-container">
      <div style="flex:1;">
        <h3>Available Equipment (<?=count($available)?>)</h3>
        <table class="lists">
          <thead><tr><th><input type="checkbox" onclick="toggleAll(this,'availBody')"></th><th>Category</th><th>Type</th><th>Brand</th><th>Model</th><th>Serial</th></tr></thead>
          <tbody id="availBody"><?php foreach($available as $row): ?><tr data-eid="<?=htmlspecialchars($row['equipment_id'])?>"><td><input type="checkbox"></td><td><?=htmlspecialchars($row['equipment_category'])?></td><td><?=htmlspecialchars($row['equipment_type'])?></td><td><?=htmlspecialchars($row['brand'])?></td><td><?=htmlspecialchars($row['model'])?></td><td><?=htmlspecialchars($row['serial_number'])?></td></tr><?php endforeach; ?></tbody>
        </table>
      </div>
      <div class="dual-buttons">
        <button type="button" onclick="bulkAdd()">Add ▶</button>
        <button type="button" onclick="bulkRemove()">◀ Remove</button>
      </div>
      <div style="flex:1;">
        <h3>To Deploy <span id="deployCount">(0)</span></h3>
        <table class="lists">
          <thead><tr><th><input type="checkbox" onclick="toggleAll(this,'selBody')"></th><th>Category</th><th>Type</th><th>Brand</th><th>Model</th><th>Serial</th></tr></thead>
          <tbody id="selBody"></tbody>
        </table>
      </div>
    </div>

    <div id="hiddenInputs"></div>
    <button type="submit" class="submit-btn">Confirm Deployment</button>
  </form>

<script>
  function updateDeployCount() {
    document.getElementById('deployCount').textContent = `(${document.getElementById('selBody').children.length})`;
  }
  function bulkAdd() {
    const avail = document.getElementById('availBody'), sel = document.getElementById('selBody'), hid = document.getElementById('hiddenInputs');
    Array.from(avail.querySelectorAll('input[type=checkbox]:checked')).forEach(cb=>{
      const tr=cb.closest('tr'); cb.checked=false; sel.appendChild(tr);
      const eid=tr.dataset.eid, inp=document.createElement('input'); inp.type='hidden'; inp.name='equipment_ids[]'; inp.value=eid; hid.appendChild(inp);
    }); updateDeployCount();
  }
  function bulkRemove() {
    const avail=document.getElementById('availBody'), sel=document.getElementById('selBody'), hid=document.getElementById('hiddenInputs');
    Array.from(sel.querySelectorAll('input[type=checkbox]:checked')).forEach(cb=>{
      const tr=cb.closest('tr'); cb.checked=false; avail.appendChild(tr);
      const eid=tr.dataset.eid, inp=hid.querySelector(`input[value='${eid}']`); if(inp) hid.removeChild(inp);
    }); updateDeployCount();
  }
  function toggleAll(cb, bodyId) {
    document.getElementById(bodyId).querySelectorAll('input[type=checkbox]').forEach(chk=>chk.checked=cb.checked);
  }
  updateDeployCount();
</script>
</body>
</html>
