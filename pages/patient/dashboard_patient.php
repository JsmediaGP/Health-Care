<?php
// Set a page title variable for the header
$page_title = "Patient Dashboard";
$required_role = "patient";

// 1. Authorization Check (CRUCIAL SECURITY STEP)
// This code ensures only logged-in patients can access the page.
// We use a simple session check for now, but in a full app, this would be a function call.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    // Use the index page for login/unauthorized access
    header('Location: ../../index.php'); 
    exit;
}
// Now include the header, which also loads the DB connection and patient name
include '../../includes/header.php';

// Current Patient ID from session
$patient_id = $_SESSION['user_id']; 

// 2. Fetch Latest Vital Signs
try {
    // Get the latest reading based on the user_fk (PID)
    $stmt_latest = $pdo->prepare("
        SELECT 
            t4.heart_rate, 
            t4.spo2, 
            t4.timestamp 
        FROM users t1
        JOIN patients t3 ON t1.user_id = t3.user_fk
        JOIN readings t4 ON t3.patient_pk = t4.patient_fk
        WHERE t1.user_id = ?
        ORDER BY t4.timestamp DESC 
        LIMIT 1
    ");
    $stmt_latest->execute([$patient_id]);
    $latest_reading = $stmt_latest->fetch();

    $heart_rate = $latest_reading['heart_rate'] ?? 'N/A';
    $spo2 = $latest_reading['spo2'] ?? 'N/A';
    $last_updated = $latest_reading['timestamp'] ? date('M d, Y h:i A', strtotime($latest_reading['timestamp'])) : 'No data yet.';

} catch (PDOException $e) {
    // Simple error handling
    $latest_reading = null;
    $heart_rate = 'DB Error';
    $spo2 = 'DB Error';
    $last_updated = 'Could not fetch data.';
}

?>

<section class="dashboard-grid">
    
    <div class="metric-card heart-rate-card">
        <div class="icon-box"><i class="fas fa-heartbeat"></i></div>
        <h3>Heart Rate (BPM)</h3>
        <p class="data-value"><?= $heart_rate ?></p>
        <p class="status-indicator <?= ($heart_rate > 100 || $heart_rate < 60) && is_numeric($heart_rate) ? 'alert' : 'normal' ?>">
            <i class="fas fa-circle"></i> Status: <?= ($heart_rate > 100 || $heart_rate < 60) && is_numeric($heart_rate) ? 'Check' : 'Normal' ?>
        </p>
    </div>

    <div class="metric-card spo2-card">
        <div class="icon-box"><i class="fas fa-lungs"></i></div>
        <h3>Oxygen Saturation ($\text{SpO}_2$ %)</h3>
        <p class="data-value"><?= $spo2 ?></p>
        <p class="status-indicator <?= ($spo2 < 95) && is_numeric($spo2) ? 'alert' : 'normal' ?>">
            <i class="fas fa-circle"></i> Status: <?= ($spo2 < 95) && is_numeric($spo2) ? 'Low' : 'Healthy' ?>
        </p>
    </div>

    <div class="metric-card timestamp-card">
        <div class="icon-box"><i class="fas fa-clock"></i></div>
        <h3>Last Reading</h3>
        <p class="data-value updated-time"><?= $last_updated ?></p>
        <p class="status-indicator normal"><i class="fas fa-info-circle"></i> Data from your IoT Device.</p>
    </div>

    <div class="chart-container">
        <h3>Heart Rate & $\text{SpO}_2$ Trend (Last 24 Hrs)</h3>
        <canvas id="vitalsChart"></canvas>
    </div>
    
    <div class="doctor-info-card">
        <h3><i class="fas fa-user-md"></i> Assigned Physician</h3>
        <?php 
        // This query is simplified. A full system would need a join to fetch Doctor Name.
        // For now, let's display the doctor assignment status.
        $stmt_doctor = $pdo->prepare("SELECT assigned_doctor_fk FROM patients WHERE user_fk = ?");
        $stmt_doctor->execute([$patient_id]);
        $assignment = $stmt_doctor->fetchColumn();
        
        if ($assignment): 
        ?>
            <p>You are currently assigned to a doctor (ID: <?= $assignment ?>). Check your profile for contact details.</p>
        <?php else: ?>
            <p class="unassigned-alert"><i class="fas fa-exclamation-circle"></i> You are **currently unassigned**. Please contact your clinic administrator.</p>
        <?php endif; ?>
    </div>

</section>

<?php 
// 6. Include the footer file
include '../../includes/footer.php'; 
?>