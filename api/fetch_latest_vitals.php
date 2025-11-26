<?php
/**
 * API Endpoint: Fetches the single latest vital sign reading for a patient.
 * Called by: assets/js/patient_details_doctor.js (for live refresh)
 */
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Adjust path as necessary to reach your DB connection file
include '../engine/db_config.php'; 

// 1. Basic Security and Input Validation
// Only doctors can view patient data via this API
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$patient_id = $_GET['pid'] ?? null; // The patient's user_id

if (!$patient_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing Patient ID.']);
    exit;
}

try {
    // 2. Get internal patient_pk (PK) from the user_id (FK)
    $stmt_pk = $pdo->prepare("SELECT patient_pk FROM patients WHERE user_fk = ?");
    $stmt_pk->execute([$patient_id]);
    $patient_pk = $stmt_pk->fetchColumn();

    if (!$patient_pk) {
        throw new Exception("Patient profile not found.");
    }

    // 3. Fetch latest reading, including acceleration components
    $stmt = $pdo->prepare("
        SELECT heart_rate, spo2, temperature, acc_ax, acc_ay, acc_az, timestamp
        FROM readings
        WHERE patient_fk = ?
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_pk]);
    $latest_reading = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($latest_reading) {
        echo json_encode(['status' => 'success', 'reading' => $latest_reading]);
    } else {
        echo json_encode(['status' => 'success', 'reading' => null, 'message' => 'No readings found.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>