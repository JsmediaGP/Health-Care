<?php
/**
 * HEADER.PHP
 * Handles session start, authentication checks, global configuration,
 * and outputs the initial HTML structure.
 * * Dependencies: 
 * - Must be included AFTER the calling file sets $page_title and $required_role.
 * - Requires '../../engine/db_config.php' and '../../engine/functions.php'.
 */

// Start session if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global variables initialized
$user_id = null;
$user_name = 'Guest';

// Check if the current file requires authorization
// $skip_auth should be set to true in pages like index.php (login/register page)
if (!isset($skip_auth) || $skip_auth !== true) {

    // --- 1. Authentication Check ---
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        // If NOT logged in, redirect to the root index.php (login page)
        header('Location: ../../index.php'); // Two levels up from pages/role/
        exit;
    }
    
    // Include the DB config and functions (Paths are two levels up from includes/)
    require_once '../../engine/db_config.php';
    require_once '../../engine/functions.php'; 

    $user_id = $_SESSION['user_id'];
    $session_role = $_SESSION['role'];


    // --- 2. Authorization Check (Role-Specific Access) ---
    // This is the CRITICAL BLOCK to fix the infinite redirect loop.
    if ($session_role !== $required_role) {
        
        // Redirect if the logged-in role DOES NOT match the required role for this page
        if ($session_role === 'patient') {
             // Patient trying to access a Doctor page -> send to patient dashboard
             header('Location: ../patient/dashboard_patient.php'); 
             
        } else if ($session_role === 'doctor') {
             // Doctor trying to access a Patient page -> send to doctor dashboard
             header('Location: ../doctor/dashboard_doctor.php'); 
             
        } else {
            // Failsafe redirect to index
            header('Location: ../../index.php');
        }
        exit;
    }
    // IF roles match, the code falls through and continues to load the page.


    // --- 3. Fetch User Name ---
    try {
        $table = ($session_role === 'patient') ? 'patients' : 'doctors';
        $stmt = $pdo->prepare("SELECT first_name FROM {$table} WHERE user_fk = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        $user_name = $user_data['first_name'] ?? $session_role;
    } catch (PDOException $e) {
        // Log error but proceed
        $user_name = $session_role;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> | KINGFIX Maternal Monitor</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<header class="main-header">
    <div class="header-content">
        <a href="<?= $session_role === 'doctor' ? '../doctor/dashboard_doctor.php' : '../patient/dashboard_patient.php' ?>" class="logo-link">
            <i class="fas fa-heartbeat"></i> <span>KINGFIX Monitor</span>
        </a>
        <nav>
            <?php if ($session_role === 'doctor'): ?>
                <a href="../doctor/dashboard_doctor.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'dashboard_doctor.php') !== false ? 'active' : '' ?>"><i class="fas fa-notes-medical"></i> Triage</a>
                <a href="../doctor/profile.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'profile.php') !== false ? 'active' : '' ?>"><i class="fas fa-user-md"></i> Profile</a>
            <?php else: ?>
                <a href="../patient/dashboard_patient.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'dashboard_patient.php') !== false ? 'active' : '' ?>"><i class="fas fa-chart-line"></i> Live Data</a>
                <a href="../patient/profile.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'profile.php') !== false ? 'active' : '' ?>"><i class="fas fa-user-circle"></i> Profile</a>
            <?php endif; ?>
            <a href="../../engine/auth_process.php?action=logout" class="nav-item logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            
        </nav>
    </div>
</header>
<main class="dashboard-main">
    <div class="welcome-banner">
        <h2>Welcome back, <?= $user_name ?>!</h2>
        <p>Your maternal health dashboard.</p>
    </div>