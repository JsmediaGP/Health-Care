<?php
/**
 * FUNCTIONS.PHP
 * Core utility functions for the application
 */

// Requires the database connection object ($pdo) to function
// Must be included AFTER db_config.php

/**
 * Generates a unique Patient ID (PID) in the format PID + 7 digits.
 * Checks the database to ensure the ID does not already exist.
 *
 * @param PDO $pdo The PDO database connection object.
 * @return string The unique Patient ID.
 */
function generatePatientID(PDO $pdo): string {
    $prefix = "PID";
    
    // Loop until a unique ID is generated
    do {
        // Generate a random 7-digit number (e.g., 0012345)
        $number = str_pad(mt_rand(1, 9999999), 7, '0', STR_PAD_LEFT);
        $patient_id = $prefix . $number;

        // Check if this ID already exists in the users table
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $stmt->execute([$patient_id]);
        $exists = $stmt->fetch();

    } while ($exists); // Repeat if the ID exists

    return $patient_id;
}

/**
 * Safely hashes a password for storage in the database.
 * * @param string $password The plain-text password.
 * @return string The secure hash.
 */
function hashPassword(string $password): string {
    // Use PHP's built-in, secure hashing function (recommended standard)
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifies a plain-text password against a stored hash during login.
 * * @param string $password The plain-text password entered by the user.
 * @param string $hashedPassword The hash retrieved from the database.
 * @return bool True if the password matches the hash, false otherwise.
 */
function verifyPassword(string $password, string $hashedPassword): bool {
    // Use PHP's built-in verification function
    return password_verify($password, $hashedPassword);
}

// --- General Functions ---

/**
 * Sanitizes and validates data received from forms (basic security).
 * @param string $data The input string.
 * @return string The cleaned string.
 */
function sanitizeInput(string $data): string {
    // Trim whitespace, strip slashes, and convert special characters to HTML entities
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>