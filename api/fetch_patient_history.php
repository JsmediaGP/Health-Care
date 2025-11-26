<?php
/**
 * API Endpoint: Fetches Reading History or Alert History for a specific patient.
 * Called by: assets/js/patient_details_doctor.js (for history tables)
 * Usage: api/fetch_patient_history.php?pid={patient_id}&type={readings|alerts}
 */
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../engine/db_config.php'; 

// 1. Basic Security and Input Validation
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$patient_id = $_GET['pid'] ?? null;
$type = $_GET['type'] ?? null;

if (!$patient_id || !in_array($type, ['readings', 'alerts'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request parameters.']);
    exit;
}

try {
    // 2. Get internal patient_pk
    $stmt_pk = $pdo->prepare("SELECT patient_pk FROM patients WHERE user_fk = ?");
    $stmt_pk->execute([$patient_id]);
    $patient_pk = $stmt_pk->fetchColumn();

    if (!$patient_pk) {
        throw new Exception("Patient profile not found.");
    }

    $response_data = ['history' => [], 'type' => $type];

    // 3. Dynamic Query Execution
    if ($type === 'readings') {
        $stmt = $pdo->prepare("
            SELECT heart_rate, spo2, temperature, acc_ax, acc_ay, acc_az, timestamp
            FROM readings
            WHERE patient_fk = ?
            ORDER BY timestamp DESC
            LIMIT 200 -- Fetch a reasonable amount for the history table
        ");
        $stmt->execute([$patient_pk]);
        $response_data['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($type === 'alerts') {
        $stmt = $pdo->prepare("
            SELECT alert_id, alert_type, alert_message, value, recorded_at, status
            FROM alerts
            WHERE patient_fk = ?
            ORDER BY recorded_at DESC
        ");
        $stmt->execute([$patient_pk]);
        $response_data['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['status' => 'success', 'data' => $response_data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>