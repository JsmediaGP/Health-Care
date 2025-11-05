<?php
// Define required variables before including the header
$page_title = "Doctor Dashboard";
$required_role = "doctor"; 

// Include Header (handles session check, DB connection ($pdo), and fetches $user_id, $user_name)
include '../../includes/header.php'; 

// Fetch the Doctor's internal primary key (doctor_pk)
try {
    $stmt_pk = $pdo->prepare("SELECT doctor_pk FROM doctors WHERE user_fk = ?");
    $stmt_pk->execute([$user_id]);
    $doctor_pk = $stmt_pk->fetchColumn();
    
    if (!$doctor_pk) {
        // Fallback if doctor profile is missing
        echo "<p class='error-message'>Doctor profile data not found.</p>";
        include '../../includes/footer.php';
        exit;
    }

    // Query 1: Fetch all assigned patients and their latest readings
    $stmt_patients = $pdo->prepare("
        SELECT 
            p.user_fk AS patient_id, 
            p.first_name, 
            p.last_name,
            r.heart_rate,
            r.spo2,
            r.timestamp
        FROM patients p
        LEFT JOIN (
            -- Subquery to get only the LATEST reading for each patient
            SELECT 
                patient_fk, heart_rate, spo2, timestamp,
                ROW_NUMBER() OVER(PARTITION BY patient_fk ORDER BY timestamp DESC) as rn
            FROM readings
        ) r ON r.patient_fk = p.patient_pk AND r.rn = 1
        WHERE p.assigned_doctor_fk = ?
        ORDER BY r.timestamp DESC
    ");
    $stmt_patients->execute([$doctor_pk]);
    $assigned_patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

    // Query 2: Get total assigned count and patients in ALERT state (simplified check)
    $alert_count = 0;
    foreach ($assigned_patients as $patient) {
        // Simple Alert Logic: HR > 100 OR SpO2 < 95
        if ( ($patient['heart_rate'] > 100) || ($patient['spo2'] < 95) ) {
            $alert_count++;
        }
    }

} catch (PDOException $e) {
    echo "<p class='error-message'>Database Error: Unable to load patient data.</p>";
    $assigned_patients = [];
    $alert_count = 0;
}
?>

<section class="doctor-dashboard-main">
    <div class="doctor-stats-grid">
        <div class="stat-card total-patients">
            <h3><i class="fas fa-users"></i> Total Assigned Patients</h3>
            <p class="stat-value"><?= count($assigned_patients) ?></p>
        </div>
        <div class="stat-card alert-patients <?= $alert_count > 0 ? 'alert-active' : '' ?>">
            <h3><i class="fas fa-exclamation-triangle"></i> Patients in ALERT</h3>
            <p class="stat-value"><?= $alert_count ?></p>
        </div>
    </div>

    <div class="patient-list-container card">
        <h2><i class="fas fa-list-ul"></i> Assigned Patient Triage List</h2>
        
        <?php if (empty($assigned_patients)): ?>
            <div class="no-patients-message">
                <i class="fas fa-heart-circle-check"></i>
                <p>You currently have no patients assigned to you.</p>
            </div>
        <?php else: ?>
            <table class="triage-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Latest HR (BPM)</th>
                        <th>Latest SpO2 (%)</th>
                        <th>Last Updated</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_patients as $patient): 
                        $is_alert = ($patient['heart_rate'] > 100 || $patient['spo2'] < 95);
                        $status_class = $is_alert ? 'status-alert' : 'status-normal';
                        $status_text = $is_alert ? 'ALERT' : 'Normal';
                        $hr = $patient['heart_rate'] ?? 'N/A';
                        $spo2 = $patient['spo2'] ?? 'N/A';
                        $timestamp = $patient['timestamp'] ? date('Y-m-d H:i', strtotime($patient['timestamp'])) : 'No Reading';
                    ?>
                    <tr class="<?= $status_class ?>">
                        <td><?= $patient['patient_id'] ?></td>
                        <td><?= $patient['first_name'] . ' ' . $patient['last_name'] ?></td>
                        <td class="<?= ($hr > 100 && is_numeric($hr)) ? 'reading-high' : '' ?>"><?= $hr ?></td>
                        <td class="<?= ($spo2 < 95 && is_numeric($spo2)) ? 'reading-low' : '' ?>"><?= $spo2 ?></td>
                        <td><?= $timestamp ?></td>
                        <td class="<?= $status_class ?> status-cell">
                            <?= $status_text ?>
                        </td>
                        <td>
                            <a href="patient_detail.php?pid=<?= $patient['patient_id'] ?>" class="btn-view-detail"><i class="fas fa-eye"></i> View Detail</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</section>

<?php 
// Include the footer file (correct path: up two levels)
include '../../includes/footer.php'; 
?>