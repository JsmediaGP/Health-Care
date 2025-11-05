<?php
/**
 * API ENDPOINT: fetch_latest_reading.php
 * Used by the patient dashboard (AJAX/Fetch Polling) to get the latest 
 * vital signs and update the cards without a page reload.
 */

// 1. Configuration and Setup
header('Content-Type: application/json');
// Allow only requests originating from your application's domain for security, 
// or set to * if necessary for local testing.
header('Access-Control-Allow-Origin: *'); 

// Start session to get the logged-in user ID
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set default response structure
$response = [
    'status' => 'error',
    'message' => 'Authorization required.',
    'heart_rate' => 'N/A', 
    'spo2' => 'N/A', 
    'temperature' => 'N/A', 
    'acc_ax' => 'N/A',
    'acc_ay' => 'N/A',
    'acc_az' => 'N/A',
    'last_updated' => 'N/A'
];

// 2. Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(401);
    echo json_encode($response);
    exit;
}

// Include DB config
require_once '../engine/db_config.php';
// require_once '../engine/functions.php'; // Not strictly needed here

$patient_id = $_SESSION['user_id']; 

// 3. Fetch Latest Vital Signs (Same query as the dashboard)
try {
    $stmt_latest = $pdo->prepare("
        SELECT 
            t4.heart_rate, 
            t4.spo2, 
            t4.temperature,
            t4.acc_ax, t4.acc_ay, t4.acc_az,
            t4.timestamp 
        FROM users t1
        JOIN patients t3 ON t1.user_id = t3.user_fk
        JOIN readings t4 ON t3.patient_pk = t4.patient_fk
        WHERE t1.user_id = ?
        ORDER BY t4.timestamp DESC 
        LIMIT 1
    ");
    $stmt_latest->execute([$patient_id]);
    $latest_reading = $stmt_latest->fetch(PDO::FETCH_ASSOC);

    if ($latest_reading) {
        $response['status'] = 'success';
        $response['message'] = 'Latest data retrieved.';
        
        // Map fetched data to response array
        $response['heart_rate'] = $latest_reading['heart_rate'];
        $response['spo2'] = $latest_reading['spo2'];
        $response['temperature'] = number_format($latest_reading['temperature'], 2); // Format temp for display
        $response['acc_ax'] = number_format($latest_reading['acc_ax'], 2);
        $response['acc_ay'] = number_format($latest_reading['acc_ay'], 2);
        $response['acc_az'] = number_format($latest_reading['acc_az'], 2);
        
        // Calculate magnitude for simplified update
        $ax = (float)$latest_reading['acc_ax'];
        $ay = (float)$latest_reading['acc_ay'];
        $az = (float)$latest_reading['acc_az'];
        $response['acc_magnitude'] = number_format(sqrt($ax * $ax + $ay * $ay + $az * $az), 2);

        $response['last_updated'] = date('M d, Y h:i A', strtotime($latest_reading['timestamp']));

        http_response_code(200);
    } else {
        $response['status'] = 'success'; // Treat "no data" as success, but indicate absence
        $response['message'] = 'No readings found yet.';
        http_response_code(200);
    }

} catch (PDOException $e) {
    $response['message'] = 'Database error: Could not fetch readings.';
    error_log("Patient Live Fetch Error: " . $e->getMessage()); // Log error for debugging
    http_response_code(500);
}

// 4. Final Output
echo json_encode($response);
?>