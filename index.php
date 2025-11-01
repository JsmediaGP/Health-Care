<?php
// Start the session to manage login status and messages
session_start();

// Basic guardrail: Redirect if user is already logged in
// *** CORRECTION APPLIED HERE ***
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'patient') {
        // Correct path to the new patient dashboard location
        header('Location: pages/patient/dashboard_patient.php');
    } elseif ($_SESSION['role'] === 'doctor') {
        // Correct path to the new doctor dashboard location
        header('Location: pages/doctor/dashboard_doctor.php');
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KINGFIX | Maternal Health Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php 
// Display Status/Error Messages for redirects from auth_process.php
if (isset($_SESSION['success_message'])): ?>
    <div class="status-message success">
        <i class="fas fa-check-circle"></i> 
        <?= $_SESSION['success_message']; ?>
    </div>
    <?php unset($_SESSION['success_message']); 
elseif (isset($_SESSION['error'])): ?>
    <div class="status-message error">
        <i class="fas fa-exclamation-triangle"></i> 
        <?= $_SESSION['error']; ?>
    </div>
    <?php unset($_SESSION['error']); 
endif;
?>
<header>
    <div class="logo-container">
        <i class="fas fa-heartbeat"></i>
        <h1>KINGFIX Maternal Monitor</h1>
    </div>
</header>

<main class="auth-container">
    <div class="form-card">
        <div class="tab-selector">
            <button class="tab active" data-form="login">Login</button>
            <button class="tab" data-form="register">Patient Registration</button>
        </div>
        
        <div id="login-form" class="form-content active">
            <h2>Access Portal</h2>
            <form action="engine/auth_process.php" method="POST">
                
                <div class="input-group">
                    <label for="login_id"><i class="fas fa-user-circle"></i> ID (Patient/Doctor)</label>
                    <input type="text" id="login_id" name="user_id" placeholder="Enter PID or Doctor ID" required>
                </div>
                
                <div class="input-group">
                    <label for="login_password"><i class="fas fa-lock"></i> Password</label>
                    <div class="password-toggle-container">
                        <input type="password" id="login_password" name="password" required>
                        <i class="fas fa-eye toggle-password" data-target="login_password"></i>
                    </div>
                </div>
                
                <input type="hidden" name="action" value="login">
                <button type="submit" class="btn-primary">Secure Login</button>
            </form>
        </div>

        <div id="register-form" class="form-content hidden">
            <h2>New Patient Sign Up</h2>
            <form action="engine/auth_process.php" method="POST">
                <p class="id-note"><i class="fas fa-id-badge"></i> Your unique **Patient ID (PID)** will be generated automatically upon successful submission.</p>

                <div class="input-group-row">
                    <div class="input-group">
                        <label for="reg_fname">First Name</label>
                        <input type="text" id="reg_fname" name="first_name" value="<?= $_SESSION['form_data']['first_name'] ?? '' ?>" required>
                    </div>
                    <div class="input-group">
                        <label for="reg_lname">Last Name</label>
                        <input type="text" id="reg_lname" name="last_name" value="<?= $_SESSION['form_data']['last_name'] ?? '' ?>" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="reg_email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="reg_email" name="email" value="<?= $_SESSION['form_data']['email'] ?? '' ?>" required>
                </div>

                <div class="input-group">
                    <label for="reg_password"><i class="fas fa-key"></i> Password</label>
                    <div class="password-toggle-container">
                        <input type="password" id="reg_password" name="password" required>
                        <i class="fas fa-eye toggle-password" data-target="reg_password"></i>
                    </div>
                </div>

                <div class="input-group">
                    <label for="reg_address"><i class="fas fa-map-marker-alt"></i> Address</label>
                    <textarea id="reg_address" name="address" rows="3" required><?= $_SESSION['form_data']['address'] ?? '' ?></textarea>
                </div>
                
                <input type="hidden" name="action" value="register">
                <button type="submit" class="btn-primary">Register Patient (Get PID)</button>
            </form>
        </div>
    </div>
</main>

<script src="assets/js/index.js"></script>
</body>
</html>

<?php 
// Clean up stored form data after display
if(isset($_SESSION['form_data'])) {
    unset($_SESSION['form_data']);
}
?>