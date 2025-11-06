<?php
/**
 * DOCTOR PATIENT DETAILS PAGE
 * Shows the doctor a comprehensive view of a single patient's data.
 */

$page_title = "Patient Detail View";
$required_role = "doctor"; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Header handles auth check and DB connection ($pdo)
include '../../includes/header.php'; 

// 1. Get Patient ID from URL
$patient_id = $_GET['pid'] ?? null;

if (!$patient_id) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Error: No Patient ID provided.</div></div>";
    include '../../includes/footer.php';
    exit;
}

// Global data arrays initialized
$patient_data = [];
$latest_reading = [];
$reading_history = [];
$alert_history = [];
$error_message = null;

try {
    // Check if the current doctor is assigned to this patient.
    $stmt_doc = $pdo->prepare("SELECT doctor_pk FROM doctors WHERE user_fk = ?");
    $stmt_doc->execute([$_SESSION['user_id']]);
    $doctor_pk = $stmt_doc->fetchColumn();

    if (!$doctor_pk) {
        $error_message = "Doctor profile not found.";
        goto render_page;
    }

    // 2. Fetch Patient Profile and PK (Ensure patient is assigned to this doctor)
    $stmt = $pdo->prepare("
        SELECT 
            p.patient_pk, 
            p.first_name, 
            p.last_name, 
            p.address,
            u.email
        FROM patients p
        JOIN users u ON u.user_id = p.user_fk
        WHERE p.user_fk = ? AND p.assigned_doctor_fk = ?
    ");
    $stmt->execute([$patient_id, $doctor_pk]);
    $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient_data) {
        $error_message = "Patient ID {$patient_id} not found or is not assigned to you.";
        goto render_page;
    }
    
    $patient_pk = $patient_data['patient_pk'];

    // 3. Fetch Latest Reading Snapshot
    $stmt_latest = $pdo->prepare("
        SELECT heart_rate, spo2, temperature, timestamp
        FROM readings
        WHERE patient_fk = ?
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt_latest->execute([$patient_pk]);
    $latest_reading = $stmt_latest->fetch(PDO::FETCH_ASSOC);

    // 4. Fetch ALL Reading History (Full table)
    $stmt_readings = $pdo->prepare("
        SELECT heart_rate, spo2, temperature, acc_ax, acc_ay, acc_az, timestamp
        FROM readings
        WHERE patient_fk = ?
        ORDER BY timestamp DESC
    ");
    $stmt_readings->execute([$patient_pk]);
    $reading_history = $stmt_readings->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch ALL Alert History (Full table)
    $stmt_alerts = $pdo->prepare("
        SELECT alert_id, alert_type, alert_message, value, recorded_at, status
        FROM alerts
        WHERE patient_fk = ?
        ORDER BY recorded_at DESC
    ");
    $stmt_alerts->execute([$patient_pk]);
    $alert_history = $stmt_alerts->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

render_page: // Jump point
?>
<main class="container mt-4">
    <h2 class="mb-4">
        <i class="fas fa-user-injured"></i> Patient File: <?= htmlspecialchars($patient_data['first_name'] ?? 'N/A') . ' ' . htmlspecialchars($patient_data['last_name'] ?? '') ?>
    </h2>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
        <?php include '../../includes/footer.php'; exit; ?>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-heartbeat"></i> Latest Vitals Snapshot
            <span class="float-right small">Last Updated: <?= $latest_reading['timestamp'] ? date('M d, Y H:i:s', strtotime($latest_reading['timestamp'])) : 'No Recent Data' ?></span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 border-right">
                    <h5>Profile Info</h5>
                    <p class="mb-1"><strong>Patient ID:</strong> <?= htmlspecialchars($patient_id) ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($patient_data['email'] ?? 'N/A') ?></p>
                    <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($patient_data['address'] ?? 'No Address')) ?></p>
                </div>
                <div class="col-md-8">
                    <h5>Current Vitals</h5>
                    <div class="row">
                        <div class="col-sm-4 text-center">
                            <div class="stat-box p-3 border rounded">
                                <strong>Heart Rate (BPM)</strong>
                                <p class="h3 mb-0 <?= (isset($latest_reading['heart_rate']) && $latest_reading['heart_rate'] > 120) ? 'text-danger' : 'text-success' ?>">
                                    <?= $latest_reading['heart_rate'] ?? 'N/A' ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-sm-4 text-center">
                            <div class="stat-box p-3 border rounded">
                                <strong>SpO2 (%)</strong>
                                <p class="h3 mb-0 <?= (isset($latest_reading['spo2']) && $latest_reading['spo2'] < 95) ? 'text-danger' : 'text-success' ?>">
                                    <?= $latest_reading['spo2'] ?? 'N/A' ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-sm-4 text-center">
                            <div class="stat-box p-3 border rounded">
                                <strong>Temp ($\circ\text{C}$)</strong>
                                <p class="h3 mb-0 <?= (isset($latest_reading['temperature']) && $latest_reading['temperature'] >= 37.8) ? 'text-danger' : 'text-success' ?>">
                                    <?= number_format($latest_reading['temperature'] ?? 0, 2) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" id="patientDetailTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="full-readings-tab" data-toggle="tab" href="#full-readings" role="tab" aria-controls="full-readings" aria-selected="true">
                <i class="fas fa-table"></i> Full Reading History (<?= count($reading_history) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="all-alerts-tab" data-toggle="tab" href="#all-alerts" role="tab" aria-controls="all-alerts" aria-selected="false">
                <i class="fas fa-history"></i> Full Alert Log (<?= count($alert_history) ?>)
            </a>
        </li>
    </ul>

    <div class="tab-content card p-3 border border-top-0" id="patientDetailTabsContent">
        
        <div class="tab-pane fade show active" id="full-readings" role="tabpanel" aria-labelledby="full-readings-tab">
            <h3 class="mt-2 mb-3">Complete Sensor Readings</h3>
            <?php if (empty($reading_history)): ?>
                <p class="alert alert-info">No sensor data recorded for this patient.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>Timestamp</th>
                                <th>HR (BPM)</th>
                                <th>SpO2 (%)</th>
                                <th>Temp ($\circ\text{C}$)</th>
                                <th>Accel X</th>
                                <th>Accel Y</th>
                                <th>Accel Z</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reading_history as $reading): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i:s', strtotime($reading['timestamp'])) ?></td>
                                    <td><?= htmlspecialchars($reading['heart_rate']) ?></td>
                                    <td><?= htmlspecialchars($reading['spo2']) ?></td>
                                    <td><?= number_format($reading['temperature'], 2) ?></td>
                                    <td><?= number_format($reading['acc_ax'], 2) ?></td>
                                    <td><?= number_format($reading['acc_ay'], 2) ?></td>
                                    <td><?= number_format($reading['acc_az'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="all-alerts" role="tabpanel" aria-labelledby="all-alerts-tab">
            <h3 class="mt-2 mb-3">Clinical Alerts Log</h3>
            <p class="text-muted">Use the 'Mark Read' button to clear alerts from the main Triage Dashboard.</p>
            <?php if (empty($alert_history)): ?>
                <p class="alert alert-success">No abnormal readings have triggered an alert for this patient.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead class="thead-danger">
                            <tr>
                                <th>Time Recorded</th>
                                <th>Alert Type</th>
                                <th>Trigger Value</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alert_history as $alert): 
                                $is_unread = $alert['status'] === 'UNREAD';
                            ?>
                                <tr class="<?= $is_unread ? 'table-warning' : '' ?>">
                                    <td><?= date('M d, Y H:i:s', strtotime($alert['recorded_at'])) ?></td>
                                    <td><span class="badge badge-danger"><?= htmlspecialchars($alert['alert_type']) ?></span></td>
                                    <td><?= number_format($alert['value'], 2) ?></td>
                                    <td><?= htmlspecialchars($alert['alert_message']) ?></td>
                                    <td id="status-<?= $alert['alert_id'] ?>">
                                        <span class="badge badge-<?= $is_unread ? 'warning' : 'success' ?>">
                                            <?= htmlspecialchars($alert['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($is_unread): ?>
                                            <button class="btn btn-sm btn-outline-primary mark-read-btn" data-alert-id="<?= $alert['alert_id'] ?>">Mark Read</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>