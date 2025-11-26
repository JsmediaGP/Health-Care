<?php
/**
 * DOCTOR PATIENT DETAILS PAGE (General Monitoring View)
 * Displays Live Vitals Grid and Split Profile/Dynamic History View using AJAX.
 */

$page_title = "Patient Detail View";
$required_role = "doctor"; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../includes/header.php'; // Includes DB connection ($pdo) and auth check

$patient_id = $_GET['pid'] ?? null; // Patient's user_id (VARCHAR)
$doctor_user_id = $_SESSION['user_id'];
$error_message = null;

// Initialize variables
$patient_data = [];
$latest_reading = [];
$patient_pk = null; // Important for the JS script

if (!$patient_id) {
    $error_message = "Error: No Patient ID provided for details view.";
    goto render_page;
}

try {
    // 1. Get Doctor's Primary Key (PK)
    $stmt_doc = $pdo->prepare("SELECT doctor_pk FROM doctors WHERE user_fk = ?");
    $stmt_doc->execute([$doctor_user_id]);
    $doctor_pk = $stmt_doc->fetchColumn();

    if (!$doctor_pk) {
        $error_message = "Your doctor profile could not be found.";
        goto render_page;
    }

    // 2. Fetch Patient Data (including email via JOIN and address)
    $stmt_pat = $pdo->prepare("
        SELECT 
            p.patient_pk, 
            p.first_name, 
            p.last_name, 
            p.address,
            u.email
        FROM patients p
        JOIN users u ON p.user_fk = u.user_id
        WHERE p.user_fk = ? AND p.assigned_doctor_fk = ?
    ");
    $stmt_pat->execute([$patient_id, $doctor_pk]);
    $patient_data = $stmt_pat->fetch(PDO::FETCH_ASSOC);

    if (!$patient_data) {
        $error_message = "Patient not found or not assigned to you.";
        goto render_page;
    }
    $patient_pk = $patient_data['patient_pk']; // Set the critical patient_pk variable

    // 3. Fetch Latest Reading
    $stmt_latest = $pdo->prepare("
        SELECT heart_rate, spo2, temperature, acc_ax, acc_ay, acc_az, timestamp
        FROM readings
        WHERE patient_fk = ?
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt_latest->execute([$patient_pk]);
    // The result is assigned to $latest_reading, preventing the 'Undefined variable' warning
    $latest_reading = $stmt_latest->fetch(PDO::FETCH_ASSOC); 
    
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

render_page:

// --- Initial Data Assignment (for first load, later updated by JS) ---
// Note: The null-coalescing operator (??) prevents 'Trying to access array offset on value of type null' if $latest_reading is false/empty.
$heart_rate = $latest_reading['heart_rate'] ?? 'N/A';
$spo2 = $latest_reading['spo2'] ?? 'N/A';
$temperature = $latest_reading['temperature'] ?? 'N/A';
$acc_ax = $latest_reading['acc_ax'] ?? 'N/A';
$acc_ay = $latest_reading['acc_ay'] ?? 'N/A';
$acc_az = $latest_reading['acc_az'] ?? 'N/A';

$last_updated = $latest_reading['timestamp'] ? date('M d, Y h:i A', strtotime($latest_reading['timestamp'])) : 'No data yet.';

$acc_magnitude = (is_numeric($acc_ax) && is_numeric($acc_ay) && is_numeric($acc_az))
    ? number_format(sqrt($acc_ax * $acc_ax + $acc_ay * $acc_ay + $acc_az * $acc_az), 2)
    : 'N/A';

$is_hr_alert = (is_numeric($heart_rate) && ($heart_rate > 120 || $heart_rate < 50));
$is_spo2_alert = (is_numeric($spo2) && $spo2 < 95);
$is_temp_alert = (is_numeric($temperature) && $temperature >= 37.8);
$is_overall_alert = $is_hr_alert || $is_spo2_alert || $is_temp_alert;

?>
<main class="container mt-4">
    <h2 class="mb-4">
        <i class="fas fa-user-injured"></i> Patient File: **<?= htmlspecialchars($patient_data['first_name'] ?? 'N/A') . ' ' . htmlspecialchars($patient_data['last_name'] ?? '') ?>**
    </h2>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
        <?php include '../../includes/footer.php'; exit; ?>
    <?php endif; ?>

    <h3 class="mt-4 mb-3 text-success"><i class="fas fa-chart-line"></i> **Live Vitals Snapshot**</h3>
    <section class="dashboard-grid" id="live-vitals-grid">
        <div class="metric-card heart-rate-card card">
            <div class="icon-box"><i class="fas fa-heartbeat"></i></div>
            <h3>Heart Rate (BPM)</h3>
            <p id="live-hr" class="data-value <?= $is_hr_alert ? 'alert-text' : '' ?>"><?= $heart_rate ?></p>
            <p class="status-indicator <?= $is_hr_alert ? 'alert' : 'normal' ?>">
                <i class="fas fa-circle"></i> Status: <?= $is_hr_alert ? 'ALERT' : 'Normal' ?>
            </p>
        </div>
        
        <div class="metric-card spo2-card card">
            <div class="icon-box"><i class="fas fa-lungs"></i></div>
            <h3>Oxygen Saturation ($\text{SpO}_2$ %)</h3>
            <p id="live-spo2" class="data-value <?= $is_spo2_alert ? 'alert-text' : '' ?>"><?= $spo2 ?></p>
            <p class="status-indicator <?= $is_spo2_alert ? 'alert' : 'normal' ?>">
                <i class="fas fa-circle"></i> Status: <?= $is_spo2_alert ? 'Low' : 'Healthy' ?>
            </p>
        </div>
        
        <div class="metric-card temp-card card">
            <div class="icon-box"><i class="fas fa-thermometer-half"></i></div>
            <h3>Temperature ($\circ\text{C}$)</h3>
            <p id="live-temp" class="data-value <?= $is_temp_alert ? 'alert-text' : '' ?>">
                <?= is_numeric($temperature) ? number_format($temperature, 2) : $temperature ?>
            </p>
            <p class="status-indicator <?= $is_temp_alert ? 'alert' : 'normal' ?>">
                <i class="fas fa-circle"></i> Status: <?= $is_temp_alert ? 'Fever' : 'Normal' ?>
            </p>
        </div>
        
        <div class="metric-card acc-card card">
            <div class="icon-box"><i class="fas fa-running"></i></div>
            <h3>Acceleration (G)</h3>
            <p class="data-value"><?= $acc_magnitude ?></p>
            <p class="status-indicator normal">
                <i class="fas fa-info-circle"></i> 
                X: <?= is_numeric($acc_ax) ? number_format($acc_ax, 2) : 'N/A' ?> | 
                Y: <?= is_numeric($acc_ay) ? number_format($acc_ay, 2) : 'N/A' ?> | 
                Z: <?= is_numeric($acc_az) ? number_format($acc_az, 2) : 'N/A' ?>
            </p>
        </div>

        <div class="metric-card latest-reading-summary card">
            <h3><i class="fas fa-notes-medical"></i> Quick Status Check</h3>
            <p class="status-indicator <?= $is_overall_alert ? 'alert' : 'normal' ?>">
                <i class="fas fa-circle"></i> Overall Status: 
                <?= $is_overall_alert ? 'Review Required' : 'Stable' ?>
            </p>
            <p class="mt-2 text-muted">
                <i class="fas fa-clock"></i> Last Update: <span id="live-update-time" class="updated-time font-weight-bold">
                    <?= $last_updated ?>
                </span>
            </p>
        </div>
    </section>

    <h3 class="mt-5 mb-3"><i class="fas fa-file-alt"></i> **Patient Record Details**</h3>
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-secondary text-white">Patient Profile</div>
                <div class="card-body">
                    <p class="mb-1">**Name:** <?= htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']) ?></p>
                    <p class="mb-1">**Patient ID:** <?= htmlspecialchars($patient_id) ?></p>
                    <p class="mb-1">**Email:** <?= htmlspecialchars($patient_data['email'] ?? 'N/A') ?></p>
                    <p class="mb-1">**Address:** <?= htmlspecialchars($patient_data['address'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center bg-light">
                    <div class="btn-group btn-group-sm history-toggle" role="group">
                        <button type="button" class="btn btn-primary active" id="btn-readings-history">Readings</button>
                        <button type="button" class="btn btn-secondary" id="btn-alerts-history">Alerts</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="history-data-view">
                        <p class="text-center text-muted mt-5">Loading history...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    const PATIENT_PK = '<?= $patient_pk ?? '0' ?>';
    const PATIENT_ID = '<?= $patient_id ?>';
</script>
<script src="../../assets/js/patient_details_doctor.js"></script>
<?php include '../../includes/footer.php'; ?>