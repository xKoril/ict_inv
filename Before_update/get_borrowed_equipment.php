<?php
require 'db.php';

// get_borrowed_equipment.php
// Fetch all equipment entries for a given slip_no
$slip_no = $_GET['slip_no'] ?? '';
if (!$slip_no) {
    echo 'Invalid slip number.';
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
       bt.equipment_returned_status
FROM borrow_transactions bt
JOIN equipment e ON bt.equipment_id = e.equipment_id
WHERE bt.slip_no = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$slip_no]);
$rows = $stmt->fetchAll();

// Render return form
?>
<form method="post" action="process_return.php">
  <input type="hidden" name="slip_no" value="<?= htmlspecialchars($slip_no, ENT_QUOTES) ?>">
  <table>
    <tr>
      <th>Select</th>
      <th>Equipment ID</th>
      <th>Type</th>
      <th>Brand</th>
      <th>Model</th>
      <th>Date Returned</th>
      <th>Status</th>
    </tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td>
        <input type="checkbox"
               name="return[<?= $r['borrower_id_seq'] ?>]"
               id="chk_<?= $r['borrower_id_seq'] ?>">
      </td>
      <td><?= htmlspecialchars($r['equipment_id']) ?></td>
      <td><?= htmlspecialchars($r['equipment_type']) ?></td>
      <td><?= htmlspecialchars($r['brand']) ?></td>
      <td><?= htmlspecialchars($r['model']) ?></td>
      <td>
        <input type="date"
               name="date_returned[<?= $r['borrower_id_seq'] ?>]"
               id="date_<?= $r['borrower_id_seq'] ?>"
               value="<?= htmlspecialchars($r['date_returned']) ?>">
      </td>
      <td>
        <select name="status[<?= $r['borrower_id_seq'] ?>]"
                id="status_<?= $r['borrower_id_seq'] ?>">
          <option value="Functional (in good condition)" <?= $r['equipment_returned_status']=='Functional (in good condition)' ? 'selected' : '' ?>>Functional (in good condition)</option>
          <option value="Functional (with few defects)" <?= $r['equipment_returned_status']=='Functional (with few defects)' ? 'selected' : '' ?>>Functional (with few defects)</option>
          <option value="Unserviceable" <?= $r['equipment_returned_status']=='Unserviceable' ? 'selected' : '' ?>>Unserviceable</option>
        </select>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <button type="submit">Confirm Return</button>
</form>

<script>
// Only require date and status for checked rows
window.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type=checkbox][name^="return["]').forEach(function(cb) {
        var id = cb.id.split('_')[1];
        var dateInput = document.getElementById('date_' + id);
        var statusSelect = document.getElementById('status_' + id);

        function toggleRequirement() {
            dateInput.required = cb.checked;
            statusSelect.required = cb.checked;
        }

        // Initialize on load
        toggleRequirement();
        // Update on change
        cb.addEventListener('change', toggleRequirement);
    });
});
</script>
