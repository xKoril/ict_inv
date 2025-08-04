<?php
require 'db.php';

// process_return.php
// Handle return form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: borrower.php');
    exit;
}

$slip_no       = $_POST['slip_no'] ?? '';
$returned      = $_POST['return'] ?? [];
$date_returned = $_POST['date_returned'] ?? [];
$status        = $_POST['status'] ?? [];

if (!$slip_no) {
    header('Location: borrower.php?error=Invalid+slip+number');
    exit;
}

try {
    $pdo->beginTransaction();

    // Update borrow_transactions for each returned item
    $updTran = $pdo->prepare(
        "UPDATE borrow_transactions
         SET date_returned = :date_ret,
             equipment_returned_status = :stat
         WHERE borrower_id_seq = :id"
    );
    // Update equipment status back to available
    $updEquip = $pdo->prepare(
        "UPDATE equipment e
         JOIN borrow_transactions bt ON e.equipment_id = bt.equipment_id
         SET e.equipment_status = 'Available for Deployment'
         WHERE bt.borrower_id_seq = :id"
    );

    foreach ($returned as $id => $_) {
        $date = $date_returned[$id] ?? null;
        $stat = $status[$id] ?? null;
        if (!$date || !$stat) {
            continue;
        }
        $updTran->execute([
            ':date_ret' => $date,
            ':stat'     => $stat,
            ':id'       => $id
        ]);
        $updEquip->execute([':id' => $id]);
    }

    $pdo->commit();
    header('Location: borrower.php?message=Return+successful');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: borrower.php?error=' . urlencode($e->getMessage()));
    exit;
}
