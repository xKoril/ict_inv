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
            // UPDATED: Now includes locator update
            $updEquip = $pdo->prepare(
                "UPDATE equipment 
                 SET equipment_status='Deployed', ics_par_no = :ics, locator = :locator
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
                // UPDATED: Now updates locator with office_custodian value
                $updEquip->execute([
                    ':ics' => $ics_par_no,
                    ':locator' => $office_cust, // NEW: Updates locator
                    ':eid' => $eid
                ]);
            }
            $pdo->commit();
            header('Location: deploy_equipment.php?success=Deployment+successful');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    } else {
        $error = 'Please complete all deployment fields and select at least one equipment.';
    }
}

// Fetch ALL Available equipment (no server-side filtering)
$sql = "SELECT equipment_id, equipment_category, equipment_type, brand, model, serial_number
        FROM equipment
        WHERE equipment_status = 'Available for Deployment'
        ORDER BY equipment_category, equipment_type, brand, model";
$stmt = $pdo->query($sql);
$available = $stmt->fetchAll();
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
    .filter-form { margin-bottom: 20px; display: flex; gap: 12px; background: #f8f9fa; padding: 10px; border-radius: 5px; align-items: end; flex-wrap: wrap; }
    .filter-form label { flex: 1; min-width: 120px; }
    .filter-form button, .filter-form a.btn { flex: 0 0 auto; text-decoration: none; text-align:center; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; padding: 6px 12px; }
    .filter-form a.btn { background: #6c757d; display: flex; align-items: center; justify-content: center; }
    .filter-form button:hover, .filter-form a.btn:hover { opacity: 0.9; }
    .dual-container { display: flex; gap: 20px; }
    
    /* Enhanced table container styling */
    .table-container {
      border: 1px solid #ccc;
      border-radius: 5px;
      overflow: hidden;
      background: white;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .table-header {
      background: #f8f9fa;
      padding: 10px;
      border-bottom: 1px solid #ccc;
      font-weight: bold;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .table-wrapper {
      height: 400px; /* Fixed height for scrollable area */
      overflow-y: auto;
      overflow-x: hidden;
    }
    
    table.lists { 
      width: 100%; 
      border-collapse: collapse; 
      text-align: left; 
      font-size: 0.85em; 
      margin: 0;
    }
    
    table.lists th, table.lists td { 
      border: 1px solid #e0e0e0; 
      padding: 8px; 
      vertical-align: middle;
    }
    
    table.lists th { 
      background: #f4f4f4; 
      position: sticky;
      top: 0;
      z-index: 10;
      font-weight: bold;
    }
    
    table.lists tbody tr:hover {
      background-color: #f8f9fa;
    }
    
    table.lists tbody tr:nth-child(even) {
      background-color: #fafafa;
    }
    
    th.select-col, td.select-col { width: 40px; text-align: center; }
    th.type-col, td.type-col { width: 200px; }
    th.brand-col, td.brand-col { width: 140px; }
    th.model-col, td.model-col { width: 140px; }
    
    /* Custom scrollbar styling */
    .table-wrapper::-webkit-scrollbar {
      width: 8px;
    }
    
    .table-wrapper::-webkit-scrollbar-track {
      background: #f1f1f1;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb:hover {
      background: #a1a1a1;
    }
    
    /* Empty state styling */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #666;
      font-style: italic;
      background: #f9f9f9;
    }
    
    .dual-buttons { display: flex; flex-direction: column; justify-content: center; gap: 10px; }
    .dual-buttons button { width: 100%; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; padding: 8px; transition: background-color 0.2s; }
    .dual-buttons button:hover { background: #218838; }
    .submit-btn { margin-top: 20px; width: 100%; background: #dc3545; color: white; border: none; padding: 12px; font-size: 1em; border-radius: 3px; cursor: pointer; transition: background-color 0.2s; }
    .submit-btn:hover { background: #c82333; }
    .error { color: red; background: #f8d7da; padding: 10px; border-radius: 3px; margin-bottom: 15px; }
    .success { color: green; background: #d4edda; padding: 10px; border-radius: 3px; margin-bottom: 15px; border: 1px solid #c3e6cb; }
    
    /* Row count badge */
    .count-badge {
      background: #007bff;
      color: white;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.8em;
      font-weight: normal;
    }

    /* Hidden row styling */
    .hidden-row {
      display: none !important;
    }
  </style>
</head>
<body>
  <h1>Deploy Equipment</h1>
  
  <!-- Return Button -->
  <div style="margin-bottom: 20px;">
    <a href="deployment.php" class="btn" style="background: #6c757d; color: white; text-decoration: none; padding: 8px 16px; border-radius: 3px; display: inline-block;">← Back to Deployment Dashboard</a>
  </div>

  <!-- Success Message -->
  <?php if (!empty($_GET['success'])): ?>
    <div class="success"><strong><?=htmlspecialchars($_GET['success'])?></strong></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="error"><strong><?=htmlspecialchars($error)?></strong></div>
  <?php endif; ?>

  <!-- Single Main Form for Everything -->
  <form method="post" id="mainForm">
    <input type="hidden" name="action" value="deploy">
    
    <table class="form-table">
      <tr>
        <td><label>ICS/PAR No. *</label><input type="text" name="ics_par_no" required value=""></td>
        <td><label>Date Deployed *</label><input type="date" name="date_deployed" required value="<?=date('Y-m-d')?>"></td>
        <td><label>Time Deployed *</label><input type="time" name="time_deployed" required value="<?=date('H:i')?>"></td>
      </tr>
      <tr>
        <td><label>Custodian *</label><input type="text" name="custodian" required value=""></td>
        <td colspan="2">
          <label>Office Custodian * <small>(This will also update equipment locator)</small></label>
          <select name="office_custodian" required>
            <option value="">--Select--</option>
            <?php foreach($offices as $off): ?>
            <option value="<?=htmlspecialchars($off)?>"><?=htmlspecialchars($off)?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <td colspan="3"><label>Remarks</label><textarea name="remarks"></textarea></td>
      </tr>
    </table>

    <!-- Filter Equipment Section -->
    <h3>Select Equipment to Deploy</h3>
    <div class="filter-form">
      <label>Category:
        <select id="filterCategory">
          <option value="">--All--</option>
          <?php foreach($categories as $cat): ?>
          <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Brand:
        <select id="filterBrand">
          <option value="">--All--</option>
          <?php foreach($brands as $brand): ?>
          <option value="<?=htmlspecialchars($brand)?>"><?=htmlspecialchars($brand)?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Type:<input type="text" id="filterType" placeholder="Search type"></label>
      <label>Model:<input type="text" id="filterModel" placeholder="Search model"></label>
      <label>Serial:<input type="text" id="filterSerial" placeholder="Search serial"></label>
      <button type="button" onclick="applyFilters()">Filter</button>
      <button type="button" onclick="clearFilters()">Clear</button>
    </div>

    <!-- Equipment Selection -->
    <div class="dual-container">
      <div style="flex:1;">
        <div class="table-container">
          <div class="table-header">
            Available Equipment
            <span class="count-badge" id="availableCount"><?=count($available)?> items</span>
          </div>
          <div class="table-wrapper">
            <?php if (count($available) > 0): ?>
            <table class="lists">
              <thead>
                <tr>
                  <th class="select-col"><input type="checkbox" onclick="toggleAll(this, 'availBody')"></th>
                  <th>Category</th><th class="type-col">Type</th><th class="brand-col">Brand</th><th class="model-col">Model</th><th>Serial</th>
                </tr>
              </thead>
              <tbody id="availBody">
                <?php foreach($available as $row): ?>
                <tr data-eid="<?=$row['equipment_id']?>" 
                    data-category="<?=htmlspecialchars($row['equipment_category'])?>"
                    data-type="<?=htmlspecialchars($row['equipment_type'])?>"
                    data-brand="<?=htmlspecialchars($row['brand'])?>"
                    data-model="<?=htmlspecialchars($row['model'])?>"
                    data-serial="<?=htmlspecialchars($row['serial_number'])?>">
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
            <div id="availableEmptyState" class="empty-state" style="display: none;">
              No equipment matches your filter criteria.<br>
              Try adjusting your filters.
            </div>
            <?php else: ?>
            <div class="empty-state">
              No equipment available for deployment.
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="dual-buttons">
        <button type="button" onclick="bulkAdd()">Add Selected ▶</button>
        <button type="button" onclick="bulkRemove()">◀ Remove Selected</button>
      </div>

      <div style="flex:1;">
        <div class="table-container">
          <div class="table-header">
            To Deploy
            <span class="count-badge" id="deployCount">0 items</span>
          </div>
          <div class="table-wrapper">
            <table class="lists">
              <thead>
                <tr>
                  <th class="select-col"><input type="checkbox" onclick="toggleAll(this, 'selBody')"></th>
                  <th>Category</th><th class="type-col">Type</th><th class="brand-col">Brand</th><th class="model-col">Model</th><th>Serial</th>
                </tr>
              </thead>
              <tbody id="selBody">
                <!-- Selected equipment will appear here -->
              </tbody>
            </table>
            <div id="emptyDeployState" class="empty-state">
              No equipment selected for deployment.<br>
              Select items from the left table and click "Add Selected".
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="hiddenInputs"></div>
    
    <button type="submit" class="submit-btn">Confirm Deployment</button>
  </form>

  <script>
    function updateDeployCount() {
      const count = document.getElementById('selBody').children.length;
      const countBadge = document.getElementById('deployCount');
      const emptyState = document.getElementById('emptyDeployState');
      
      countBadge.textContent = `${count} items`;
      
      // Show/hide empty state
      if (count === 0) {
        emptyState.style.display = 'block';
      } else {
        emptyState.style.display = 'none';
      }
    }

    function updateAvailableCount() {
      const availBody = document.getElementById('availBody');
      const visibleRows = Array.from(availBody.children).filter(row => !row.classList.contains('hidden-row'));
      const count = visibleRows.length;
      
      document.getElementById('availableCount').textContent = `${count} items`;
      
      // Show/hide empty state for available equipment
      const emptyState = document.getElementById('availableEmptyState');
      if (count === 0 && availBody.children.length > 0) {
        emptyState.style.display = 'block';
      } else {
        emptyState.style.display = 'none';
      }
    }

    function applyFilters() {
      const category = document.getElementById('filterCategory').value.toLowerCase();
      const brand = document.getElementById('filterBrand').value.toLowerCase();
      const type = document.getElementById('filterType').value.toLowerCase();
      const model = document.getElementById('filterModel').value.toLowerCase();
      const serial = document.getElementById('filterSerial').value.toLowerCase();

      const availBody = document.getElementById('availBody');
      const rows = availBody.querySelectorAll('tr');

      rows.forEach(row => {
        const rowCategory = row.dataset.category.toLowerCase();
        const rowBrand = row.dataset.brand.toLowerCase();
        const rowType = row.dataset.type.toLowerCase();
        const rowModel = row.dataset.model.toLowerCase();
        const rowSerial = row.dataset.serial.toLowerCase();

        let show = true;

        if (category && !rowCategory.includes(category)) show = false;
        if (brand && !rowBrand.includes(brand)) show = false;
        if (type && !rowType.includes(type)) show = false;
        if (model && !rowModel.includes(model)) show = false;
        if (serial && !rowSerial.includes(serial)) show = false;

        if (show) {
          row.classList.remove('hidden-row');
        } else {
          row.classList.add('hidden-row');
          // Uncheck if hidden
          const checkbox = row.querySelector('input[type="checkbox"]');
          if (checkbox) checkbox.checked = false;
        }
      });

      updateAvailableCount();
    }

    function clearFilters() {
      document.getElementById('filterCategory').value = '';
      document.getElementById('filterBrand').value = '';
      document.getElementById('filterType').value = '';
      document.getElementById('filterModel').value = '';
      document.getElementById('filterSerial').value = '';

      // Show all rows
      const availBody = document.getElementById('availBody');
      availBody.querySelectorAll('tr').forEach(row => {
        row.classList.remove('hidden-row');
      });

      updateAvailableCount();
    }

    function bulkAdd() {
      const avail = document.getElementById('availBody');
      const sel   = document.getElementById('selBody');
      const hidden = document.getElementById('hiddenInputs');
      
      // Only process visible (non-filtered) checkboxes
      const visibleCheckedBoxes = Array.from(avail.querySelectorAll('input[type=checkbox]:checked'))
        .filter(cb => !cb.closest('tr').classList.contains('hidden-row'));
      
      visibleCheckedBoxes.forEach(cb => {
        const tr = cb.closest('tr'); 
        cb.checked = false;
        
        // Remove hidden-row class and move to selected
        tr.classList.remove('hidden-row');
        sel.appendChild(tr);
        
        const eid = tr.dataset.eid;
        const inp = document.createElement('input');
        inp.type = 'hidden'; 
        inp.name = 'equipment_ids[]'; 
        inp.value = eid;
        hidden.appendChild(inp);
      });
      
      updateDeployCount();
      updateAvailableCount();
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
      
      // Reapply filters to moved items
      applyFilters();
      updateDeployCount();
    }

    function toggleAll(cb, bodyId) {
      const body = document.getElementById(bodyId);
      const checkboxes = body.querySelectorAll('input[type=checkbox]');
      
      checkboxes.forEach(chk => {
        // Only toggle visible rows
        const row = chk.closest('tr');
        if (!row.classList.contains('hidden-row')) {
          chk.checked = cb.checked;
        }
      });
    }

    // Add real-time filtering on input
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('filterType').addEventListener('input', applyFilters);
      document.getElementById('filterModel').addEventListener('input', applyFilters);
      document.getElementById('filterSerial').addEventListener('input', applyFilters);
      document.getElementById('filterCategory').addEventListener('change', applyFilters);
      document.getElementById('filterBrand').addEventListener('change', applyFilters);
      
      // Initialize counts
      updateDeployCount();
      updateAvailableCount();
    });
  </script>
</body>
</html>