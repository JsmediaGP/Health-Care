<?php
/**
 * PATIENT_FETCH_24HR_DATA.PHP
 * API endpoint for the patient to retrieve their own last 24 hours of vital signs history.
 */
session_start();
header('Content-Type: application/json');

// --- 1. Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Access denied. Patient login required.']);
    exit;
}

// 2. Include core files
require_once '../engine/db_config.php';
require_once '../engine/functions.php'; 

$user_id = $_SESSION['user_id'];
$hours_limit = 24; 

try {
    // A. Get the Patient's internal PK (patient_pk)
    $stmt_patient_pk = $pdo->prepare("SELECT patient_pk FROM patients WHERE user_fk = ?");
    $stmt_patient_pk->execute([$user_id]);
    $patient_pk = $stmt_patient_pk->fetchColumn();

    if (!$patient_pk) {
        throw new Exception("Patient profile data not found.");
    }
    
    // B. Data Fetch: Retrieve historical readings for the last 24 hours
    $stmt = $pdo->prepare("
        SELECT 
            heart_rate, 
            spo2, 
            timestamp 
        FROM readings 
        WHERE patient_fk = ? 
        AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$patient_pk, $hours_limit]);
    $readings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // C. Format data for Chart.js (Using raw timestamp for 'time' axis)
    $response_data = [
        'heart_rate' => [],
        'spo2' => [],
        'labels' => [], 
        'status' => 'success'
    ];

    foreach ($readings as $reading) {
        $response_data['heart_rate'][] = (float)$reading['heart_rate'];
        $response_data['spo2'][] = (float)$reading['spo2'];
        // Using raw timestamp for time-series axis compatibility (e.g., YYYY-MM-DD HH:MM:SS)
        $response_data['labels'][] = $reading['timestamp'];
    }

    echo json_encode($response_data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error during data retrieval.']);
}
?>