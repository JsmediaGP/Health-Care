<?php
/**
 * DOCTOR DASHBOARD (TRIAGE & OVERSIGHT VIEW)
 * Displays patients with pending alerts and a list of all assigned patients.
 */

// Define required variables before including the header
$page_title = "Doctor Dashboard";
$required_role = "doctor"; 

// Include Header (handles session check, DB connection ($pdo), and fetches $user_id)
include '../../includes/header.php'; 

// Initialize data arrays
$assigned_patients = [];
$triage_patients = [];
$alert_count = 0;
$error_message = null;

// Fetch the Doctor's internal primary key (doctor_pk)
try {
    $stmt_pk = $pdo->prepare("SELECT doctor_pk FROM doctors WHERE user_fk = ?");
    $stmt_pk->execute([$user_id]);
    $doctor_pk = $stmt_pk->fetchColumn();
    
    if (!$doctor_pk) {
        $error_message = "Doctor profile data not found.";
        goto render_page;
    }

    // --- Query 1: Fetch ALL assigned patients and their latest readings (OVERSIGHT LIST) ---
    $stmt_all_patients = $pdo->prepare("
        SELECT 
            p.patient_pk,
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
        ORDER BY p.last_name ASC
    ");
    $stmt_all_patients->execute([$doctor_pk]);
    $assigned_patients = $stmt_all_patients->fetchAll(PDO::FETCH_ASSOC);


    // --- Query 2: Fetch patients with UNREAD or UNRESOLVED Alerts (TRIAGE LIST) ---
    $stmt_triage = $pdo->prepare("
        SELECT 
            p.user_fk AS patient_id,
            p.first_name,
            p.last_name,
            a.alert_type,
            a.value AS alert_value,  /* CORRECTED COLUMN NAME: 'value' */
            a.recorded_at,
            a.status
        FROM patients p
        JOIN alerts a ON a.patient_fk = p.patient_pk
        -- Filtering for UNREAD status (most urgent to display)
        WHERE p.assigned_doctor_fk = ? AND a.status = 'UNREAD' 
        -- Grouping to ensure one row per patient, effectively showing the latest UNREAD alert
        GROUP BY p.patient_pk
        ORDER BY a.recorded_at DESC
    ");
    $stmt_triage->execute([$doctor_pk]);
    $triage_patients = $stmt_triage->fetchAll(PDO::FETCH_ASSOC);
    
    $alert_count = count($triage_patients);

} catch (PDOException $e) {
    $error_message = "Database Error: Unable to load patient data. " . $e->getMessage();
}

render_page:
?>

<section class="doctor-dashboard-main container mt-4">
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger mb-4"><?= $error_message ?></div>
    <?php endif; ?>

    <div class="doctor-stats-grid mb-4">
        <div class="stat-card total-patients card p-3">
            <h3><i class="fas fa-users"></i> Total Assigned Patients</h3>
            <p class="stat-value h1 mb-0"><?= count($assigned_patients) ?></p>
        </div>
        <div class="stat-card alert-patients card p-3 <?= $alert_count > 0 ? 'alert-active border-danger' : '' ?>">
            <h3><i class="fas fa-exclamation-triangle"></i> Pending Triage Alerts</h3>
            <p class="stat-value h1 mb-0 text-danger"><?= $alert_count ?></p>
        </div>
    </div>

    <ul class="nav nav-tabs" id="doctorTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="triage-tab" data-toggle="tab" href="#triage" role="tab" aria-controls="triage" aria-selected="true">
                <i class="fas fa-exclamation-circle"></i> **Triage List (Alerts)**
                <?php if ($alert_count > 0): ?>
                    <span class="badge badge-danger ml-2"><?= $alert_count ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="all-patients-tab" data-toggle="tab" href="#all-patients" role="tab" aria-controls="all-patients" aria-selected="false">
                <i class="fas fa-notes-medical"></i> All Patients (Oversight)
            </a>
        </li>
    </ul>

    <div class="tab-content card p-3" id="doctorTabsContent">
        
        <div class="tab-pane fade show active" id="triage" role="tabpanel" aria-labelledby="triage-tab">
            <h3 class="mt-2 mb-3">Patients with Pending Alerts</h3>
            <?php if (empty($triage_patients)): ?>
                <div class="no-patients-message alert alert-success text-center">
                    <i class="fas fa-check-circle"></i>
                    <p class="mb-0">All assigned patients are currently stable or alerts have been addressed.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="triage-table table table-hover">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Latest Alert Type</th>
                                <th>Trigger Value</th>
                                <th>Recorded At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($triage_patients as $patient): ?>
                                <tr class="table-danger"> <td><?= $patient['patient_id'] ?></td>
                                    <td><?= $patient['first_name'] . ' ' . $patient['last_name'] ?></td>
                                    <td><span class="badge badge-danger"><?= htmlspecialchars($patient['alert_type']) ?></span></td>
                                    <td><?= number_format($patient['alert_value'], 2) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($patient['recorded_at'])) ?></td>
                                    <td>
                                        <a href="patient_detail.php?pid=<?= $patient['patient_id'] ?>" class="btn btn-sm btn-danger"><i class="fas fa-eye"></i> View & Address</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="all-patients" role="tabpanel" aria-labelledby="all-patients-tab">
            <h3 class="mt-2 mb-3">All Assigned Patients and Vitals</h3>
            <?php if (empty($assigned_patients)): ?>
                <div class="no-patients-message alert alert-info text-center">
                    <i class="fas fa-user-times"></i>
                    <p class="mb-0">You currently have no patients assigned to you.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="triage-table table table-hover">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Latest HR (BPM)</th>
                                <th>Latest SpO2 (%)</th>
                                <th>Last Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_patients as $patient): 
                                $hr = $patient['heart_rate'] ?? 'N/A';
                                $spo2 = $patient['spo2'] ?? 'N/A';
                                $timestamp = $patient['timestamp'] ? date('Y-m-d H:i', strtotime($patient['timestamp'])) : 'No Reading';
                                
                                // Simple visual check for a warning color on the row
                                $status_class = (is_numeric($hr) && ($hr > 100 || $spo2 < 95)) ? 'table-warning' : '';
                            ?>
                            <tr class="<?= $status_class ?>">
                                <td><?= $patient['patient_id'] ?></td>
                                <td><?= $patient['first_name'] . ' ' . $patient['last_name'] ?></td>
                                <td><?= $hr ?></td>
                                <td><?= $spo2 ?></td>
                                <td><?= $timestamp ?></td>
                                <td>
                                    <a href="patient_detail.php?pid=<?= $patient['patient_id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php 
// Include the footer file 
include '../../includes/footer.php'; 
?>