<?php
// ... [Existing session_start() and other checks]

// Check if the current file requires authorization
if (!isset($skip_auth) || $skip_auth !== true) {

    // --- CRUCIAL FIX: Authorization Check ---
    if (!isset($_SESSION['user_id'])) {
        // If NOT logged in, redirect to the root index.php
        header('Location: ../../index.php'); // Two levels up to reach index.php
        exit;
    }
    
    // Include the DB config and functions
    require_once '../../engine/db_config.php';   // Two levels up
    require_once '../../engine/functions.php';  // Two levels up

    // Check for role-specific access (Authorization)
    // We need a variable in the dashboard file to tell the header what role is required.
    if ($_SESSION['role'] !== $required_role) {
        // Redirect if wrong role is accessing the page
        if ($_SESSION['role'] === 'patient') {
             header('Location: ../pages/patient/dashboard_patient.php'); // Redirect patient to patient dashboard
        } else if ($_SESSION['role'] === 'doctor') {
             header('Location: ../pages/doctor/dashboard_doctor.php'); // Redirect doctor to doctor dashboard
        } else {
            // Failsafe redirect to index
            header('Location: ../../index.php');
        }
        exit;
    }


    // Fetch user name for personalized greeting
    // The $pdo variable is now available
    $user_id = $_SESSION['user_id'];
    $table = ($_SESSION['role'] === 'patient') ? 'patients' : 'doctors';
    $stmt = $pdo->prepare("SELECT first_name FROM {$table} WHERE user_fk = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    $user_name = $user_data['first_name'] ?? 'User';
}
// ... [rest of the header HTML/PHP remains, using $user_name]
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> | KINGFIX Maternal Monitor</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<header class="main-header">
    <div class="header-content">
        <a href="dashboard_patient.php" class="logo-link">
            <i class="fas fa-heartbeat"></i> <span>KINGFIX Monitor</span>
        </a>
        <nav>
            <a href="dashboard_patient.php" class="nav-item active"><i class="fas fa-chart-line"></i> Live Data</a>
            <a href="profile.php" class="nav-item"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="../../engine/auth_process.php?action=logout" class="nav-item logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <!-- <a href="engine/auth_process.php?action=logout" class="nav-item logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a> -->
        </nav>
    </div>
</header>
<main class="dashboard-main">
    <div class="welcome-banner">
        <h2>Welcome back, <?= $user_name ?>!</h2>
        <p>Your maternal health data at a glance.</p>
    </div>