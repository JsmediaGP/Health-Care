<?php
$page_title = "Dynamic History & Alerts";
$required_role = "patient";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../../index.php'); 
    exit;
}

include '../../includes/header.php'; // Loads DB connection ($pdo)

// No PHP data fetching is needed here, as JS handles it via AJAX.
?>

<main class="container mt-4">
    <h2 class="mb-4">Patient Data History</h2>
    
    <div class="btn-group mb-3" role="group" aria-label="Data Toggle">
        <button type="button" class="btn btn-primary active" id="btn-readings" data-type="readings">
            <i class="fas fa-chart-line"></i> Normal Readings
        </button>
        <button type="button" class="btn btn-secondary" id="btn-alerts" data-type="alerts">
            <i class="fas fa-exclamation-triangle"></i> Alert History
        </button>
    </div>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm" id="historyTable">
            </table>
        <p id="loading-message" class="text-center text-muted mt-3">Loading history...</p>
    </div>

</main>

<?php include '../../includes/footer.php'; ?>