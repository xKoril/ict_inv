<?php
// change_status.php
// Updates a single equipment's status based on AJAX POST from deployment dashboard

require 'db.php';

// Set JSON header first
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed - Only POST requests accepted',
        'received_method' => $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

// Get and validate input
$equipment_id = $_POST['equipment_id'] ?? null;
$new_status   = $_POST['status'] ?? null;

// Validate required fields
if (!$equipment_id || !$new_status) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields',
        'required' => ['equipment_id', 'status'],
        'received' => [
            'equipment_id' => $equipment_id,
            'status' => $new_status
        ]
    ]);
    exit;
}

// Validate equipment_id is numeric
if (!is_numeric($equipment_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Equipment ID must be numeric',
        'received_equipment_id' => $equipment_id
    ]);
    exit;
}

try {
    // First, check if equipment exists and get current status
    $checkStmt = $pdo->prepare("SELECT equipment_status FROM equipment WHERE equipment_id = ?");
    $checkStmt->execute([$equipment_id]);
    $currentData = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentData) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Equipment not found',
            'equipment_id' => $equipment_id
        ]);
        exit;
    }
    
    $oldStatus = $currentData['equipment_status'];
    
    // Don't update if status is the same
    if ($oldStatus === $new_status) {
        echo json_encode([
            'success' => true,
            'message' => 'Status already set to requested value',
            'equipment_id' => $equipment_id,
            'status' => $new_status,
            'changed' => false
        ]);
        exit;
    }
    
    // Update the status
    $updateStmt = $pdo->prepare("UPDATE equipment SET equipment_status = ? WHERE equipment_id = ?");
    $result = $updateStmt->execute([$new_status, $equipment_id]);
    
    if ($result && $updateStmt->rowCount() > 0) {
        // Verify the update worked
        $verifyStmt = $pdo->prepare("SELECT equipment_status FROM equipment WHERE equipment_id = ?");
        $verifyStmt->execute([$equipment_id]);
        $updatedData = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'equipment_id' => (int)$equipment_id,
            'old_status' => $oldStatus,
            'new_status' => $updatedData['equipment_status'],
            'rows_affected' => $updateStmt->rowCount(),
            'changed' => true
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Update failed - no rows affected',
            'equipment_id' => $equipment_id,
            'attempted_status' => $new_status
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'equipment_id' => $equipment_id,
        'error_code' => $e->getCode(),
        // Don't expose sensitive database details in production
        'debug' => (isset($_GET['debug']) && $_GET['debug'] === '1') ? $e->getMessage() : 'Enable debug mode for details'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error occurred',
        'equipment_id' => $equipment_id,
        'debug' => (isset($_GET['debug']) && $_GET['debug'] === '1') ? $e->getMessage() : 'Enable debug mode for details'
    ]);
}
?>