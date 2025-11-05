<?php
/**
 * API ENDPOINT: fetch_history_data.php
 * Dynamically fetches either 'readings' or 'alerts' data for the logged-in patient.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['status' => 'error', 'message' => 'Authorization required.', 'data' => []];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(401);
    echo json_encode($response);
    exit;
}

require_once '../engine/db_config.php';

$patient_id = $_SESSION['user_id'];
$data_type = $_GET['type'] ?? 'readings'; // Default to 'readings'

// 1. Get the internal patient_pk
try {
    $stmt_pk = $pdo->prepare("SELECT patient_pk FROM patients WHERE user_fk = ?");
    $stmt_pk->execute([$patient_id]);
    $patient_pk = $stmt_pk->fetchColumn();

    if (!$patient_pk) {
        throw new Exception("Patient profile not found.");
    }
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    http_response_code(404);
    echo json_encode($response);
    exit;
} catch (PDOException $e) {
    $response['message'] = "Database error: " . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
    exit;
}


// 2. Fetch data based on type
try {
    if ($data_type === 'alerts') {
        $stmt = $pdo->prepare("
            SELECT alert_type, alert_message, value, recorded_at AS timestamp, status
            FROM alerts
            WHERE patient_fk = ?
            ORDER BY recorded_at DESC
        ");
        
    } else { // default to 'readings'
        $stmt = $pdo->prepare("
            SELECT timestamp, heart_rate, spo2, temperature, acc_ax, acc_ay, acc_az
            FROM readings
            WHERE patient_fk = ?
            ORDER BY timestamp DESC
        ");
    }
    
    $stmt->execute([$patient_pk]);
    $history_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['status'] = 'success';
    $response['message'] = 'Data fetched successfully.';
    $response['data'] = $history_data;
    $response['type'] = $data_type;

} catch (PDOException $e) {
    $response['message'] = 'Database error fetching history: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>