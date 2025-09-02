<?php
// Add authentication check at the top of index.php
require_once 'auth.php';

// Require user to be logged in
$auth->requireLogin();

// Get current user info for display
$currentUser = $auth->getCurrentUser();

// Or require specific permission
$auth->requirePermission('add'); // for add pages
$auth->requirePermission('edit'); // for edit pages
$auth->requirePermission('delete'); // for delete pages

require 'db.php';

// Offices list
$offices = ['DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo','DTI-Negros Occ','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD','DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'];

// Define which departments can lend their deployed equipment
$lending_departments = ['DTI RO - MIS', 'DTI RO - ORD']; // Add departments that can lend

// Fetch categories from database
$cat_stmt = $pdo->query("SELECT DISTINCT equipment_category FROM equipment WHERE equipment_category IS NOT NULL AND equipment_category != '' ORDER BY equipment_category");
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch brands from database
$brand_stmt = $pdo->query("SELECT DISTINCT brand FROM equipment WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
$brands = $brand_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch statuses from database  
$status_stmt = $pdo->query("SELECT DISTINCT equipment_status FROM equipment WHERE equipment_status IS NOT NULL AND equipment_status != '' ORDER BY equipment_status");
$statuses = $status_stmt->fetchAll(PDO::FETCH_COLUMN);

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
            header('Location: borrow_equipment.php?success=Borrow+successful');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields and select at least one item.';
    }
}

// ENHANCED: Fetch equipment that's Available OR Deployed to lending departments (no server-side filtering)
$lending_conditions = [];
foreach ($lending_departments as $dept) {
    $lending_conditions[] = "dt.office_custodian = '" . $dept . "'";
}
$lending_clause = implode(' OR ', $lending_conditions);

$sql = "
SELECT DISTINCT
    e.equipment_id, 
    e.equipment_category, 
    e.equipment_status, 
    e.equipment_type, 
    e.brand, 
    e.model, 
    e.serial_number
FROM equipment e
LEFT JOIN (
    SELECT 
        equipment_id, 
        office_custodian,
        ROW_NUMBER() OVER (PARTITION BY equipment_id ORDER BY date_deployed DESC, time_deployed DESC) as rn
    FROM deployment_transactions 
) dt ON e.equipment_id = dt.equipment_id AND dt.rn = 1
WHERE 
    -- Available equipment (existing logic)
    e.equipment_status = 'Available for Deployment'
    OR 
    -- Deployed equipment from lending departments that's not already borrowed
    (e.equipment_status = 'Deployed' 
     AND ($lending_clause)
     AND NOT EXISTS (
         SELECT 1 FROM borrow_transactions bt 
         WHERE bt.equipment_id = e.equipment_id 
         AND bt.date_returned IS NULL
     ))
ORDER BY e.equipment_category, e.equipment_type, e.brand, e.model
";
$stmt = $pdo->query($sql);
$available = $stmt->fetchAll();
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
    th.status-col, td.status-col { width: 120px; }
    th.type-col, td.type-col { width: 160px; }
    th.brand-col, td.brand-col { width: 120px; }
    th.model-col, td.model-col { width: 120px; }
    
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
    
    /* Status column styling */
    .status-available { 
      color: #28a745; 
      font-weight: bold; 
    }
    
    .status-deployed { 
      color: #007bff; 
      font-weight: bold; 
    }
    
    .status-borrowed { 
      color: #ffc107; 
      font-weight: bold; 
    }

    /* Hidden row styling */
    .hidden-row {
      display: none !important;
    }
  </style>
</head>
<body>
  <h1>Borrow Equipment</h1>
  
  <!-- Return Button -->
  <div style="margin-bottom: 20px;">
    <a href="borrower.php" class="btn" style="background: #6c757d; color: white; text-decoration: none; padding: 8px 16px; border-radius: 3px; display: inline-block;">← Back to Borrower Dashboard</a>
  </div>

  <!-- Success Message -->
  <?php if (!empty($_GET['success'])): ?>
    <div class="success"><strong><?=htmlspecialchars($_GET['success'])?></strong></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="error"><strong><?=htmlspecialchars($error)?></strong></div>
  <?php endif; ?>

  <!-- Single Main Form for Everything -->
  <form method="post" id="borrowForm">
    <input type="hidden" name="action" value="borrow">
    
    <table class="form-table">
      <tr>
        <td><label>Slip Number *</label><input type="text" name="slip_no" required value=""></td>
        <td><label>Borrower Name *</label><input type="text" name="borrower" required value=""></td>
        <td>
          <label>Office *</label>
          <select name="office" required>
            <option value="">--Select--</option>
            <?php foreach($offices as $off): ?>
            <option value="<?=htmlspecialchars($off)?>"><?=htmlspecialchars($off)?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <td colspan="3"><label>Purpose *</label><textarea name="purpose" required></textarea></td>
      </tr>
      <tr>
        <td><label>Date Borrowed *</label><input type="date" name="date_borrowed" required value="<?=date('Y-m-d')?>"></td>
        <td><label>Due Date</label><input type="date" name="due_date" value=""></td>
        <td><label>Time Borrowed *</label><input type="time" name="time_borrowed" required value="<?=date('H:i')?>"></td>
      </tr>
    </table>

    <!-- Filter Equipment Section -->
    <h3>Select Equipment to Borrow</h3>
    <div class="filter-form">
      <label>Category:
        <select id="filterCategory">
          <option value="">--All--</option>
          <?php foreach($categories as $cat): ?>
          <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Status:
        <select id="filterStatus">
          <option value="">--All--</option>
          <?php foreach($statuses as $status): ?>
          <option value="<?=htmlspecialchars($status)?>"><?=htmlspecialchars($status)?></option>
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

    <!-- Equipment Selection Section -->
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
                  <th>Category</th><th class="status-col">Status</th><th class="type-col">Type</th><th class="brand-col">Brand</th><th class="model-col">Model</th><th>Serial</th>
                </tr>
              </thead>
              <tbody id="availBody">
                <?php foreach($available as $row): ?>
                <tr data-eid="<?=$row['equipment_id']?>"
                    data-category="<?=htmlspecialchars($row['equipment_category'])?>"
                    data-status="<?=htmlspecialchars($row['equipment_status'])?>"
                    data-type="<?=htmlspecialchars($row['equipment_type'])?>"
                    data-brand="<?=htmlspecialchars($row['brand'])?>"
                    data-model="<?=htmlspecialchars($row['model'])?>"
                    data-serial="<?=htmlspecialchars($row['serial_number'])?>">
                  <td class="select-col"><input type="checkbox"></td>
                  <td><?=htmlspecialchars($row['equipment_category'])?></td>
                  <td class="status-<?=strtolower(str_replace(' ', '-', $row['equipment_status']))?>"><?=htmlspecialchars($row['equipment_status'])?></td>
                  <td><?=htmlspecialchars($row['equipment_type'])?></td>
                  <td><?=htmlspecialchars($row['brand'])?></td>
                  <td><?=htmlspecialchars($row['model'])?></td>
                  <td><?=htmlspecialchars($row['serial_number'])?></td>
                </tr>
                <?php endforeach;?>
              </tbody>
            </table>
            <div id="availableEmptyState" class="empty-state" style="display: none;">
              No equipment matches your filter criteria.<br>
              Try adjusting your filters.
            </div>
            <?php else: ?>
            <div class="empty-state">
              No equipment available for borrowing.
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
            To Borrow
            <span class="count-badge" id="borrowCount">0 items</span>
          </div>
          <div class="table-wrapper">
            <table class="lists">
              <thead>
                <tr>
                  <th class="select-col"><input type="checkbox" onclick="toggleAll(this, 'selBody')"></th>
                  <th>Category</th><th class="status-col">Status</th><th class="type-col">Type</th><th class="brand-col">Brand</th><th class="model-col">Model</th><th>Serial</th>
                </tr>
              </thead>
              <tbody id="selBody">
                <!-- Selected equipment will appear here -->
              </tbody>
            </table>
            <div id="emptyBorrowState" class="empty-state">
              No equipment selected for borrowing.<br>
              Select items from the left table and click "Add Selected".
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="hiddenInputs"></div>
    
    <button type="submit" class="submit-btn">Confirm Borrow Request</button>
  </form>

  <script>
    function updateBorrowCount() {
      const count = document.getElementById('selBody').children.length;
      const countBadge = document.getElementById('borrowCount');
      const emptyState = document.getElementById('emptyBorrowState');
      
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
      const status = document.getElementById('filterStatus').value.toLowerCase();
      const brand = document.getElementById('filterBrand').value.toLowerCase();
      const type = document.getElementById('filterType').value.toLowerCase();
      const model = document.getElementById('filterModel').value.toLowerCase();
      const serial = document.getElementById('filterSerial').value.toLowerCase();

      const availBody = document.getElementById('availBody');
      const rows = availBody.querySelectorAll('tr');

      rows.forEach(row => {
        const rowCategory = row.dataset.category.toLowerCase();
        const rowStatus = row.dataset.status.toLowerCase();
        const rowBrand = row.dataset.brand.toLowerCase();
        const rowType = row.dataset.type.toLowerCase();
        const rowModel = row.dataset.model.toLowerCase();
        const rowSerial = row.dataset.serial.toLowerCase();

        let show = true;

        if (category && !rowCategory.includes(category)) show = false;
        if (status && !rowStatus.includes(status)) show = false;
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
      document.getElementById('filterStatus').value = '';
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
      
      updateBorrowCount();
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
      updateBorrowCount();
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
      document.getElementById('filterStatus').addEventListener('change', applyFilters);
      document.getElementById('filterBrand').addEventListener('change', applyFilters);
      
      // Initialize counts
      updateBorrowCount();
      updateAvailableCount();
    });

    // Form validation to ensure at least one equipment is selected
    document.getElementById('borrowForm').addEventListener('submit', function(e) {
      const equipmentIds = document.querySelectorAll('input[name="equipment_ids[]"]');
      if (equipmentIds.length === 0) {
        e.preventDefault();
        alert('Please select at least one equipment item to borrow.');
        return false;
      }
    });
  </script>
</body>
</html>