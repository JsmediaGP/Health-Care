</main>
<footer class="main-footer" style=" text-align: center">
    &copy; <?= date('Y') ?> KINGFIX Maternal Health Monitor. All rights reserved.
</footer>

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'doctor'): ?>
<script src="../../assets/js/doctor_charts.js"></script>
<?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'patient'): ?>
<script src="../../assets/js/patient_charts.js"></script>
<?php endif; ?>

</body>
</html>