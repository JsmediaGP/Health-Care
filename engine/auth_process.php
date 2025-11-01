<?php
/**
 * AUTH_PROCESS.PHP
 * Handles all server-side authentication processes (Registration, Login, and Logout).
 */

session_start();

// 1. Include core files (relative path is important since we are in the engine/ folder)
require_once 'db_config.php';
require_once 'functions.php';

$action = '';

// Determine the action based on request method (POST for forms, GET for logout link)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = sanitizeInput($_POST['action'] ?? '');
} elseif ($_SERVER["REQUEST_METHOD"] === "GET") {
    $action = sanitizeInput($_GET['action'] ?? '');
}

$errors = [];

// =========================================================================
//                  A. HANDLER FOR PATIENT REGISTRATION (POST)
// =========================================================================
if ($action === 'register') {
    
    // Safety check for registration method
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $errors[] = "Invalid method for registration.";
    } else {
        // 2. Sanitize and Validate Input
        $firstName = sanitizeInput($_POST['first_name'] ?? '');
        $lastName = sanitizeInput($_POST['last_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? ''; 
        $address = sanitizeInput($_POST['address'] ?? '');

        // Basic Validation Checks
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($address)) {
            $errors[] = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Check if email is already registered in the users table
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = "This email is already registered.";
                }

                if (empty($errors)) {
                    // Generate Unique Patient ID and Hash Password
                    $patientID = generatePatientID($pdo);
                    $hashedPassword = hashPassword($password);
                    $role = 'patient';

                    // 1. Insert into users table (Authentication Data)
                    $stmt = $pdo->prepare("INSERT INTO users (user_id, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$patientID, $email, $hashedPassword, $role]);

                    // 2. Insert into patients table (Profile Data)
                    // *** FIX APPLIED HERE: Using user_fk and assigned_doctor_fk ***
                    $stmt = $pdo->prepare("INSERT INTO patients (user_fk, assigned_doctor_fk, first_name, last_name, address) VALUES (?, NULL, ?, ?, ?)");
                    $stmt->execute([$patientID, $firstName, $lastName, $address]);

                    $pdo->commit();

                    // Success Message
                    $_SESSION['success_message'] = "Registration successful! Your unique Patient ID is: <strong>{$patientID}</strong>. Please use this ID and your password to log in.";
                    unset($_SESSION['form_data']); // Clear form data on success
                }

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // For debugging: $errors[] = "Database error: " . $e->getMessage();
                $errors[] = "Database error during registration. Please try again.";
            }
        }
    }


// =========================================================================
//                  B. HANDLER FOR LOGIN (POST)
// =========================================================================
} elseif ($action === 'login') {
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
         $errors[] = "Invalid method for login.";
    } else {
        $user_id = sanitizeInput($_POST['user_id'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($user_id) || empty($password)) {
            $errors[] = "ID and password are required for login.";
        } 
        
        if (empty($errors)) {
            try {
                // Fetch user data (ID, hashed password, and role)
                $stmt = $pdo->prepare("SELECT user_id, password, role FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if ($user && verifyPassword($password, $user['password'])) {
                    
                    // Login Successful: Create Session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // Redirect to respective dashboard 
                    // *** FIX APPLIED HERE: Correct paths to patient/doctor files ***
                    if ($user['role'] === 'patient') {
                        header("Location: ../pages/patient/dashboard_patient.php");
                        exit;
                    } elseif ($user['role'] === 'doctor') {
                        header("Location: ../pages/doctor/dashboard_doctor.php");
                        exit;
                    }
                    
                } else {
                    $errors[] = "Invalid ID or password. Please try again.";
                }

            } catch (PDOException $e) {
                $errors[] = "A database error occurred during login. Please try again.";
            }
        }
    }


// =========================================================================
//                  C. HANDLER FOR LOGOUT (GET)
// =========================================================================
} elseif ($action === 'logout') {
    
    // Clear and destroy the session
    $_SESSION = array();
    session_destroy();
    
    $_SESSION['success_message'] = "You have been successfully logged out.";
    header("Location: ../index.php"); // Redirect to root index
    exit;

} else {
    // If an unknown action is submitted
    $errors[] = "Invalid action request.";
}

// 4. Final Redirect Handler (redirects back to index.php with status messages)
if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    $_SESSION['form_data'] = $_POST; // Keep form data for pre-filling on error
}

// Redirect back to the index page if no successful redirect has occurred
if (!headers_sent()) {
    header("Location: ../index.php");
    exit;
}
?>