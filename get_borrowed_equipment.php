<?php
require 'db.php';

// get_borrowed_equipment.php
// Fetch all equipment entries for a given slip_no
$slip_no = $_GET['slip_no'] ?? '';
if (!$slip_no) {
    echo '<div class="error-message">Invalid slip number.</div>';
    exit;
}

// Prepare and execute query
$sql = "
SELECT bt.borrower_id_seq,
       e.equipment_id,
       e.equipment_type,
       e.brand,
       e.model,
       bt.date_returned,
       bt.equipment_returned_status,
       bt.borrower,
       bt.office_borrower,
       bt.purpose,
       bt.date_borrowed,
       bt.due_date
FROM borrow_transactions bt
JOIN equipment e ON bt.equipment_id = e.equipment_id
WHERE bt.slip_no = ?
ORDER BY e.equipment_type, e.model
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$slip_no]);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo '<div class="error-message">No equipment found for this slip number.</div>';
    exit;
}

// Define authorized personnel who can receive returned equipment (matches ENUM)
$authorized_receivers = ['Angelo', 'Nonie', 'Bemy', 'Kristopher', 'Dan'];

// Get borrower info from first record
$borrower_info = $rows[0];
?>

<style>
.modal-container {
    font-family: Arial, sans-serif;
    font-size: 1em;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #f1aeb5;
    text-align: center;
    font-size: 1em;
}

.borrower-info {
    background: #e7f3ff;
    border: 1px solid #b8daff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}

.borrower-info h4 {
    margin: 0 0 15px 0;
    color: #0056b3;
    border-bottom: 2px solid #0056b3;
    padding-bottom: 8px;
    font-size: 1.2em;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-weight: bold;
    color: #495057;
    font-size: 0.95em;
    margin-bottom: 3px;
}

.info-value {
    color: #212529;
    padding: 2px 0;
    font-size: 1em;
}

.return-form {
    background: #fff;
    border-radius: 8px;
}

.form-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
}

.form-header h4 {
    margin: 0;
    color: #495057;
    font-size: 1.1em;
}

.received-by-section {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 5px;
    padding: 15px 20px;
    margin: 15px 0;
}

.received-by-section h5 {
    margin: 0 0 10px 0;
    color: #856404;
    font-size: 1em;
}

.form-group {
    margin-bottom: 10px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #495057;
    font-size: 0.9em;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.9em;
    box-sizing: border-box;
}

.form-group select {
    background: white;
    cursor: pointer;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.required {
    color: #dc3545;
}

.equipment-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    font-size: 0.95em;
}

.equipment-table th {
    background: #f8f9fa;
    color: #495057;
    font-weight: 600;
    padding: 14px 10px;
    text-align: left;
    border: 1px solid #dee2e6;
    position: sticky;
    top: 0;
    font-size: 0.95em;
}

.equipment-table td {
    padding: 12px 10px;
    border: 1px solid #dee2e6;
    vertical-align: middle;
    font-size: 0.95em;
}

.equipment-table tbody tr:nth-child(even) {
    background: #f8f9fa;
}

.equipment-table tbody tr:hover {
    background: #e7f3ff;
}

.equipment-table input[type="checkbox"] {
    transform: scale(1.3);
    cursor: pointer;
}

.equipment-table input[type="date"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.9em;
}

.equipment-table select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.9em;
    background: white;
}

.equipment-table input[type="date"]:focus,
.equipment-table select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.form-actions {
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 8px 8px;
    text-align: center;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1em;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
    margin: 0 5px;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.status-indicator {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
    text-transform: uppercase;
}

.status-returned {
    background: #d4edda;
    color: #155724;
}

.status-not-returned {
    background: #fff3cd;
    color: #856404;
}

.selection-controls {
    margin-bottom: 15px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 5px;
    text-align: center;
}

.selection-controls button {
    background: #007bff;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9em;
    margin: 0 5px;
}

.selection-controls button:hover {
    background: #0056b3;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .equipment-table {
        font-size: 0.85em;
    }
    
    .equipment-table th,
    .equipment-table td {
        padding: 8px 6px;
    }
}
</style>

<div class="modal-container">
    <div class="borrower-info">
        <h4>📋 Borrow Slip Information</h4>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Slip Number:</span>
                <span class="info-value"><?= htmlspecialchars($slip_no) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Borrower:</span>
                <span class="info-value"><?= htmlspecialchars($borrower_info['borrower']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Office:</span>
                <span class="info-value"><?= htmlspecialchars($borrower_info['office_borrower']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Purpose:</span>
                <span class="info-value"><?= htmlspecialchars($borrower_info['purpose']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Date Borrowed:</span>
                <span class="info-value"><?= htmlspecialchars($borrower_info['date_borrowed']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Due Date:</span>
                <span class="info-value"><?= htmlspecialchars($borrower_info['due_date']) ?></span>
            </div>
        </div>
    </div>

    <div class="return-form">
        <div class="form-header">
            <h4>📦 Equipment Return Form</h4>
        </div>
        
        <form method="post" action="process_return.php">
            <input type="hidden" name="slip_no" value="<?= htmlspecialchars($slip_no, ENT_QUOTES) ?>">
            
            <div class="received-by-section">
                <h5>👤 Received By <span class="required">*</span></h5>
                <div class="form-group">
                    <label for="received_by_select">Select Authorized Personnel:</label>
                    <select id="received_by_select" name="received_by" required>
                        <option value="">-- Select Authorized Personnel --</option>
                        <?php foreach ($authorized_receivers as $receiver): ?>
                            <option value="<?= htmlspecialchars($receiver) ?>">
                                👤 <?= htmlspecialchars($receiver) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="selection-controls">
                <button type="button" onclick="selectAll()">✅ Select All</button>
                <button type="button" onclick="selectNone()">❌ Select None</button>
                <button type="button" onclick="selectUnreturned()">📤 Select Unreturned Only</button>
            </div>
            
            <table class="equipment-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Select</th>
                        <th style="width: 80px;">Equipment ID</th>
                        <th>Type</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th style="width: 130px;">Date Returned</th>
                        <th style="width: 180px;">Return Status</th>
                        <th style="width: 80px;">Current Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td style="text-align: center;">
                            <input type="checkbox"
                                   name="return[<?= $r['borrower_id_seq'] ?>]"
                                   id="chk_<?= $r['borrower_id_seq'] ?>"
                                   data-returned="<?= $r['date_returned'] ? 'true' : 'false' ?>">
                        </td>
                        <td><?= htmlspecialchars($r['equipment_id']) ?></td>
                        <td><?= htmlspecialchars($r['equipment_type']) ?></td>
                        <td><?= htmlspecialchars($r['brand']) ?></td>
                        <td><?= htmlspecialchars($r['model']) ?></td>
                        <td>
                            <input type="date"
                                   name="date_returned[<?= $r['borrower_id_seq'] ?>]"
                                   id="date_<?= $r['borrower_id_seq'] ?>"
                                   value="<?= htmlspecialchars($r['date_returned'] ?: date('Y-m-d')) ?>">
                        </td>
                        <td>
                            <select name="status[<?= $r['borrower_id_seq'] ?>]"
                                    id="status_<?= $r['borrower_id_seq'] ?>">
                                <option value="Functional (in good condition)" <?= $r['equipment_returned_status']=='Functional (in good condition)' ? 'selected' : '' ?>>Functional (in good condition)</option>
                                <option value="Functional (with few defects)" <?= $r['equipment_returned_status']=='Functional (with few defects)' ? 'selected' : '' ?>>Functional (with few defects)</option>
                                <option value="For Repair" <?= $r['equipment_returned_status']=='For Repair' ? 'selected' : '' ?>>For Repair</option>
                                <option value="Unserviceable" <?= $r['equipment_returned_status']=='Unserviceable' ? 'selected' : '' ?>>Unserviceable</option>
                            </select>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($r['date_returned']): ?>
                                <span class="status-indicator status-returned">✅ Returned</span>
                            <?php else: ?>
                                <span class="status-indicator status-not-returned">📤 Borrowed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success" onclick="return validateForm()">
                    ↩️ Confirm Return
                </button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('returnModal').style.display='none'">
                    ❌ Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Selection helper functions
function selectAll() {
    document.querySelectorAll('input[type=checkbox][name^="return["]').forEach(function(cb) {
        cb.checked = true;
        toggleRequirement(cb);
    });
}

function selectNone() {
    document.querySelectorAll('input[type=checkbox][name^="return["]').forEach(function(cb) {
        cb.checked = false;
        toggleRequirement(cb);
    });
}

function selectUnreturned() {
    document.querySelectorAll('input[type=checkbox][name^="return["]').forEach(function(cb) {
        cb.checked = (cb.dataset.returned === 'false');
        toggleRequirement(cb);
    });
}

function toggleRequirement(checkbox) {
    var id = checkbox.id.split('_')[1];
    var dateInput = document.getElementById('date_' + id);
    var statusSelect = document.getElementById('status_' + id);
    
    dateInput.required = checkbox.checked;
    statusSelect.required = checkbox.checked;
    
    // Visual feedback
    if (checkbox.checked) {
        dateInput.style.borderColor = '#007bff';
        statusSelect.style.borderColor = '#007bff';
    } else {
        dateInput.style.borderColor = '#ced4da';
        statusSelect.style.borderColor = '#ced4da';
    }
}

// Form validation
function validateForm() {
    const receivedBySelect = document.getElementById('received_by_select');
    const receivedBy = receivedBySelect.value.trim();
    
    if (!receivedBy) {
        alert('Please select who received the equipment.');
        receivedBySelect.focus();
        return false;
    }
    
    // Check if at least one item is selected
    const checkedItems = document.querySelectorAll('input[type=checkbox][name^="return["]:checked');
    if (checkedItems.length === 0) {
        alert('Please select at least one equipment item to return.');
        return false;
    }
    
    // Confirmation dialog
    const equipmentCount = checkedItems.length;
    const message = `Are you sure you want to return ${equipmentCount} equipment item(s)?\n\nReceived by: ${receivedBy}\n\nThis action cannot be undone.`;
    
    return confirm(message);
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type=checkbox][name^="return["]').forEach(function(cb) {
        function handleToggle() {
            toggleRequirement(cb);
        }

        // Initialize on load
        handleToggle();
        // Update on change
        cb.addEventListener('change', handleToggle);
    });
    
    // Auto-select unreturned items on load
    selectUnreturned();
});
</script>