<?php
require 'db.php';

// Offices list
$offices = [
    'DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo',
    'DTI-Negros Occ','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD',
    'DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'
];

// Fetch categories from database
$cat_stmt = $pdo->query("SELECT DISTINCT equipment_category FROM equipment WHERE equipment_category IS NOT NULL AND equipment_category != '' ORDER BY equipment_category");
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch brands from database
$brand_stmt = $pdo->query("SELECT DISTINCT brand FROM equipment WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
$brands = $brand_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle deployment POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deploy') {
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
$a_category = $_GET['a_category'] ?? '';
$a_type_txt = $_GET['a_type_txt'] ?? '';
$a_brand    = $_GET['a_brand']    ?? '';
$a_model    = $_GET['a_model']    ?? '';

// Fetch Available for Deployment
$where = [];
$params = [];
if ($a_category) { $where[] = 'equipment_category=?'; $params[] = $a_category; }
if ($a_type_txt) { $where[] = 'equipment_type LIKE ?'; $params[] = "%$a_type_txt%"; }
if ($a_brand)    { $where[] = 'brand=?';               $params[] = $a_brand; }
if ($a_model)    { $where[] = 'model LIKE ?';          $params[] = "%$a_model%"; }

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
    .form-table { width: 100%; border-spacing: 20px; margin-bottom: 20px; }
    .form-table td { vertical-align: top; }
    label { display: block; margin-bottom: 4px; }
    input, select, textarea, button, a.btn { font-size: 0.9em; padding: 6px; box-sizing: border-box; }
    input, select, textarea { width: 100%; }
    .filter-form { margin-bottom: 20px; display: flex; gap: 12px; background: #f8f9fa; padding: 10px; border-radius: 5px; align-items: end; }
    .filter-form label { flex: 1; }
    .filter-form button, .filter-form a.btn { flex: 0 0 auto; text-decoration: none; text-align:center; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; padding: 6px 12px; }
    .filter-form a.btn { background: #6c757d; display: flex; align-items: center; justify-content: center; }
    .filter-form button:hover, .filter-form a.btn:hover { opacity: 0.9; }
    .dual-container { display: flex; gap: 20px; }
    table.lists { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85em; }
    table.lists th, table.lists td { border: 1px solid #ccc; padding: 6px; }
    table.lists th { background: #f4f4f4; }
    th.select-col, td.select-col { width: 40px; text-align: center; }
    th.type-col, td.type-col { width: 200px; }
    th.brand-col, td.brand-col { width: 140px; }
    th.model-col, td.model-col { width: 140px; }
    .dual-buttons { display: flex; flex-direction: column; justify-content: center; gap: 10px; }
    .dual-buttons button { width: 100%; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; padding: 8px; }
    .dual-buttons button:hover { background: #218838; }
    .submit-btn { margin-top: 20px; width: 100%; background: #dc3545; color: white; border: none; padding: 12px; font-size: 1em; border-radius: 3px; cursor: pointer; }
    .submit-btn:hover { background: #c82333; }
    .error { color: red; background: #f8d7da; padding: 10px; border-radius: 3px; margin-bottom: 15px; }
  </style>
</head>
<body>
  <h1>Deploy Equipment</h1>
  <?php if (!empty($error)): ?>
    <div class="error"><strong><?=htmlspecialchars($error)?></strong></div>
  <?php endif; ?>

  <!-- Main Deployment Form -->
  <form method="post">
    <input type="hidden" name="action" value="deploy">
    
    <table class="form-table">
      <tr>
        <td><label>ICS/PAR No. *</label><input type="text" name="ics_par_no" required value="<?=htmlspecialchars($_POST['ics_par_no']??'')?>"></td>
        <td><label>Date Deployed *</label><input type="date" name="date_deployed" required value="<?=htmlspecialchars($_POST['date_deployed']??$default_date)?>"></td>
        <td><label>Time Deployed *</label><input type="time" name="time_deployed" required value="<?=htmlspecialchars($_POST['time_deployed']??$default_time)?>"></td>
      </tr>
      <tr>
        <td><label>Custodian *</label><input type="text" name="custodian" required value="<?=htmlspecialchars($_POST['custodian']??'')?>"></td>
        <td colspan="2">
          <label>Office Custodian *</label>
          <select name="office_custodian" required>
            <option value="">--Select--</option>
            <?php foreach($offices as $off): ?>
            <option value="<?=htmlspecialchars($off)?>" <?=(($off)==($_POST['office_custodian']??''))?'selected':''?>><?=htmlspecialchars($off)?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <td colspan="3"><label>Remarks</label><textarea name="remarks"><?=htmlspecialchars($_POST['remarks']??'')?></textarea></td>
      </tr>
    </table>
  </form>

  <!-- Filter Equipment Section -->
  <h3>Select Equipment to Deploy</h3>
  <form method="get" class="filter-form">
    <label>Category:
      <select name="a_category">
        <option value="">--All--</option>
        <?php foreach($categories as $cat): ?>
        <option value="<?=htmlspecialchars($cat)?>" <?=($cat==$a_category)?'selected':''?>><?=htmlspecialchars($cat)?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Brand:
      <select name="a_brand">
        <option value="">--All--</option>
        <?php foreach($brands as $brand): ?>
        <option value="<?=htmlspecialchars($brand)?>" <?=($brand==$a_brand)?'selected':''?>><?=htmlspecialchars($brand)?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Type:<input type="text" name="a_type_txt" value="<?=htmlspecialchars($a_type_txt)?>" placeholder="Search type"></label>
    <label>Model:<input type="text" name="a_model" value="<?=htmlspecialchars($a_model)?>" placeholder="Search model"></label>
    <button type="submit">Filter</button>
    <a href="deploy_equipment.php" class="btn">Clear</a>
  </form>

  <!-- Equipment Selection Form -->
  <form method="post">
    <input type="hidden" name="action" value="deploy">
    
    <div class="dual-container">
      <div style="flex:1;">
        <h3>Available Equipment (<?=count($available)?> items)</h3>
        <table class="lists">
          <thead>
            <tr>
              <th class="select-col"><input type="checkbox" onclick="toggleAll(this, 'availBody')"></th>
              <th>Category</th><th class="type-col">Type</th><th class="brand-col">Brand</th><th class="model-col">Model</th><th>Serial</th>
            </tr>
          </thead>
          <tbody id="availBody">
            <?php foreach($available as $row): ?>
            <tr data-eid="<?=$row['equipment_id']?>">
              <td class="select-col"><input type="checkbox"></td>
              <td><?=htmlspecialchars($row['equipment_category'])?></td>
              <td><?=htmlspecialchars($row['equipment_type'])?></td>
              <td><?=htmlspecialchars($row['brand'])?></td>
              <td><?=htmlspecialchars($row['model'])?></td>
              <td><?=htmlspecialchars($row['serial_number'])?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="dual-buttons">
        <button type="button" onclick="bulkAdd()">Add Selected ▶</button>
        <button type="button" onclick="bulkRemove()">◀ Remove Selected</button>
      </div>

      <div style="flex:1;">
        <h3>To Deploy <span id="deployCount">(0 items)</span></h3>
        <table class="lists">
          <thead>
            <tr>
              <th class="select-col"><input type="checkbox" onclick="toggleAll(this, 'selBody')"></th>
              <th>Category</th><th class="type-col">Type</th><th class="brand-col">Brand</th><th class="model-col">Model</th><th>Serial</th>
            </tr>
          </thead>
          <tbody id="selBody"></tbody>
        </table>
      </div>
    </div>

    <div id="hiddenInputs"></div>
    
    <!-- Copy deployment form fields to preserve data -->
    <input type="hidden" name="ics_par_no" value="<?=htmlspecialchars($_POST['ics_par_no']??'')?>">
    <input type="hidden" name="date_deployed" value="<?=htmlspecialchars($_POST['date_deployed']??$default_date)?>">
    <input type="hidden" name="time_deployed" value="<?=htmlspecialchars($_POST['time_deployed']??$default_time)?>">
    <input type="hidden" name="custodian" value="<?=htmlspecialchars($_POST['custodian']??'')?>">
    <input type="hidden" name="office_custodian" value="<?=htmlspecialchars($_POST['office_custodian']??'')?>">
    <input type="hidden" name="remarks" value="<?=htmlspecialchars($_POST['remarks']??'')?>">
    
    <button type="submit" class="submit-btn">Confirm Deployment</button>
  </form>

  <script>
    function updateDeployCount() {
      const count = document.getElementById('selBody').children.length;
      document.getElementById('deployCount').textContent = `(${count} items)`;
    }

    function bulkAdd() {
      const avail = document.getElementById('availBody');
      const sel   = document.getElementById('selBody');
      const hidden = document.getElementById('hiddenInputs');
      
      Array.from(avail.querySelectorAll('input[type=checkbox]:checked')).forEach(cb => {
        const tr = cb.closest('tr'); 
        cb.checked = false;
        sel.appendChild(tr);
        
        const eid = tr.dataset.eid;
        const inp = document.createElement('input');
        inp.type = 'hidden'; 
        inp.name = 'equipment_ids[]'; 
        inp.value = eid;
        hidden.appendChild(inp);
      });
      updateDeployCount();
    }

    function bulkRemove() {
      const avail = document.getElementById('availBody');
      const sel   = document.getElementById('selBody');
      const hidden = document.getElementById('hiddenInputs');
      
      Array.from(sel.querySelectorAll('input[type=checkbox]:checked')).forEach(cb => {
        const tr = cb.closest('tr'); 
        cb.checked = false;
        avail.appendChild(tr);
        
        const eid = tr.dataset.eid;
        const inp = hidden.querySelector(`input[value='${eid}']`);
        if (inp) hidden.removeChild(inp);
      });
      updateDeployCount();
    }

    function toggleAll(cb, bodyId) {
      document.getElementById(bodyId).querySelectorAll('input[type=checkbox]').forEach(chk => 
        chk.checked = cb.checked
      );
    }

    // Initialize count on page load
    updateDeployCount();
  </script>
</body>
</html>