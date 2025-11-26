<?php
/**
 * API Endpoint: Marks ALL UNREAD alerts for a specific patient as 'READ'.
 * Called by: assets/js/alert_page_doctor.js (Resolve All Alerts button)
 */
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Adjust path as necessary to reach your DB connection file
include '../engine/db_config.php'; 

// 1. Basic Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// 2. Input Validation (Requires POST data)
$patient_id = $_POST['patient_id'] ?? null; // The patient's user_fk (VARCHAR)
$doctor_user_id = $_SESSION['user_id'];

if (!$patient_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing Patient ID for resolution.']);
    exit;
}

try {
    // A. Get Doctor's Primary Key (PK)
    $stmt_doc = $pdo->prepare("SELECT doctor_pk FROM doctors WHERE user_fk = ?");
    $stmt_doc->execute([$doctor_user_id]);
    $doctor_pk = $stmt_doc->fetchColumn();

    if (!$doctor_pk) {
        throw new Exception("Doctor profile not found.");
    }

    // B. Get Patient's Primary Key (PK) and verify assignment
    $stmt_pat = $pdo->prepare("SELECT patient_pk FROM patients WHERE user_fk = ? AND assigned_doctor_fk = ?");
    $stmt_pat->execute([$patient_id, $doctor_pk]);
    $patient_pk = $stmt_pat->fetchColumn();

    if (!$patient_pk) {
        throw new Exception("Patient not found or not assigned to this doctor.");
    }
    
    // C. Update ALL UNREAD alerts to READ for the retrieved patient_pk
    // CRITICAL FIX: The query is simplified to only set 'status' to 'READ' 
    // and correctly uses ONE placeholder for $patient_pk.
    $stmt = $pdo->prepare("
        UPDATE alerts
        SET status = 'READ'
        WHERE patient_fk = ? AND status = 'UNREAD'
    ");
    
    // Execute: Only the $patient_pk is passed, corresponding to the single '?' placeholder.
    $stmt->execute([$patient_pk]); 
    
    $rows_updated = $stmt->rowCount();

    if ($rows_updated > 0) {
        echo json_encode([
            'status' => 'success', 
            'message' => "Successfully marked {$rows_updated} alerts as READ.",
            'updated_count' => $rows_updated
        ]);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'No pending alerts found to resolve.', 'updated_count' => 0]);
    }

} catch (PDOException $e) {
    // Log the error for debugging, but return a generic message to the frontend
    error_log('Alert Resolution DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database operation failed. Check server logs for details.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>