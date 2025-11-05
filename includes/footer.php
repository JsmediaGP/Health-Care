</main>
<footer class="main-footer" style=" text-align: center">
    &copy; <?= date('Y') ?> KINGFIX Maternal Health Monitor. All rights reserved.
</footer>

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'doctor'): ?>
<script src="../../assets/js/doctor_charts.js"></script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>

<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'patient'): ?>
<script src="../../assets/js/patient_charts.js"></script>
<script src="../../assets/js/patient_live_vitals.js"></script>
<script src="../../assets/js/patient_history.js"></script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>

<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


<?php endif; ?>


</body>
</html>