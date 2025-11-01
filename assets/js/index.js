document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab-selector .tab');
    const formContainers = document.querySelectorAll('.form-content');
    const toggleIcons = document.querySelectorAll('.toggle-password');

    // --- Tab Switching Logic ---
    function showForm(targetId) {
        // Deactivate all tabs and hide all forms
        tabs.forEach(t => t.classList.remove('active'));
        formContainers.forEach(container => container.classList.add('hidden'));

        // Activate the correct tab and show the target form
        document.querySelector(`.tab[data-form="${targetId}"]`).classList.add('active');
        document.getElementById(targetId + '-form').classList.remove('hidden');
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            showForm(this.getAttribute('data-form'));
        });
    });

    // --- Password Toggle Logic ---
    toggleIcons.forEach(icon => {
        icon.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);

            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                this.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                targetInput.type = 'password';
                this.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // --- Initial Form State after Error/Success Redirect ---
    // Check for status messages. If an error exists, default to the registration form.
    const statusSuccess = document.querySelector('.status-message.success');
    const statusError = document.querySelector('.status-message.error');

    if (statusError && statusError.innerHTML.includes('email is already registered') || statusError && !statusError.innerHTML.includes('Login functionality')) {
        // If there's a registration error (like validation or email exists), show the register form
        showForm('register');
    } else if (statusSuccess) {
         // If registration was successful, show the login form 
         showForm('login');
    } else {
        // Default: Ensure login is shown on initial load
        showForm('login');
    }
});