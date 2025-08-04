<?php
require 'db.php';

// Pagination settings
$page_s = max(1, (int)($_GET['page_s'] ?? 1)); // ICS/PAR summary page
$page_d = max(1, (int)($_GET['page_d'] ?? 1)); // deployed equipment page
$page_a = max(1, (int)($_GET['page_a'] ?? 1)); // available equipment page
$limit   = 10;
$offset_s = ($page_s - 1) * $limit;
$offset_d = ($page_d - 1) * $limit;
$offset_a = ($page_a - 1) * $limit;

// Offices for filters
$offices = ['DTI-Aklan','DTI-Antique','DTI-Capiz','DTI-Guimaras','DTI-Iloilo',
            'DTI-Negros Occ','DTI RO - ORD','DTI RO - MIS','DTI RO - BDD',
            'DTI RO - CPD','DTI RO - FAD','DTI RO - IDD','COA','SBCorp'];

// Fetch office enum values from deployment_transactions table
$officeOptions = [];
$colOffice = $pdo->query("SHOW COLUMNS FROM deployment_transactions LIKE 'office_custodian'")->fetch(PDO::FETCH_ASSOC);
if (isset($colOffice['Type']) && preg_match("/^enum\\((.*)\\)$/", $colOffice['Type'], $m)) {
    foreach (explode(',', $m[1]) as $v) {
        $officeOptions[] = trim($v, "' ");
    }
} else {
    // Fallback to the static array if no enum found
    $officeOptions = $offices;
}

// Fetch enum values for equipment_status
$statusOptions = [];
$col = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'equipment_status'")->fetch(PDO::FETCH_ASSOC);
if (isset($col['Type']) && preg_match("/^enum\\((.*)\\)$/", $col['Type'], $m)) {
    foreach (explode(',', $m[1]) as $v) {
        $statusOptions[] = trim($v, "' ");
    }
}

// Summary filters
$s_ics  = $_GET['s_ics']  ?? '';
$s_cust = $_GET['s_cust'] ?? '';
$s_off  = $_GET['s_off']  ?? '';
$s_filters = [];
$s_params  = [];
if ($s_ics)  { $s_filters[] = 'ics_par_no = ?';      $s_params[] = $s_ics; }
if ($s_cust) { $s_filters[] = 'custodian LIKE ?';     $s_params[] = "%$s_cust%"; }
if ($s_off)  { $s_filters[] = 'office_custodian = ?'; $s_params[] = $s_off; }

// ICS/PAR Summary
$summary_sql = "SELECT ics_par_no, custodian, office_custodian, date_deployed, COUNT(*) AS total_equipment
FROM deployment_transactions";
if ($s_filters) {
    $summary_sql .= ' WHERE ' . implode(' AND ', $s_filters);
}
$summary_sql .= " GROUP BY ics_par_no, custodian, office_custodian, date_deployed
ORDER BY date_deployed DESC
LIMIT $limit OFFSET $offset_s";
$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute($s_params);
$summary = $summary_stmt->fetchAll();

// Deployed Equipment (only status='Deployed') filters
$d_ics   = $_GET['d_ics']   ?? '';
$d_type  = $_GET['d_type']  ?? '';
$d_cust  = $_GET['d_cust']  ?? '';
$d_off   = $_GET['d_off']   ?? '';
$d_filters = ["e.equipment_status = 'Deployed'"];
$d_params  = [];
if ($d_ics)  { $d_filters[] = 'dt.ics_par_no = ?';       $d_params[] = $d_ics; }
if ($d_type) { $d_filters[] = 'e.equipment_type LIKE ?'; $d_params[] = "%$d_type%"; }
if ($d_cust) { $d_filters[] = 'dt.custodian LIKE ?';     $d_params[] = "%$d_cust%"; }
if ($d_off)  { $d_filters[] = 'dt.office_custodian = ?'; $d_params[] = $d_off; }

$d_sql = "SELECT dt.ics_par_no, dt.custodian, dt.office_custodian,
           e.equipment_id, e.equipment_type, e.brand, e.model, e.serial_number, e.description_specification
FROM deployment_transactions dt
JOIN equipment e ON dt.equipment_id = e.equipment_id
WHERE " . implode(' AND ', $d_filters) . "
ORDER BY dt.date_deployed DESC
LIMIT $limit OFFSET $offset_d";
$d_stmt = $pdo->prepare($d_sql);
$d_stmt->execute($d_params);
$deployed = $d_stmt->fetchAll();

// Available Equipment filters
$a_type  = $_GET['a_type']  ?? '';
$a_brand = $_GET['a_brand'] ?? '';
$a_model = $_GET['a_model'] ?? '';
$a_filters = [];
$a_params  = [];
if ($a_type)  { $a_filters[] = 'equipment_type LIKE ?'; $a_params[] = "%$a_type%"; }
if ($a_brand) { $a_filters[] = 'brand LIKE ?';          $a_params[] = "%$a_brand%"; }
if ($a_model) { $a_filters[] = 'model LIKE ?';          $a_params[] = "%$a_model%"; }

$a_sql = "SELECT equipment_type, brand, model, locator, serial_number, description_specification
FROM equipment
WHERE equipment_status = 'Available for Deployment'";
if ($a_filters) {
    $a_sql .= ' AND ' . implode(' AND ', $a_filters);
}
$a_sql .= " ORDER BY equipment_type, brand
LIMIT $limit OFFSET $offset_a";
$a_stmt = $pdo->prepare($a_sql);
$a_stmt->execute($a_params);
$available = $a_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Deployment Dashboard</title>
  <style>
    body { font-family:Arial,sans-serif; margin:20px; font-size:0.9em; }
    .back-link { display:inline-block; margin-bottom:12px; color:#0056b3; text-decoration:none; }
    .back-link:hover { text-decoration:underline; }
    h1,h2 { margin-top:24px; margin-bottom:8px; }
    .box { border:1px solid #ccc; padding:16px; margin-bottom:24px; border-radius:4px; background:#fafafa; }
    table { width:100%; border-collapse:collapse; margin-bottom:12px; }
    th,td { border:1px solid #ccc; padding:8px; text-align:left; }
    th { background:#f4f4f4; }
    form label { margin-right:12px; }
    input, select, button { padding:6px; }
    .pagination a { margin-right:8px; text-decoration:none; color:#0056b3; }
    .pagination span { margin-right:8px; }
    .modal { display:none; position:fixed; top:20%; left:50%; transform:translateX(-50%); width:400px; background:#fff; padding:20px; border:1px solid #ccc; z-index:1000; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .modal-header { font-weight:bold; margin-bottom:12px; font-size:1.1em; }
    .modal-footer { margin-top:12px; text-align:right; }
    .dual-button { margin-right:4px; }
    .equipment-info { background:#f9f9f9; border:1px solid #ddd; padding:12px; margin:10px 0; border-radius:4px; }
    .equipment-info h4 { margin:0 0 8px 0; color:#333; }
    .equipment-detail { margin:4px 0; }
    .equipment-detail strong { color:#555; }
    .overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999; }
  </style>
</head>
<body>
  <a href="index.php" class="back-link">← Back to Inventory</a>
  <h1>Deployment Dashboard</h1>
  <button onclick="location.href='deploy_equipment.php'">➕ Create Deployment Form</button>

  <div class="box">
    <h2>ICS/PAR Summary</h2>
    <form method="get">
      <input type="hidden" name="page_d" value="<?=$page_d?>">
      <input type="hidden" name="page_a" value="<?=$page_a?>">
      <label>ICS/PAR No.: <input type="text" name="s_ics" value="<?=htmlspecialchars($s_ics)?>"></label>
      <label>Custodian: <input type="text" name="s_cust" value="<?=htmlspecialchars($s_cust)?>"></label>
      <label>Office: <select name="s_off"><option value="">--All--</option><?php foreach($offices as $o): ?><option value="<?=htmlspecialchars($o)?>" <?php if($o===$s_off) echo 'selected';?>><?=htmlspecialchars($o)?></option><?php endforeach; ?></select></label>
      <button type="submit">Filter</button>
      <a href="deployment.php" style="margin-left:8px;">Clear</a>
    </form>
    <table>
      <tr><th>ICS/PAR No.</th><th>Custodian</th><th>Office</th><th>Date Deployed</th><th>Action</th></tr>
      <?php foreach($summary as $row): ?>
      <tr>
        <td><?=htmlspecialchars($row['ics_par_no'])?></td>
        <td><?=htmlspecialchars($row['custodian'])?></td>
        <td><?=htmlspecialchars($row['office_custodian'])?></td>
        <td><?=htmlspecialchars($row['date_deployed'])?></td>
        <td><button onclick="viewDeploy('<?=htmlspecialchars($row['ics_par_no'])?>')">View Equipment</button></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="pagination">
      <?php if($page_s>1): ?><a href="?page_s=<?=$page_s-1?>&s_ics=<?=urlencode($s_ics)?>&s_cust=<?=urlencode($s_cust)?>&s_off=<?=urlencode($s_off)?>">← Prev</a><?php endif; ?>
      <span>Page <?=$page_s?></span>
      <?php if(count($summary)==$limit): ?><a href="?page_s=<?=$page_s+1?>&s_ics=<?=urlencode($s_ics)?>&s_cust=<?=urlencode($s_cust)?>&s_off=<?=urlencode($s_off)?>">Next →</a><?php endif; ?>
    </div>
  </div>

  <div class="box">
    <h2>Deployed Equipment</h2>
    <form method="get">
      <input type="hidden" name="page_s" value="<?=$page_s?>">
      <input type="hidden" name="page_a" value="<?=$page_a?>">
      <label>ICS/PAR:<input type="text" name="d_ics" value="<?=htmlspecialchars($d_ics)?>"></label>
      <label>Type:<input type="text" name="d_type" value="<?=htmlspecialchars($d_type)?>"></label>
      <label>Custodian:<input type="text" name="d_cust" value="<?=htmlspecialchars($d_cust)?>"></label>
      <label>Office:<select name="d_off"><option value="">--All--</option><?php foreach($offices as $o): ?><option value="<?=htmlspecialchars($o)?>" <?php if($o===$d_off) echo 'selected';?>><?=htmlspecialchars($o)?></option><?php endforeach; ?></select></label>
      <button type="submit">Filter</button>
      <a href="deployment.php?page_s=<?=$page_s?>" style="margin-left:8px;">Clear</a>
    </form>
    <table>
      <tr><th>ICS/PAR</th><th>Custodian</th><th>Office</th><th>Type</th><th>Brand</th><th>Model</th><th>Description</th><th>Actions</th></tr>
      <?php foreach($deployed as $row): ?>
      <tr>
        <td><?=htmlspecialchars($row['ics_par_no'])?></td>
        <td><?=htmlspecialchars($row['custodian'])?></td>
        <td><?=htmlspecialchars($row['office_custodian'])?></td>
        <td><?=htmlspecialchars($row['equipment_type'])?></td>
        <td><?=htmlspecialchars($row['brand'])?></td>
        <td><?=htmlspecialchars($row['model'])?></td>
        <td><?=htmlspecialchars($row['description_specification'])?></td>
        <td>
          <button class="dual-button" onclick="openStatusModal(<?=htmlspecialchars($row['equipment_id'])?>, '<?=htmlspecialchars($row['ics_par_no'])?>', '<?=htmlspecialchars($row['equipment_type'])?>', '<?=htmlspecialchars($row['brand'])?>', '<?=htmlspecialchars($row['model'])?>', '<?=htmlspecialchars($row['serial_number'])?>')">Change Status</button>
          <button class="dual-button" onclick="openTransferModal(<?=htmlspecialchars($row['equipment_id'])?>, '<?=htmlspecialchars($row['ics_par_no'])?>', '<?=htmlspecialchars($row['equipment_type'])?>', '<?=htmlspecialchars($row['brand'])?>', '<?=htmlspecialchars($row['model'])?>', '<?=htmlspecialchars($row['serial_number'])?>')">Transfer</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="pagination">
      <?php if($page_d>1): ?><a href="?page_s=<?=$page_s?>&page_d=<?=$page_d-1?>&d_ics=<?=urlencode($d_ics)?>&d_type=<?=urlencode($d_type)?>&d_cust=<?=urlencode($d_cust)?>&d_off=<?=urlencode($d_off)?>">← Prev</a><?php endif; ?>
      <span>Page <?=$page_d?></span>
      <?php if(count($deployed)==$limit): ?><a href="?page_s=<?=$page_s?>&page_d=<?=$page_d+1?>&d_ics=<?=urlencode($d_ics)?>&d_type=<?=urlencode($d_type)?>&d_cust=<?=urlencode($d_cust)?>&d_off=<?=urlencode($d_off)?>">Next →</a><?php endif; ?>
    </div>
  </div>

  <div class="box">
    <h2>Available Equipment</h2>
    <form method="get">
      <input type="hidden" name="page_s" value="<?=$page_s?>">
      <input type="hidden" name="page_d" value="<?=$page_d?>">
      <label>Type:<input type="text" name="a_type" value="<?=htmlspecialchars($a_type)?>"></label>
      <label>Brand:<input type="text" name="a_brand" value="<?=htmlspecialchars($a_brand)?>"></label>
      <label>Model:<input type="text" name="a_model" value="<?=htmlspecialchars($a_model)?>"></label>
      <button type="submit">Filter</button>
      <a href="deployment.php?page_s=<?=$page_s?>&page_d=<?=$page_d?>" style="margin-left:8px;">Clear</a>
    </form>
    <table>
      <tr><th>Type</th><th>Brand</th><th>Model</th><th>Locator</th><th>Serial</th><th>Description</th></tr>
      <?php foreach($available as $row): ?>
      <tr>
        <td><?=htmlspecialchars($row['equipment_type'])?></td>
        <td><?=htmlspecialchars($row['brand'])?></td>
        <td><?=htmlspecialchars($row['model'])?></td>
        <td><?=htmlspecialchars($row['locator'])?></td>
        <td><?=htmlspecialchars($row['serial_number'])?></td>
        <td><?=htmlspecialchars($row['description_specification'])?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="pagination">
      <?php if($page_a>1): ?><a href="?page_s=<?=$page_s?>&page_d=<?=$page_d?>&page_a=<?=$page_a-1?>&a_type=<?=urlencode($a_type)?>&a_brand=<?=urlencode($a_brand)?>&a_model=<?=urlencode($a_model)?>">← Prev</a><?php endif; ?>
      <span>Page <?=$page_a?></span>
      <?php if(count($available)==$limit): ?><a href="?page_s=<?=$page_s?>&page_d=<?=$page_d?>&page_a=<?=$page_a+1?>&a_type=<?=urlencode($a_type)?>&a_brand=<?=urlencode($a_brand)?>&a_model=<?=urlencode($a_model)?>">Next →</a><?php endif; ?>
    </div>
  </div>

  <!-- Modal Overlay -->
  <div id="overlay" class="overlay" onclick="closeStatus()"></div>

  <!-- Change Status Modal -->
  <div id="statusModal" class="modal">
    <div class="modal-header">Change Equipment Status</div>
    
    <!-- Equipment Information Section -->
    <div class="equipment-info">
      <h4>Equipment Details</h4>
      <div class="equipment-detail"><strong>ICS/PAR No:</strong> <span id="modalIcsParNo"></span></div>
      <div class="equipment-detail"><strong>Type:</strong> <span id="modalEquipmentType"></span></div>
      <div class="equipment-detail"><strong>Brand:</strong> <span id="modalEquipmentBrand"></span></div>
      <div class="equipment-detail"><strong>Model:</strong> <span id="modalEquipmentModel"></span></div>
      <div class="equipment-detail"><strong>Serial Number:</strong> <span id="modalEquipmentSerial"></span></div>
    </div>
    
    <input type="hidden" id="statusEqId">
    <label for="statusSelect"><strong>New Status:</strong></label>
    <select id="statusSelect" style="width:100%; margin-top:8px;">
      <?php foreach($statusOptions as $opt): ?>
        <option value="<?=htmlspecialchars($opt)?>"><?=htmlspecialchars($opt)?></option>
      <?php endforeach; ?>
    </select>
    <div class="modal-footer">
      <button onclick="confirmStatus()">Confirm</button>
      <button onclick="closeStatus()">Cancel</button>
    </div>
  </div>

  <!-- Transfer Equipment Modal -->
  <div id="transferModal" class="modal" style="width:450px;">
    <div class="modal-header">Transfer Equipment</div>
    
    <!-- Equipment Information Section -->
    <div class="equipment-info">
      <h4>Equipment Details</h4>
      <div class="equipment-detail"><strong>Current ICS/PAR:</strong> <span id="transferCurrentIcsParNo"></span></div>
      <div class="equipment-detail"><strong>Type:</strong> <span id="transferEquipmentType"></span></div>
      <div class="equipment-detail"><strong>Brand:</strong> <span id="transferEquipmentBrand"></span></div>
      <div class="equipment-detail"><strong>Model:</strong> <span id="transferEquipmentModel"></span></div>
      <div class="equipment-detail"><strong>Serial Number:</strong> <span id="transferEquipmentSerial"></span></div>
    </div>
    
    <input type="hidden" id="transferEqId">
    
    <div style="margin-top:15px;">
      <label for="newIcsParNo"><strong>New ICS/PAR No:</strong></label>
      <input type="text" id="newIcsParNo" style="width:100%; margin-top:5px; padding:8px;" placeholder="Enter new ICS/PAR number" required>
    </div>
    
    <div style="margin-top:10px;">
      <label for="newCustodian"><strong>New Custodian:</strong></label>
      <input type="text" id="newCustodian" style="width:100%; margin-top:5px; padding:8px;" placeholder="Enter custodian name" required>
    </div>
    
    <div style="margin-top:10px;">
      <label for="newOffice"><strong>Office:</strong></label>
      <select id="newOffice" style="width:100%; margin-top:5px; padding:8px;" required>
        <option value="">--Select Office--</option>
        <?php foreach($officeOptions as $office): ?>
          <option value="<?=htmlspecialchars($office)?>"><?=htmlspecialchars($office)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div style="margin-top:10px;">
      <label for="transferDate"><strong>Date Deployed:</strong></label>
      <input type="date" id="transferDate" style="width:100%; margin-top:5px; padding:8px;" required>
    </div>
    
    <div style="margin-top:10px;">
      <label for="transferTime"><strong>Time Deployed:</strong></label>
      <input type="time" id="transferTime" style="width:100%; margin-top:5px; padding:8px;" required>
    </div>
    
    <div class="modal-footer">
      <button onclick="confirmTransfer()">Transfer Equipment</button>
      <button onclick="closeTransfer()">Cancel</button>
    </div>
  </div>

  <script>
    function openStatusModal(id, icsParNo, type, brand, model, serial) {
      document.getElementById('statusEqId').value = id;
      document.getElementById('modalIcsParNo').textContent = icsParNo || 'N/A';
      document.getElementById('modalEquipmentType').textContent = type || 'N/A';
      document.getElementById('modalEquipmentBrand').textContent = brand || 'N/A';
      document.getElementById('modalEquipmentModel').textContent = model || 'N/A';
      document.getElementById('modalEquipmentSerial').textContent = serial || 'N/A';
      
      document.getElementById('overlay').style.display = 'block';
      document.getElementById('statusModal').style.display = 'block';
    }
    
    function closeStatus() {
      document.getElementById('overlay').style.display = 'none';
      document.getElementById('statusModal').style.display = 'none';
    }
    
    function openTransferModal(id, icsParNo, type, brand, model, serial) {
      document.getElementById('transferEqId').value = id;
      document.getElementById('transferCurrentIcsParNo').textContent = icsParNo || 'N/A';
      document.getElementById('transferEquipmentType').textContent = type || 'N/A';
      document.getElementById('transferEquipmentBrand').textContent = brand || 'N/A';
      document.getElementById('transferEquipmentModel').textContent = model || 'N/A';
      document.getElementById('transferEquipmentSerial').textContent = serial || 'N/A';
      
      // Set current date and time as defaults
      const now = new Date();
      const currentDate = now.toISOString().split('T')[0];
      const currentTime = now.toTimeString().slice(0, 5);
      document.getElementById('transferDate').value = currentDate;
      document.getElementById('transferTime').value = currentTime;
      
      // Clear form fields
      document.getElementById('newIcsParNo').value = '';
      document.getElementById('newCustodian').value = '';
      document.getElementById('newOffice').value = '';
      
      document.getElementById('overlay').style.display = 'block';
      document.getElementById('transferModal').style.display = 'block';
    }
    
    function closeTransfer() {
      document.getElementById('overlay').style.display = 'none';
      document.getElementById('transferModal').style.display = 'none';
    }
    
    function confirmStatus() {
      const id = document.getElementById('statusEqId').value;
      const status = document.getElementById('statusSelect').value;
      
      // Disable button to prevent double-clicks
      const confirmBtn = event.target;
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Updating...';
      
      fetch('change_status.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `equipment_id=${id}&status=${encodeURIComponent(status)}`
      })
      .then(response => response.text())
      .then(data => {
        console.log('Server response:', data);
        try {
          const jsonData = JSON.parse(data);
          if (jsonData.success) {
            alert(`Status updated successfully!\nEquipment ID: ${jsonData.equipment_id}\nOld Status: ${jsonData.old_status}\nNew Status: ${jsonData.new_status}`);
          } else {
            alert('Update failed: ' + jsonData.message);
          }
        } catch (e) {
          // If response is not JSON, show as plain text
          alert('Server response: ' + data);
        }
        location.reload();
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating status: ' + error.message);
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Confirm';
      });
    }
    
    function confirmTransfer() {
      const equipmentId = document.getElementById('transferEqId').value;
      const newIcsParNo = document.getElementById('newIcsParNo').value.trim();
      const newCustodian = document.getElementById('newCustodian').value.trim();
      const newOffice = document.getElementById('newOffice').value;
      const transferDate = document.getElementById('transferDate').value;
      const transferTime = document.getElementById('transferTime').value;
      
      // Validate required fields
      if (!newIcsParNo || !newCustodian || !newOffice || !transferDate || !transferTime) {
        alert('Please fill in all required fields.');
        return;
      }
      
      // Disable button to prevent double-clicks
      const confirmBtn = event.target;
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Transferring...';
      
      const formData = new URLSearchParams();
      formData.append('equipment_id', equipmentId);
      formData.append('ics_par_no', newIcsParNo);
      formData.append('new_custodian', newCustodian);
      formData.append('office_custodian', newOffice);
      formData.append('date_deployed', transferDate);
      formData.append('time_deployed', transferTime);
      
      fetch('transfer_equipment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData.toString()
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(`Equipment transferred successfully!\n\nDetails:\n- Equipment ID: ${data.equipment_id}\n- New ICS/PAR: ${data.ics_par_no}\n- New Custodian: ${data.new_custodian}\n- Office: ${data.office_custodian}\n- Date: ${data.date_deployed}\n- Time: ${data.time_deployed}`);
          closeTransfer();
          location.reload();
        } else {
          alert('Transfer failed: ' + data.message);
          confirmBtn.disabled = false;
          confirmBtn.textContent = 'Transfer Equipment';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while transferring equipment: ' + error.message);
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Transfer Equipment';
      });
    }
    
    // Close modal when clicking overlay
    document.getElementById('overlay').addEventListener('click', function() {
      closeStatus();
      closeTransfer();
    });
    
    function viewDeploy(ics) {
      document.getElementById('deployModal').style.display = 'block';
      fetch('get_deployed_equipment.php?ics=' + encodeURIComponent(ics))
        .then(res => res.text())
        .then(html => document.getElementById('modalDeployContent').innerHTML = html);
    }
  </script>
</body>
</html>