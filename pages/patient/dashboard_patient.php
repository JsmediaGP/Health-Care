<?php
// Define required variables before including the header
$page_title = "Patient Dashboard";
$required_role = "patient";

// 1. Authorization Check (CRUCIAL SECURITY STEP)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    // Redirect to login page
    header('Location: ../../index.php'); 
    exit;
}

// Now include the header, which also loads the DB connection and patient name
include '../../includes/header.php'; // NOTE: header.php must load Moment.js and Chart.js

// Current Patient ID from session
$patient_id = $_SESSION['user_id']; 

// 2. Fetch Latest Vital Signs (All 7 fields)
try {
    // Query to get the latest reading based on the user_fk (PID)
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
    $latest_reading = $stmt_latest->fetch();

    $heart_rate = $latest_reading['heart_rate'] ?? 'N/A';
    $spo2 = $latest_reading['spo2'] ?? 'N/A';
    $temperature = $latest_reading['temperature'] ?? 'N/A';
    $acc_ax = $latest_reading['acc_ax'] ?? 'N/A';
    $acc_ay = $latest_reading['acc_ay'] ?? 'N/A';
    $acc_az = $latest_reading['acc_az'] ?? 'N/A';
    $last_updated = $latest_reading['timestamp'] ? date('M d, Y h:i A', strtotime($latest_reading['timestamp'])) : 'No data yet.';
    
    // Calculate Acceleration Magnitude for simplified display
    $acc_magnitude = (is_numeric($acc_ax) && is_numeric($acc_ay) && is_numeric($acc_az))
        ? sqrt($acc_ax * $acc_ax + $acc_ay * $acc_ay + $acc_az * $acc_az)
        : 'N/A';

} catch (PDOException $e) {
    // Simple error handling
    $latest_reading = null;
    $heart_rate = $spo2 = $temperature = $acc_ax = $acc_ay = $acc_az = 'DB Error';
    $acc_magnitude = 'DB Error';
    $last_updated = 'Could not fetch data.';
}

// --- Define Clinical Alert Conditions (Based on Pregnancy Vitals) ---
$is_hr_alert = (is_numeric($heart_rate) && ($heart_rate > 120 || $heart_rate < 50));
$is_spo2_alert = (is_numeric($spo2) && $spo2 < 95);
$is_temp_alert = (is_numeric($temperature) && $temperature >= 37.8);
$is_overall_alert = $is_hr_alert || $is_spo2_alert || $is_temp_alert;

?>

<section class="dashboard-grid">
    
    <div class="metric-card heart-rate-card card">
        <div class="icon-box"><i class="fas fa-heartbeat"></i></div>
        <h3>Heart Rate (BPM)</h3>
        <p class="data-value"><?= $heart_rate ?></p>
        <p class="status-indicator <?= $is_hr_alert ? 'alert' : 'normal' ?>">
            <i class="fas fa-circle"></i> Status: <?= $is_hr_alert ? 'ALERT' : 'Normal' ?>
        </p>
    </div>

    <div class="metric-card spo2-card card">
        <div class="icon-box"><i class="fas fa-lungs"></i></div>
        <h3>Oxygen Saturation ($\text{SpO}_2$ %)</h3>
        <p class="data-value"><?= $spo2 ?></p>
        <p class="status-indicator <?= $is_spo2_alert ? 'alert' : 'normal' ?>">
            <i class="fas fa-circle"></i> Status: <?= $is_spo2_alert ? 'Low' : 'Healthy' ?>
        </p>
    </div>

    <div class="metric-card temp-card card">
        <div class="icon-box"><i class="fas fa-thermometer-half"></i></div>
        <h3>Temperature ($\circ\text{C}$)</h3>
        <p class="data-value"><?= is_numeric($temperature) ? number_format($temperature, 2) : $temperature ?></p>
        <p class="status-indicator <?= $is_temp_alert ? 'alert' : 'normal' ?>">
            <i class="fas fa-circle"></i> Status: <?= $is_temp_alert ? 'Fever' : 'Normal' ?>
        </p>
    </div>

    <div class="metric-card latest-reading-summary card">
        <h3><i class="fas fa-notes-medical"></i> Quick Status Check</h3>
        <div class="vitals-row">
            <div class="vitals-item">
                <span class="value-label">Heart Rate:</span>
                <span class="value <?= $is_hr_alert ? 'alert-text' : 'normal-text' ?>">
                    <?= $heart_rate ?> BPM
                </span>
            </div>
            <div class="vitals-item">
                <span class="value-label">SpO2:</span>
                <span class="value <?= $is_spo2_alert ? 'alert-text' : 'normal-text' ?>">
                    <?= $spo2 ?> %
                </span>
            </div>
        </div>

        <p class="status-indicator <?= $is_overall_alert ? 'alert' : 'normal' ?>">
            <i class="fas fa-circle"></i> Overall Status: 
            <?= $is_overall_alert ? 'Review Required' : 'Stable' ?>
        </p>
    </div>

    <div class="metric-card acc-card card">
        <div class="icon-box"><i class="fas fa-running"></i></div>
        <h3>Acceleration (G)</h3>
        <p class="data-value"><?= is_numeric($acc_magnitude) ? number_format($acc_magnitude, 2) : $acc_magnitude ?></p>
        <p class="status-indicator normal">
            <i class="fas fa-info-circle"></i> Components: X: <?= number_format($acc_ax, 2) ?> | Y: <?= number_format($acc_ay, 2) ?> | Z: <?= number_format($acc_az, 2) ?>
        </p>
    </div>
    
    <div class="metric-card doctor-info-card card">
        <h3><i class="fas fa-user-md"></i> Assigned Physician</h3>
        <?php 
        $stmt_doctor = $pdo->prepare("SELECT assigned_doctor_fk FROM patients WHERE user_fk = ?");
        $stmt_doctor->execute([$patient_id]);
        $assignment = $stmt_doctor->fetchColumn();
        
        if ($assignment): 
        ?>
            <p>Your doctor is monitoring your vitals. <a href="profile.php">Check profile for contact details.</a></p>
        <?php else: ?>
            <p class="unassigned-alert"><i class="fas fa-exclamation-circle"></i> You are **currently unassigned**.</p>
        <?php endif; ?>
    </div>
    
    <div class="chart-container card full-width">
        <h3>Heart Rate & $\text{SpO}_2$ Trend (Last 24 Hrs)</h3>
        <div style="height: 350px; margin-top: 15px;"> 
            <canvas id="vitalsChart"></canvas>
        </div>
        <p id="chartError" class="text-center text-danger"></p> 
    </div>
    
    <div class="metric-card timestamp-card card">
        <div class="icon-box"><i class="fas fa-clock"></i></div>
        <h3>Last Reading</h3>
        <p class="data-value updated-time"><?= $last_updated ?></p>
        <p class="status-indicator normal"><i class="fas fa-info-circle"></i> Data from your IoT Device.</p>
    </div>

</section>

<?php 
// 6. Include the footer file, which loads patient_charts.js
include '../../includes/footer.php'; 
?>