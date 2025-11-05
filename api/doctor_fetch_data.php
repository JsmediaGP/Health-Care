<?php
/**
 * DOCTOR_FETCH_DATA.PHP
 * Secure API endpoint for doctors to retrieve a specific patient's vital signs history.
 */
session_start();
header('Content-Type: application/json');

// --- 1. Security Check ---
// Verify user is logged in and has the 'doctor' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Access denied. Doctor login required.']);
    exit;
}

// 2. Include core files
// Adjust paths as necessary based on your folder structure (api/ to engine/)
require_once '../engine/db_config.php';
require_once '../engine/functions.php'; // For sanitizeInput

// 3. Get Patient ID (PID) from the request URL
$patient_id = sanitizeInput($_GET['pid'] ?? '');
if (empty($patient_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing Patient ID (pid).']);
    exit;
}

$user_id = $_SESSION['user_id'];
$days_limit = 7; // Fetch data for the last 7 days

try {
    // A. Authorization: Get the Doctor's internal PK (doctor_pk) from the session user_id (user_fk)
    $stmt_doc_pk = $pdo->prepare("SELECT doctor_pk FROM doctors WHERE user_fk = ?");
    $stmt_doc_pk->execute([$user_id]);
    $doctor_pk = $stmt_doc_pk->fetchColumn();

    if (!$doctor_pk) {
        http_response_code(500);
        echo json_encode(['error' => 'Doctor profile data incomplete.']);
        exit;
    }

    // B. Authorization: Verify the requested patient is assigned to this doctor 
    // AND get the patient's internal primary key (patient_pk)
    $stmt_auth = $pdo->prepare("
        SELECT patient_pk 
        FROM patients 
        WHERE user_fk = ? AND assigned_doctor_fk = ?
    ");
    $stmt_auth->execute([$patient_id, $doctor_pk]);
    $patient_pk = $stmt_auth->fetchColumn();

    if (!$patient_pk) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'Patient not assigned to you or patient not found.']);
        exit;
    }
    
    // C. Data Fetch: Retrieve historical readings
    $stmt = $pdo->prepare("
        SELECT 
            heart_rate, 
            spo2, 
            timestamp 
        FROM readings 
        WHERE patient_fk = ? 
        AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$patient_pk, $days_limit]);
    $readings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // D. Format data for Chart.js
    $response_data = [
        'heart_rate' => [],
        'spo2' => [],
        'labels' => [], 
        'status' => 'success'
    ];

    foreach ($readings as $reading) {
        $response_data['heart_rate'][] = (float)$reading['heart_rate'];
        $response_data['spo2'][] = (float)$reading['spo2'];
        // Format label for chart display
        // $response_data['labels'][] = date('M d, h:i A', strtotime($reading['timestamp']));
        // New (Use standard timestamp):
        $response_data['labels'][] = $reading['timestamp'];
    }

    echo json_encode($response_data);

} catch (PDOException $e) {
    http_response_code(500);
    // Remove $e->getMessage() in production
    echo json_encode(['error' => 'Database error during data retrieval: ' . $e->getMessage()]); 
}
?>