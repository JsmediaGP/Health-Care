<?php
// Define required variables before including the header
$page_title = "Patient Detail View";
$required_role = "doctor"; 

// Include Header (handles security, session check, DB connection ($pdo), etc.)
include '../../includes/header.php'; 

// 1. Get Patient ID from URL
$patient_id = $_GET['pid'] ?? null;

if (!$patient_id) {
    echo '<div class="error-message">Error: No Patient ID provided.</div>';
    include '../../includes/footer.php';
    exit;
}

// Ensure the logged-in doctor is assigned to this patient (Security/Authorization Check)
try {
    $doctor_user_id = $_SESSION['user_id'];
    
    // First, get the logged-in doctor's internal PK
    $stmt_doc_pk = $pdo->prepare("SELECT doctor_pk FROM doctors WHERE user_fk = ?");
    $stmt_doc_pk->execute([$doctor_user_id]);
    $doctor_pk = $stmt_doc_pk->fetchColumn();

    // Query: Check if the patient is assigned to this doctor and fetch necessary details
    $stmt_assignment = $pdo->prepare("
        SELECT 
            p.first_name, 
            p.last_name, 
            p.address, 
            p.patient_pk,
            t1.email 
        FROM patients p
        JOIN users t1 ON p.user_fk = t1.user_id 
        WHERE p.user_fk = ? AND p.assigned_doctor_fk = ?
    ");
    $stmt_assignment->execute([$patient_id, $doctor_pk]);
    $patient_data = $stmt_assignment->fetch(PDO::FETCH_ASSOC);

    if (!$patient_data) {
        echo '<div class="error-message">Authorization Error: This patient is not assigned to you.</div>';
        include '../../includes/footer.php';
        exit;
    }

    $patient_pk = $patient_data['patient_pk']; // Store the internal PK for readings query later
    $patient_full_name = $patient_data['first_name'] . ' ' . $patient_data['last_name'];
    $patient_email = $patient_data['email']; // NEW: Extract the email
    
} catch (PDOException $e) {
    echo '<div class="error-message">Database Error: Could not verify patient assignment.</div>';
    include '../../includes/footer.php';
    exit;
}

// 2. Fetch Latest Reading (for quick summary)
$latest_reading = null;
$stmt_latest = $pdo->prepare("
    SELECT heart_rate, spo2, timestamp 
    FROM readings 
    WHERE patient_fk = ?
    ORDER BY timestamp DESC 
    LIMIT 1
");
$stmt_latest->execute([$patient_pk]);
$latest_reading = $stmt_latest->fetch(PDO::FETCH_ASSOC);

$hr_value = $latest_reading['heart_rate'] ?? 'N/A';
$spo2_value = $latest_reading['spo2'] ?? 'N/A';
$last_updated = $latest_reading['timestamp'] ? date('M d, Y h:i A', strtotime($latest_reading['timestamp'])) : 'No data yet.';

?>

<section class="detail-header">
    <a href="dashboard_doctor.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Triage List</a>
    <h1>Patient: <?= $patient_full_name ?> (PID: <?= $patient_id ?>)</h1>
</section>

<section class="detail-summary-grid">
    <div class="metric-card heart-rate-card">
        <h3>Latest Heart Rate (BPM)</h3>
        <p class="data-value"><?= $hr_value ?></p>
        <p class="status-indicator <?= ($hr_value > 100 || $hr_value < 60) && is_numeric($hr_value) ? 'alert' : 'normal' ?>">
            <i class="fas fa-clock"></i> Updated: <?= date('h:i A', strtotime($last_updated)) ?>
        </p>
    </div>

    <div class="metric-card spo2-card">
        <h3>Latest SpO2 (%)</h3>
        <p class="data-value"><?= $spo2_value ?></p>
        <p class="status-indicator <?= ($spo2_value < 95) && is_numeric($spo2_value) ? 'alert' : 'normal' ?>">
            <i class="fas fa-clock"></i> Updated: <?= date('h:i A', strtotime($last_updated)) ?>
        </p>
    </div>

    <div class="info-card">
        <h3>Patient Contact Info</h3>
        <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($patient_email) ?>"><?= htmlspecialchars($patient_email) ?></a></p> <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($patient_data['address'])) ?></p>
        <p><strong>Status:</strong> <span class="badge badge-assigned">Monitored</span></p>
        </div>
</section>


<div class="chart-container card">
    <h3>7-Day Vitals Trend</h3>
    <div class="chart-scroll-wrapper" style="height: 400px; overflow-x: auto;"> 
        
        <div class="chart-content-wrapper">
            <canvas id="patientVitalsChart"></canvas>
        </div>
    </div>
    <p id="chartError" class="text-center text-danger"></p>
</div>

<section class="detail-actions">
    <button class="btn-secondary"><i class="fas fa-file-pdf"></i> Generate PDF Report</button>
    <button class="btn-secondary alert"><i class="fas fa-cogs"></i> Adjust Alert Thresholds</button>
</section>

<?php 
// Include the footer file
include '../../includes/footer.php'; 
?>