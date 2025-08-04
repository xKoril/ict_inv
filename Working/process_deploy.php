<?php
require 'db.php';

// process_deploy.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: deploy_equipment.php');
    exit;
}

$ics_par_no    = $_POST['ics_par_no'] ?? '';
$date_dep      = $_POST['date_deployed'] ?? '';
$time_dep      = $_POST['time_deployed'] ?? '';
$custodian     = $_POST['custodian'] ?? '';
$office_cust   = $_POST['office_custodian'] ?? '';
$remarks       = $_POST['remarks'] ?? '';
$equipment_ids = $_POST['equipment_ids'] ?? [];

if (!$ics_par_no || !$date_dep || !$time_dep || !$custodian || !$office_cust || !count($equipment_ids)) {
    header('Location: deploy_equipment.php?error=Please+fill+all+fields');
    exit;
}

try {
    $pdo->beginTransaction();
    $ins = $pdo->prepare("INSERT INTO deployment_transactions (equipment_id, ics_par_no, date_deployed, time_deployed, custodian, office_custodian, remarks) VALUES (:eid,:ics,:d,:t,:cust,:off,:rem)");
    $upd = $pdo->prepare("UPDATE equipment SET equipment_status='Deployed', ics_par_no=:ics WHERE equipment_id=:eid");
    foreach ($equipment_ids as $eid) {
        $ins->execute([':eid'=>$eid,':ics'=>$ics_par_no,':d'=>$date_dep,':t'=>$time_dep,':cust'=>$custodian,':off'=>$office_cust,':rem'=>$remarks]);
        $upd->execute([':ics'=>$ics_par_no,':eid'=>$eid]);
    }
    $pdo->commit();
    header('Location: borrower.php?message=Deployment+successful');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: deploy_equipment.php?error=' . urlencode($e->getMessage()));
    exit;
}
