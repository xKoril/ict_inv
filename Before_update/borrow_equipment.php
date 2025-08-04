<?php
require 'db.php';

// Offices list
$offices = ['DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo','DTI-Negros Occ','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD','DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'];

// Handle POST submission for borrowing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {
    $slip_no       = trim($_POST['slip_no'] ?? '');
    $borrower      = trim($_POST['borrower'] ?? '');
    $office        = $_POST['office'] ?? '';
    $purpose       = trim($_POST['purpose'] ?? '');
    $date_borrowed = $_POST['date_borrowed'] ?? '';
    $time_borrowed = $_POST['time_borrowed'] ?? '';
    $due_date      = $_POST['due_date'] ?? null;
    $equipment_ids = $_POST['equipment_ids'] ?? [];

    if ($slip_no && $borrower && $office && $purpose && $date_borrowed && $time_borrowed && count($equipment_ids)) {
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare(
                "INSERT INTO borrow_transactions (equipment_id, date_borrowed, time_borrowed, due_date, purpose, borrower, office_borrower, slip_no) VALUES (:equip, :d_b, :t_b, :due, :purp, :brw, :off, :slip)"
            );
            $upd = $pdo->prepare("UPDATE equipment SET equipment_status='Borrowed' WHERE equipment_id = :equip");
            foreach ($equipment_ids as $eid) {
                $ins->execute([
                    ':equip' => $eid,
                    ':d_b'   => $date_borrowed,
                    ':t_b'   => $time_borrowed,
                    ':due'   => $due_date,
                    ':purp'  => $purpose,
                    ':brw'   => $borrower,
                    ':off'   => $office,
                    ':slip'  => $slip_no,
                ]);
                $upd->execute([':equip' => $eid]);
            }
            $pdo->commit();
            header('Location: borrower.php?message=Borrow+successful');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields and select at least one item.';
    }
}

// GET filter values (independent of form submission)
$a_type     = $_GET['a_type']     ?? '';
$a_status   = $_GET['a_status']   ?? '';
$a_type_txt = $_GET['a_type_txt'] ?? '';
$a_model    = $_GET['a_model']    ?? '';

// Build Available query
$where = [];
$params = [];
if ($a_type)     { $where[] = 'equipment_category=?'; $params[] = $a_type; }
if ($a_status)   { $where[] = 'equipment_status=?';   $params[] = $a_status; }
if ($a_type_txt) { $where[] = 'equipment_type LIKE ?';  $params[] = "%$a_type_txt%"; }
if ($a_model)    { $where[] = 'model LIKE ?';           $params[] = "%$a_model%"; }

$sql = 'SELECT equipment_id, equipment_category, equipment_status, equipment_type, brand, model, serial_number
        FROM equipment
        WHERE equipment_status = "Available for Deployment"';
if ($where) {
    $sql .= ' AND ' . implode(' AND ', $where);
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$available = $stmt->fetchAll();

// Defaults for slip fields
$default_date = date('Y-m-d');
$default_time = date('H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Borrow Equipment</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .form-table { width: 100%; border-spacing: 20px; margin-bottom: 20px; }
    .form-table td { vertical-align: top; }
    label { display: block; margin-bottom: 4px; }
    input, select, textarea, button, a.btn { font-size: 0.9em; padding: 6px; box-sizing: border-box; }
    input, select, textarea { width: 100%; }
    .filter-form { margin-bottom: 20px; display: flex; gap: 12px; background: #f8f9fa; padding: 15px; border-radius: 5px; }
    .filter-form label { flex: 1; }
    .filter-form button, .filter-form a.btn { flex: 1; text-decoration: none; text-align:center; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
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
    .dual-buttons button { width: 100%; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; }
    .dual-buttons button:hover { background: #218838; }
    .submit-btn { margin-top: 20px; width: 100%; background: #dc3545; color: white; border: none; padding: 12px; font-size: 1em; border-radius: 3px; cursor: pointer; }
    .submit-btn:hover { background: #c82333; }
    .error { color: red; background: #f8d7da; padding: 10px; border-radius: 3px; margin-bottom: 15px; }
  </style>
</head>
<body>
  <h1>Borrow Equipment</h1>
  <?php if (!empty($error)): ?>
    <div class="error"><strong><?=htmlspecialchars($error)?></strong></div>
  <?php endif; ?>

  <!-- Separate Filter Form -->
  <form method="get" class="filter-form">
    <label>Category:
      <select name="a_type">
        <option value="">--All--</option>
        <?php foreach(['MIS Equipment','For DTI Personnel'] as $cat): ?>
        <option value="<?=htmlspecialchars($cat)?>" <?=($cat==$a_type)?'selected':''?>><?=htmlspecialchars($cat)?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Status:
      <select name="a_status">
        <option value="">--All--</option>
        <option value="Available for Deployment" <?=('Available for Deployment'==$a_status)?'selected':''?>>Available for Deployment</option>
      </select>
    </label>
    <label>Type:<input type="text" name="a_type_txt" value="<?=htmlspecialchars($a_type_txt)?>" placeholder="Search type"></label>
    <label>Model:<input type="text" name="a_model" value="<?=htmlspecialchars($a_model)?>" placeholder="Search model"></label>
    <button type="submit">Filter</button>
    <a href="borrow_equipment.php" class="btn">Clear</a>
  </form>

  <!-- Main Borrowing Form -->
  <form method="post">
    <input type="hidden" name="action" value="borrow">
    
    <table class="form-table">
      <tr>
        <td><label>Slip Number *</label><input type="text" name="slip_no" required value="<?=htmlspecialchars($_POST['slip_no']??'')?>"></td>
        <td><label>Borrower Name *</label><input type="text" name="borrower" required value="<?=htmlspecialchars($_POST['borrower']??'')?>"></td>
        <td>
          <label>Office *</label>
          <select name="office" required>
            <option value="">--Select--</option>
            <?php foreach($offices as $off): ?>
            <option value="<?=htmlspecialchars($off)?>" <?=(($off)==($_POST['office']??''))?'selected':''?>><?=htmlspecialchars($off)?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <td colspan="3"><label>Purpose *</label><textarea name="purpose" required><?=htmlspecialchars($_POST['purpose']??'')?></textarea></td>
      </tr>
      <tr>
        <td><label>Date Borrowed *</label><input type="date" name="date_borrowed" required value="<?=htmlspecialchars($_POST['date_borrowed']??$default_date)?>"></td>
        <td><label>Due Date</label><input type="date" name="due_date" value="<?=htmlspecialchars($_POST['due_date']??'')?>"></td>
        <td><label>Time Borrowed *</label><input type="time" name="time_borrowed" required value="<?=htmlspecialchars($_POST['time_borrowed']??$default_time)?>"></td>
      </tr>
    </table>

    <div class="dual-container">
      <div style="flex:1;">
        <h3>Available Equipment (<?=count($available)?> items)</h3>
        <table class="lists">
          <thead>
            <tr>
              <th class="select-col"><input type="checkbox" onclick="toggleAll(this, 'availBody')"></th>
              <th>Category</th><th>Status</th><th class="type-col">Type</th><th class="brand-col">Brand</th><th class="model-col">Model</th><th>Serial</th>
            </tr>
          </thead>
          <tbody id="availBody">
            <?php foreach($available as $row): ?>
            <tr data-eid="<?=$row['equipment_id']?>">
              <td class="select-col"><input type="checkbox"></td>
              <td><?=htmlspecialchars($row['equipment_category'])?></td>
              <td><?=htmlspecialchars($row['equipment_status'])?></td>
              <td><?=htmlspecialchars($row['equipment_type'])?></td>
              <td><?=htmlspecialchars($row['brand'])?></td>
              <td><?=htmlspecialchars($row['model'])?></td>
              <td><?=htmlspecialchars($row['serial_number'])?></td>
            </tr>
            <?php endforeach;?>
          </tbody>
        </table>
      </div>

      <div class="dual-buttons">
        <button type="button" onclick="bulkAdd()">Add Selected ▶</button>
        <button type="button" onclick="bulkRemove()">◀ Remove Selected</button>
      </div>

      <div style="flex:1;">
        <h3>To Borrow <span id="borrowCount">(0 items)</span></h3>
        <table class="lists">
          <thead>
            <tr>
              <th class="select-col"><input type="checkbox" onclick="toggleAll(this, 'selBody')"></th>
              <th>Category</th><th>Status</th><th class="type-col">Type</th><th class="brand-col">Brand</th><th class="model-col">Model</th><th>Serial</th>
            </tr>
          </thead>
          <tbody id="selBody"></tbody>
        </table>
      </div>
    </div>

    <div id="hiddenInputs"></div>
    <button type="submit" class="submit-btn">Confirm Borrow Request</button>
  </form>

  <script>
    function updateBorrowCount() {
      const count = document.getElementById('selBody').children.length;
      document.getElementById('borrowCount').textContent = `(${count} items)`;
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
      updateBorrowCount();
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
      updateBorrowCount();
    }

    function toggleAll(cb, bodyId) {
      document.getElementById(bodyId).querySelectorAll('input[type=checkbox]').forEach(chk => 
        chk.checked = cb.checked
      );
    }

    // Initialize count on page load
    updateBorrowCount();
  </script>
</body>
</html>