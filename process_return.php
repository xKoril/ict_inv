<?php
require 'db.php';

// process_return.php
// Handle return form submission for BORROW returns
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: borrower.php');
    exit;
}

$slip_no       = $_POST['slip_no'] ?? '';
$returned      = $_POST['return'] ?? [];
$date_returned = $_POST['date_returned'] ?? [];
$status        = $_POST['status'] ?? [];
$received_by   = trim($_POST['received_by'] ?? '');

// Define authorized personnel (should match ENUM values)
$authorized_receivers = ['Angelo', 'Nonie', 'Bemy', 'Kristopher', 'Dan'];

if (!$slip_no) {
    header('Location: borrower.php?error=Invalid+slip+number');
    exit;
}

if (empty($returned)) {
    header('Location: borrower.php?error=No+equipment+selected+for+return');
    exit;
}

if (empty($received_by)) {
    header('Location: borrower.php?error=Please+specify+who+received+the+equipment');
    exit;
}

// Validate that received_by is one of the authorized personnel
if (!in_array($received_by, $authorized_receivers)) {
    header('Location: borrower.php?error=Invalid+authorized+personnel+selected');
    exit;
}

try {
    $pdo->beginTransaction();

    // Update borrow_transactions for each returned item (with received_by)
    $updTran = $pdo->prepare(
        "UPDATE borrow_transactions
         SET date_returned = :date_ret,
             equipment_returned_status = :stat,
             received_by = :received_by
         WHERE borrower_id_seq = :id"
    );

    // ENHANCED: Smart equipment status update based on previous deployment status
    $updEquip = $pdo->prepare(
        "UPDATE equipment e
         JOIN borrow_transactions bt ON e.equipment_id = bt.equipment_id
         SET e.equipment_status = CASE 
             -- Check if equipment was deployed before borrowing by looking at deployment history
             WHEN EXISTS (
                 SELECT 1 FROM deployment_transactions dt 
                 WHERE dt.equipment_id = e.equipment_id 
                 AND dt.date_deployed < bt.date_borrowed
                 AND NOT EXISTS (
                     SELECT 1 FROM return_transactions rt 
                     WHERE rt.equipment_id = e.equipment_id 
                     AND rt.return_date > dt.date_deployed 
                     AND rt.return_date < bt.date_borrowed
                 )
             ) THEN 'Deployed'
             -- Otherwise, make it available for deployment
             ELSE 'Available for Deployment'
         END,
         e.locator = CASE 
             -- If returning to deployed status, get the office from latest deployment
             WHEN EXISTS (
                 SELECT 1 FROM deployment_transactions dt 
                 WHERE dt.equipment_id = e.equipment_id 
                 AND dt.date_deployed < bt.date_borrowed
                 AND NOT EXISTS (
                     SELECT 1 FROM return_transactions rt 
                     WHERE rt.equipment_id = e.equipment_id 
                     AND rt.return_date > dt.date_deployed 
                     AND rt.return_date < bt.date_borrowed
                 )
             ) THEN (
                 SELECT dt2.office_custodian 
                 FROM deployment_transactions dt2 
                 WHERE dt2.equipment_id = e.equipment_id 
                 AND dt2.date_deployed < bt.date_borrowed
                 ORDER BY dt2.date_deployed DESC, dt2.time_deployed DESC 
                 LIMIT 1
             )
             -- Otherwise, set to MIS (central location)
             ELSE 'DTI RO - MIS'
         END
         WHERE bt.borrower_id_seq = :id"
    );

    $updated_count = 0;
    foreach ($returned as $id => $_) {
        $date = $date_returned[$id] ?? null;
        $stat = $status[$id] ?? null;
        if (!$date || !$stat) {
            continue;
        }
        
        $updTran->execute([
            ':date_ret' => $date,
            ':stat'     => $stat,
            ':received_by' => $received_by,
            ':id'       => $id
        ]);
        
        $updEquip->execute([':id' => $id]);
        $updated_count++;
    }

    $pdo->commit();
    
    if ($updated_count > 0) {
        $message = "Successfully returned {$updated_count} equipment item(s). Received by: {$received_by}";
        header('Location: borrower.php?message=' . urlencode($message));
    } else {
        header('Location: borrower.php?error=No+valid+equipment+items+were+processed');
    }
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Borrow return processing error: " . $e->getMessage());
    header('Location: borrower.php?error=' . urlencode('Error processing return: ' . $e->getMessage()));
    exit;
}
?>