<?php
/**
 * DOCTOR ALERT RESOLUTION PAGE (PAGE 2)
 * Primary purpose: Display pending alerts and provide a resolution action.
 */

$page_title = "Alert Resolution Center";
$required_role = "doctor";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../includes/header.php'; // Includes DB connection ($pdo) and auth check

$patient_id = $_GET['pid'] ?? null;
$doctor_user_id = $_SESSION['user_id'];
$error_message = null;

// Data containers
$patient_data = [];
$latest_reading = [];
$pending_alerts = [];

if (!$patient_id) {
    $error_message = "Error: No Patient ID provided for triage.";
    goto render_page;
}

try {
    // A. Get Doctor and Patient PKs (UPDATED QUERY)
    $stmt_doc = $pdo->prepare("SELECT doctor_pk FROM doctors WHERE user_fk = ?");
    $stmt_doc->execute([$doctor_user_id]);
    $doctor_pk = $stmt_doc->fetchColumn();

    if (!$doctor_pk) {
        $error_message = "Your doctor profile could not be found.";
        goto render_page;
    }

    // FIX: Join users table to get email, and add address from patients table.
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
    $patient_pk = $patient_data['patient_pk'];

    // B. Fetch Latest Reading (for the Live Vitals Grid)
    $stmt_latest = $pdo->prepare("
        SELECT heart_rate, spo2, temperature, acc_ax, acc_ay, acc_az, timestamp
        FROM readings
        WHERE patient_fk = ?
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt_latest->execute([$patient_pk]);
    $latest_reading = $stmt_latest->fetch(PDO::FETCH_ASSOC);
    
    // C. Fetch all UNREAD Alerts for this patient (the core of the page)
    $stmt_alerts = $pdo->prepare("
        SELECT alert_id, alert_type, alert_message, value, recorded_at
        FROM alerts
        WHERE patient_fk = ? AND status = 'UNREAD'
        ORDER BY recorded_at DESC
    ");
    $stmt_alerts->execute([$patient_pk]);
    $pending_alerts = $stmt_alerts->fetchAll(PDO::FETCH_ASSOC);

    // Initial Data Assignment for Grid Display (unchanged)
    $hr = $latest_reading['heart_rate'] ?? 'N/A';
    $spo2 = $latest_reading['spo2'] ?? 'N/A';
    $temp = $latest_reading['temperature'] ?? 'N/A';
    $acc_ax = $latest_reading['acc_ax'] ?? 'N/A';
    $acc_ay = $latest_reading['acc_ay'] ?? 'N/A';
    $acc_az = $latest_reading['acc_az'] ?? 'N/A';
    
    $acc_magnitude = (is_numeric($acc_ax) && is_numeric($acc_ay) && is_numeric($acc_az))
        ? number_format(sqrt($acc_ax * $acc_ax + $acc_ay * $acc_ay + $acc_az * $acc_az), 2)
        : 'N/A';
    
    $is_hr_alert = (is_numeric($hr) && ($hr > 120 || $hr < 50));
    $is_spo2_alert = (is_numeric($spo2) && $spo2 < 95);
    $is_temp_alert = (is_numeric($temp) && $temp >= 37.8);
    $is_overall_alert = $is_hr_alert || $is_spo2_alert || $is_temp_alert;


} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

render_page:
?>
<main class="container mt-4">
    <h2 class="mb-4 text-danger"><i class="fas fa-gavel"></i> Alert Resolution for: **<?= htmlspecialchars($patient_data['first_name'] ?? 'N/A') . ' ' . htmlspecialchars($patient_data['last_name'] ?? '') ?>**</h2>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
        <?php include '../../includes/footer.php'; exit; ?>
    <?php endif; ?>

    <div class="card mb-4 shadow-lg border-danger">
        <div class="card-header bg-danger text-white">
            <h4 class="mb-0"><i class="fas fa-list-ol"></i> **Pending Alerts Requiring Resolution**</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <?php if (count($pending_alerts) > 0): ?>
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr class="table-warning">
                            <th>Alert ID</th>
                            <th>Recorded At</th>
                            <th>Type</th>
                            <th>Trigger Value</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_alerts as $alert): ?>
                        <tr class="table-danger">
                            <td><?= htmlspecialchars($alert['alert_id']) ?></td>
                            <td><?= date('M d, Y H:i:s', strtotime($alert['recorded_at'])) ?></td>
                            <td><span class="badge badge-warning"><?= htmlspecialchars($alert['alert_type']) ?></span></td>
                            <td><?= number_format($alert['value'], 2) ?></td>
                            <td><?= htmlspecialchars($alert['alert_message']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="alert alert-success m-3">
                        <i class="fas fa-check-circle"></i> **No Pending Alerts** for this patient. You may switch to general monitoring.
                        <a href="patient_details.php?pid=<?= htmlspecialchars($patient_id) ?>" class="btn btn-sm btn-success float-right">Go to Patient Detail Page</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <h3 class="mt-4 mb-3 text-success"><i class="fas fa-chart-line"></i> **Current Vitals Check**</h3>
    <section class="dashboard-grid" id="live-vitals-grid-alert">
        <div class="metric-card heart-rate-card card">
            <div class="icon-box"><i class="fas fa-heartbeat"></i></div>
            <h3>Heart Rate (BPM)</h3>
            <p id="live-hr-alert" class="data-value <?= $is_hr_alert ? 'alert-text' : '' ?>"><?= $hr ?></p>
            <p class="status-indicator <?= $is_hr_alert ? 'alert' : 'normal' ?>">
                <i class="fas fa-circle"></i> Status: <?= $is_hr_alert ? 'ALERT' : 'Normal' ?>
            </p>
        </div>
        
        <div class="metric-card spo2-card card">
            <div class="icon-box"><i class="fas fa-lungs"></i></div>
            <h3>Oxygen Saturation ($\text{SpO}_2$ %)</h3>
            <p id="live-spo2-alert" class="data-value <?= $is_spo2_alert ? 'alert-text' : '' ?>"><?= $spo2 ?></p>
            <p class="status-indicator <?= $is_spo2_alert ? 'alert' : 'normal' ?>">
                <i class="fas fa-circle"></i> Status: <?= $is_spo2_alert ? 'Low' : 'Healthy' ?>
            </p>
        </div>
        
        <div class="metric-card temp-card card">
            <div class="icon-box"><i class="fas fa-thermometer-half"></i></div>
            <h3>Temperature ($\circ\text{C}$)</h3>
            <p id="live-temp-alert" class="data-value <?= $is_temp_alert ? 'alert-text' : '' ?>">
                <?= is_numeric($temp) ? number_format($temp, 2) : $temp ?>
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
                <i class="fas fa-info-circle"></i> X: <?= number_format($acc_ax, 2) ?> | Y: <?= number_format($acc_ay, 2) ?> | Z: <?= number_format($acc_az, 2) ?>
            </p>
        </div>
        
        <div class="metric-card latest-reading-summary card">
            <h3><i class="fas fa-notes-medical"></i> Quick Status Check</h3>
            <p class="status-indicator <?= $is_overall_alert ? 'alert' : 'normal' ?>">
                <i class="fas fa-circle"></i> Overall Status: 
                <?= $is_overall_alert ? 'Review Required' : 'Stable' ?>
            </p>
            <p class="mt-2 text-muted">
                <i class="fas fa-clock"></i> Last Update: <span id="live-update-time-alert" class="updated-time font-weight-bold">
                    <?= $latest_reading['timestamp'] ? date('M d, Y H:i:s', strtotime($latest_reading['timestamp'])) : 'Loading...' ?>
                </span>
            </p>
        </div>

    </section>

    <div class="card my-5 shadow">
        <div class="card-body row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1"><i class="fas fa-user-circle"></i> Patient Profile</h4>
                <p class="mb-0">**Name:** <?= htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']) ?></p>
                <p class="mb-0">**Email:** <?= htmlspecialchars($patient_data['email']) ?></p>
                <p class="text-muted small">Once resolved, the alerts will be moved to the patient's history log.</p>
            </div>
            <div class="col-md-4 text-right">
                <?php if (count($pending_alerts) > 0): ?>
                    <button id="resolve-alerts-btn" class="btn btn-lg btn-success">
                        <i class="fas fa-check-double"></i> Resolve All <?= count($pending_alerts) ?> Alerts
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

</main>

<script>
    const PATIENT_ID = '<?= $patient_id ?>';
</script>

<script src="../../assets/js/alert_page_doctor.js"></script>

<?php 
include '../../includes/footer.php'; 
?>