<?php
/**
 * DOCTOR DASHBOARD (TRIAGE & OVERVIEW)
 * 1. Key Metrics (Counts)
 * 2. Urgent Patient Table (Pending Alerts) -> Links to Alert Page
 * 3. All Assigned Patients Table (Monitoring) -> Links to Patient Detail Page
 */

$page_title = "Doctor Dashboard";
$required_role = "doctor";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../includes/header.php'; // Includes DB connection ($pdo) and auth check

$doctor_user_id = $_SESSION['user_id'];
$doctor_pk = null;
$error_message = null;

// Data containers
$counts = ['patients' => 0, 'pending_alerts' => 0];
$urgent_patients = [];
$all_patients = [];

try {
    // 1. Get the Doctor's Primary Key (PK)
    $stmt_doc = $pdo->prepare("SELECT doctor_pk FROM doctors WHERE user_fk = ?");
    $stmt_doc->execute([$doctor_user_id]);
    $doctor_pk = $stmt_doc->fetchColumn();

    if (!$doctor_pk) {
        $error_message = "Your doctor profile could not be found.";
        goto render_page;
    }

    // 2. Fetch All Assigned Patients (for the "All Patients" table and counts)
    $stmt_all = $pdo->prepare("
        SELECT 
            p.user_fk AS patient_id,
            p.patient_pk,
            p.first_name, 
            p.last_name,
            (
                SELECT COUNT(alert_id) 
                FROM alerts 
                WHERE patient_fk = p.patient_pk AND status = 'UNREAD'
            ) AS pending_alerts_count,
            (
                SELECT timestamp 
                FROM readings 
                WHERE patient_fk = p.patient_pk 
                ORDER BY timestamp DESC 
                LIMIT 1
            ) AS last_reading_time
        FROM patients p
        WHERE p.assigned_doctor_fk = ?
        ORDER BY p.last_name ASC
    ");
    $stmt_all->execute([$doctor_pk]);
    $all_patients = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    $counts['patients'] = count($all_patients);
    
    // 3. Separate Urgent Patients and calculate total pending alerts
    foreach ($all_patients as $patient) {
        $counts['pending_alerts'] += $patient['pending_alerts_count'];
        if ($patient['pending_alerts_count'] > 0) {
            $urgent_patients[] = $patient;
        }
    }

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

render_page:
?>
<main class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-notes-medical"></i> Doctor Dashboard: Triage Center</h2>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
        <?php include '../../includes/footer.php'; exit; ?>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-info text-white shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-users"></i> Total Assigned Patients</h5>
                        <h1 class="display-4"><?= $counts['patients'] ?></h1>
                    </div>
                    <p class="card-text">Total patients under your direct care.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card <?= ($counts['pending_alerts'] > 0) ? 'bg-danger' : 'bg-success' ?> text-white shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle"></i> Pending Triage Alerts</h5>
                        <h1 class="display-4"><?= $counts['pending_alerts'] ?></h1>
                    </div>
                    <p class="card-text">Alerts requiring immediate review and resolution.</p>
                </div>
            </div>
        </div>
    </div>

    <hr/>

    <div class="card mb-4 shadow-lg border-danger">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-bell"></i> **URGENT: Patients with Pending Alerts**</h4>
            <span class="badge badge-light badge-pill"><?= count($urgent_patients) ?> Patient(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <?php if (count($urgent_patients) > 0): ?>
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr class="table-warning">
                            <th>Patient Name</th>
                            <th>Pending Alerts</th>
                            <th>Last Reading</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($urgent_patients as $patient): ?>
                        <tr class="table-danger">
                            <td><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></td>
                            <td><span class="badge badge-danger"><?= $patient['pending_alerts_count'] ?></span></td>
                            <td><?= $patient['last_reading_time'] ? date('M d, Y H:i', strtotime($patient['last_reading_time'])) : 'N/A' ?></td>
                            <td>
                                <a href="alert_page.php?pid=<?= htmlspecialchars($patient['patient_id']) ?>" class="btn btn-sm btn-danger">
                                    <i class="fas fa-gavel"></i> Review Alert
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="alert alert-success m-3">
                        <i class="fas fa-check-circle"></i> **Great!** No urgent alerts require triage at this moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <hr/>

    <div class="card mb-4 shadow-lg border-primary">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-hospital-user"></i> **All Assigned Patients**</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Patient ID</th>
                            <th>Last Reading</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Loop through all patients, including those not currently urgent
                        foreach ($all_patients as $patient): 
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></td>
                            <td><?= htmlspecialchars($patient['patient_id']) ?></td>
                            <td><?= $patient['last_reading_time'] ? date('M d, Y H:i', strtotime($patient['last_reading_time'])) : 'N/A' ?></td>
                            <td>
                                <a href="patient_detail.php?pid=<?= htmlspecialchars($patient['patient_id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<?php 
include '../../includes/footer.php'; 
?>