<?php
/**
 * API Endpoint: Marks a specific alert as 'READ'.
 * Called by: assets/js/patient_details_doctor.js (Mark Read button)
 */
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../engine/db_config.php'; 

// 1. Basic Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// 2. Input Validation (Requires POST data)
$alert_id = $_POST['alert_id'] ?? null;

if (!$alert_id || !is_numeric($alert_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid alert ID provided.']);
    exit;
}

try {
    // 3. Update the alert status using a prepared statement
    $stmt = $pdo->prepare("
        UPDATE alerts
        SET status = 'READ'
        WHERE alert_id = ? AND status = 'UNREAD'
    ");
    $stmt->execute([$alert_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Alert marked as READ.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Alert not found or already marked READ.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error during update.']);
}
?>