<?php
// change_status.php
// Updates a single equipment's status based on AJAX POST from deployment dashboard

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$equipment_id = $_POST['equipment_id'] ?? null;
$new_status   = $_POST['status']       ?? null;

if (!$equipment_id || !$new_status) {
    http_response_code(400);
    echo 'Invalid input. Equipment ID: ' . ($equipment_id ?? 'missing') . ', Status: ' . ($new_status ?? 'missing');
    exit;
}

try {
    // First, check current status
    $checkStmt = $pdo->prepare("SELECT equipment_status FROM equipment WHERE equipment_id = :id");
    $checkStmt->execute([':id' => $equipment_id]);
    $currentData = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentData) {
        http_response_code(404);
        echo 'Equipment not found with ID: ' . $equipment_id;
        exit;
    }
    
    $oldStatus = $currentData['equipment_status'];
    
    // Update the status
    $stmt = $pdo->prepare("UPDATE equipment SET equipment_status = :status WHERE equipment_id = :id");
    $result = $stmt->execute([
        ':status' => $new_status,
        ':id'     => $equipment_id
    ]);
    
    if ($result) {
        // Verify the update
        $verifyStmt = $pdo->prepare("SELECT equipment_status FROM equipment WHERE equipment_id = :id");
        $verifyStmt->execute([':id' => $equipment_id]);
        $updatedData = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'equipment_id' => $equipment_id,
            'old_status' => $oldStatus,
            'new_status' => $updatedData['equipment_status'],
            'rows_affected' => $stmt->rowCount()
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Update failed',
            'equipment_id' => $equipment_id
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'equipment_id' => $equipment_id
    ]);
}
?>