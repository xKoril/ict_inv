<?php
// Start output buffering to prevent any accidental output
ob_start();

// Ensure no errors are displayed that could break JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

require 'db.php';

// Clear any output that might have been generated
ob_clean();

// Set response header to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$original_ics_par_no = $_POST['original_ics_par_no'] ?? '';
$new_ics_par_no = trim($_POST['ics_par_no'] ?? '');
$custodian = trim($_POST['custodian'] ?? '');
$office_custodian = trim($_POST['office_custodian'] ?? '');
$date_deployed = $_POST['date_deployed'] ?? '';

// Validate required fields
if (empty($original_ics_par_no) || empty($new_ics_par_no) || empty($custodian) || empty($office_custodian) || empty($date_deployed)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date_deployed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if new ICS/PAR number already exists (if it's different from original)
    if ($new_ics_par_no !== $original_ics_par_no) {
        $check_duplicate_sql = "SELECT COUNT(*) FROM deployment_transactions WHERE ics_par_no = ?";
        $check_duplicate_stmt = $pdo->prepare($check_duplicate_sql);
        $check_duplicate_stmt->execute([$new_ics_par_no]);
        $duplicate_count = $check_duplicate_stmt->fetchColumn();
        
        if ($duplicate_count > 0) {
            throw new Exception('ICS/PAR number already exists. Please use a different number.');
        }
    }
    
    // First, get all equipment IDs that need to be updated (only deployed equipment)
    $get_equipment_sql = "SELECT dt.equipment_id 
                          FROM deployment_transactions dt
                          JOIN equipment e ON dt.equipment_id = e.equipment_id
                          WHERE dt.ics_par_no = ? 
                          AND e.equipment_status = 'Deployed'";
    
    $get_equipment_stmt = $pdo->prepare($get_equipment_sql);
    $get_equipment_stmt->execute([$original_ics_par_no]);
    $equipment_ids = $get_equipment_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($equipment_ids)) {
        throw new Exception('No deployed equipment found under this ICS/PAR number');
    }
    
    // Update deployment_transactions table for deployed equipment
    $update_deployment_sql = "UPDATE deployment_transactions dt
                              JOIN equipment e ON dt.equipment_id = e.equipment_id
                              SET dt.ics_par_no = ?, 
                                  dt.custodian = ?, 
                                  dt.office_custodian = ?, 
                                  dt.date_deployed = ?
                              WHERE dt.ics_par_no = ? 
                              AND e.equipment_status = 'Deployed'";
    
    $update_deployment_stmt = $pdo->prepare($update_deployment_sql);
    $deployment_result = $update_deployment_stmt->execute([
        $new_ics_par_no, 
        $custodian, 
        $office_custodian, 
        $date_deployed, 
        $original_ics_par_no
    ]);
    
    if (!$deployment_result) {
        throw new Exception('Failed to update deployment records');
    }
    
    $deployment_affected = $update_deployment_stmt->rowCount();
    
    // Update equipment table for the same deployed equipment
    $placeholders = str_repeat('?,', count($equipment_ids) - 1) . '?';
    $update_equipment_sql = "UPDATE equipment 
                             SET ics_par_no = ?, locator = ?
                             WHERE equipment_id IN ($placeholders) 
                             AND equipment_status = 'Deployed'";
    
    $update_equipment_stmt = $pdo->prepare($update_equipment_sql);
    $equipment_params = array_merge([$new_ics_par_no, $office_custodian], $equipment_ids);
    $equipment_result = $update_equipment_stmt->execute($equipment_params);
    
    if (!$equipment_result) {
        throw new Exception('Failed to update equipment records');
    }
    
    $equipment_affected = $update_equipment_stmt->rowCount();
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully updated {$deployment_affected} deployment record(s) and {$equipment_affected} equipment record(s)",
        'deployment_affected' => $deployment_affected,
        'equipment_affected' => $equipment_affected,
        'redirect_url' => "view_deployment.php?ics_par_no=" . urlencode($new_ics_par_no)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    // Handle database errors specifically
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

// End output buffering and send the response
ob_end_flush();
?>