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

    // Update equipment status back to available
    $updEquip = $pdo->prepare(
        "UPDATE equipment e
         JOIN borrow_transactions bt ON e.equipment_id = bt.equipment_id
         SET e.equipment_status = 'Available for Deployment'
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