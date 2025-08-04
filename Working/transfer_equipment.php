<?php
// transfer_equipment.php
// Creates a new deployment transaction record for equipment transfer

require 'db.php';

// 1) Tell the client we’re returning JSON right from the start
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// collect & validate
$equipment_id     = $_POST['equipment_id']     ?? null;
$ics_par_no       = $_POST['ics_par_no']       ?? null;
$new_custodian    = $_POST['new_custodian']    ?? null;
$office_custodian = $_POST['office_custodian'] ?? null;
$date_deployed    = $_POST['date_deployed']    ?? null;
$time_deployed    = $_POST['time_deployed']    ?? null;

if (!$equipment_id || !$ics_par_no || !$new_custodian || !$office_custodian || !$date_deployed || !$time_deployed) {
    http_response_code(400);
    echo json_encode([
        'success'        => false,
        'message'        => 'All fields are required',
        'missing_fields' => [
            'equipment_id'     => !$equipment_id,
            'ics_par_no'       => !$ics_par_no,
            'new_custodian'    => !$new_custodian,
            'office_custodian' => !$office_custodian,
            'date_deployed'    => !$date_deployed,
            'time_deployed'    => !$time_deployed,
        ],
    ]);
    exit;
}

try {
    // verify it exists & is deployed
    $checkStmt = $pdo->prepare("SELECT equipment_status FROM equipment WHERE equipment_id = :id");
    $checkStmt->execute([':id' => $equipment_id]);
    $equipment = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Equipment not found']);
        exit;
    }
    if ($equipment['equipment_status'] !== 'Deployed') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Equipment is not currently deployed']);
        exit;
    }

    // insert transfer record
    $ins = $pdo->prepare("
      INSERT INTO deployment_transactions 
        (equipment_id, ics_par_no, custodian, office_custodian, date_deployed, time_deployed)
      VALUES
        (:equipment_id, :ics_par_no, :custodian, :office_custodian, :date_deployed, :time_deployed)
    ");
    $result = $ins->execute([
        ':equipment_id'     => $equipment_id,
        ':ics_par_no'       => $ics_par_no,
        ':custodian'        => $new_custodian,
        ':office_custodian' => $office_custodian,
        ':date_deployed'    => $date_deployed,
        ':time_deployed'    => $time_deployed,
    ]);

    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success'         => true,
            'message'         => 'Equipment transferred successfully',
            'transaction_id'  => $pdo->lastInsertId(),
            'equipment_id'    => $equipment_id,
            'ics_par_no'      => $ics_par_no,
            'new_custodian'   => $new_custodian,
            'office_custodian'=> $office_custodian,
            'date_deployed'   => $date_deployed,
            'time_deployed'   => $time_deployed,
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create transfer record']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: '.$e->getMessage()]);
    exit;
}

// **NO trailing “?>”** — this prevents PHP from accidentally emitting unwanted whitespace.
