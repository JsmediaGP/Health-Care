<?php
/**
 * DEVICE API: device_submit_reading.php
 * PURPOSE: Receives IoT sensor data, validates, stores readings,
 * and raises alerts when vital signs are abnormal.
 * AUTHOR: Js'Media Health IoT Project
 * VERSION: 3.0 (2025-11)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../engine/db_config.php';
require_once '../engine/functions.php';

$response = ['status' => 'error', 'message' => 'Unknown error.'];

// === STEP 1: VALIDATE REQUEST ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Only POST requests allowed.';
    echo json_encode($response); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    $response['message'] = 'Invalid JSON payload.';
    echo json_encode($response); exit;
}

// === STEP 2: REQUIRED FIELDS ===
$required = ['pid', 'heart_rate', 'spo2', 'temp', 'ax', 'ay', 'az'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(422);
        $response['message'] = "Missing required field: {$field}.";
        echo json_encode($response); exit;
    }
}

// === STEP 3: SANITIZE INPUT ===
$pid        = trim($data['pid']);
$heart_rate = (float) $data['heart_rate'];
$spo2       = (float) $data['spo2'];
$temp       = (float) $data['temp'];
$ax         = (float) $data['ax'];
$ay         = (float) $data['ay'];
$az         = (float) $data['az'];
$signal     = isset($data['signal_status']) ? strtoupper(trim($data['signal_status'])) : 'UNKNOWN';

// === STEP 4: PATIENT VALIDATION ===
try {
    $stmt = $pdo->prepare("SELECT patient_pk FROM patients WHERE user_fk = ?");
    $stmt->execute([$pid]);
    $patient_pk = $stmt->fetchColumn();

    if (!$patient_pk) {
        http_response_code(403);
        $response['message'] = "Unauthorized PID: {$pid}";
        echo json_encode($response); exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Database error during patient lookup.';
    echo json_encode($response); exit;
}

// === STEP 5: STORE VALID READINGS ===
try {
    // Skip insertion if the signal is clearly invalid
    if ($signal !== 'NO_SIGNAL') {
        $insert = $pdo->prepare("
            INSERT INTO readings (patient_fk, heart_rate, spo2, temperature, acc_ax, acc_ay, acc_az)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$patient_pk, $heart_rate, $spo2, $temp, $ax, $ay, $az]);
    }

    // === STEP 6: ABNORMAL VALUE DETECTION ===
    $abnormal = [];
    if ($heart_rate < 50 || $heart_rate > 120) {
        $abnormal[] = "Heart rate abnormal ({$heart_rate} bpm)";
    }
    if ($spo2 < 94) {
        $abnormal[] = "Oxygen level low ({$spo2}%)";
    }
    if ($temp < 35.5 || $temp > 37.5) {
        $abnormal[] = "Body temperature abnormal ({$temp}°C)";
    }

    // === STEP 7: LOG ALERT IF ANY ===
    if (!empty($abnormal)) {
        $alert_message = implode('; ', $abnormal);
        $alert = $pdo->prepare("
            INSERT INTO alerts (patient_fk, alert_type, alert_message, value)
            VALUES (?, 'Abnormal Reading', ?, ?)
        ");
        $alert->execute([$patient_pk, $alert_message, $heart_rate]);
    }

    // === STEP 8: SUCCESS RESPONSE ===
    http_response_code(201);
    $response = [
        'status' => 'success',
        'message' => 'Data processed successfully.',
        'data' => [
            'pid' => $pid,
            'signal_status' => $signal,
            'abnormalities' => $abnormal,
            'recorded_at' => date('Y-m-d H:i:s')
        ]
    ];

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Database insertion failed.';
}

// === FINAL OUTPUT ===
echo json_encode($response, JSON_PRETTY_PRINT);
?>
