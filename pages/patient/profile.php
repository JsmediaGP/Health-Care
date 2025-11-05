<?php
// Define required variables before including the header
$page_title = "My Profile";
$required_role = "patient"; 

// The header includes the necessary session check, DB connection ($pdo), 
// and fetches $user_id and $user_name (first name)
include '../../includes/header.php'; 

// --- FETCH FULL PATIENT DETAILS ---
try {
 // Query to get user (email) and patient profile details
 $stmt = $pdo->prepare("
 SELECT 
  t1.email, 
  t2.first_name, 
  t2.last_name, 
  t2.address,
  t2.assigned_doctor_fk
 FROM users t1
 JOIN patients t2 ON t1.user_id = t2.user_fk
 WHERE t1.user_id = ?
 ");
 $stmt->execute([$user_id]);
 $patient_profile = $stmt->fetch(PDO::FETCH_ASSOC);

 // Fetch Doctor's name and email if assigned
 $doctor_name = "Unassigned";
    $doctor_email = "N/A"; 
 $doctor_pk = null; // Renamed to doctor_pk for clarity, but not displayed
 
 if ($patient_profile && $patient_profile['assigned_doctor_fk']) {
 $doctor_pk = $patient_profile['assigned_doctor_fk'];
        
        // Modified query to get Doctor's name and email via the users table
 $stmt_doctor = $pdo->prepare("
  SELECT 
                d.first_name, 
                d.last_name, 
                u.email 
  FROM doctors d
            JOIN users u ON d.user_fk = u.user_id
  WHERE d.doctor_pk = ?
 ");
 $stmt_doctor->execute([$doctor_pk]);
 $doctor_data = $stmt_doctor->fetch(PDO::FETCH_ASSOC);
 
 if ($doctor_data) {
  $doctor_name = $doctor_data['first_name'] . ' ' . $doctor_data['last_name'];
            $doctor_email = $doctor_data['email'];
 }
 }

} catch (PDOException $e) {
 // Handle database errors gracefully
 $patient_profile = null;
 $errors[] = "Error loading profile data.";
}

if (!$patient_profile) {
 // Handle case where patient data could not be found
 echo '<div class="profile-error-message">Profile data could not be retrieved. Please contact support.</div>';
 include '../../includes/footer.php';
 exit;
}
?>

<section class="profile-container">
 
 <div class="profile-details card">
 <h2><i class="fas fa-id-card"></i> Personal Information</h2>
 
 <div class="detail-row">
  <span class="detail-label">Patient ID:</span>
  <span class="detail-value highlight"><?= $user_id ?></span>
 </div>
 <div class="detail-row">
  <span class="detail-label">Name:</span>
  <span class="detail-value"><?= $patient_profile['first_name'] . ' ' . $patient_profile['last_name'] ?></span>
 </div>
 <div class="detail-row">
  <span class="detail-label">Email:</span>
  <span class="detail-value"><?= $patient_profile['email'] ?></span>
 </div>
 <div class="detail-row">
  <span class="detail-label">Address:</span>
  <span class="detail-value address-block"><?= nl2br(htmlspecialchars($patient_profile['address'])) ?></span>
 </div>
 
 <button class="btn-secondary" onclick="alert('Update functionality coming soon!')"><i class="fas fa-edit"></i> Edit Profile</button>
 </div>
 
 <div class="profile-assignment card">
 <h2><i class="fas fa-user-md"></i> Care Assignment</h2>
 
 <div class="detail-row">
  <span class="detail-label">Assigned Doctor:</span>
  <span class="detail-value assigned-doctor-name">
 <?= $doctor_name ?>
  </span>
 </div>
        
        <?php if ($doctor_pk !== null): ?>
        <div class="detail-row">
  <span class="detail-label">Doctor Email:</span>
  <span class="detail-value">
 <a href="mailto:<?= htmlspecialchars($doctor_email) ?>"><?= htmlspecialchars($doctor_email) ?></a>
  </span>
 </div>
        <?php endif; ?>
 
 <div class="detail-note <?= ($doctor_pk === null) ? 'alert' : 'info' ?>">
  <?php if ($doctor_pk === null): ?>
 <i class="fas fa-exclamation-triangle"></i> You are not currently assigned to a physician.
  <?php else: ?>
 <i class="fas fa-check-circle"></i> Your assigned physician monitors your vital signs daily.
  <?php endif; ?>
 </div>
 
 </div>

</section>

<?php 
include '../../includes/footer.php'; 
?>