/**
 * alert_page_doctor.js
 * Handles Live Vitals refresh and the "Resolve All Alerts" functionality
 * for the Doctor's Alert Resolution Page.
 */

document.addEventListener('DOMContentLoaded', function() {
    // PATIENT_ID is set in alert_page.php via a <script> tag
    if (typeof PATIENT_ID === 'undefined') {
        console.error("PATIENT_ID is not defined. PHP variable not set correctly.");
        return;
    }

    const resolveBtn = document.getElementById('resolve-alerts-btn');
    const liveUpdateSpan = document.getElementById('live-update-time-alert');
    
    // ===========================================
    // 1. LIVE VITALS AJAX FETCH & RENDER
    // ===========================================
    // NOTE: This function is similar to the one in patient_details_doctor.js, 
    // but updates elements specific to the alert_page.php file (suffixed with '-alert')

    function fetchLiveVitals() {
        // Calls the API to get the latest reading
        fetch(`../../api/fetch_latest_vitals.php?pid=${PATIENT_ID}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.reading) {
                    const r = data.reading;

                    // Calculate Acceleration Magnitude
                    const accMag = (r.acc_ax && r.acc_ay && r.acc_az) ? 
                        Math.sqrt(r.acc_ax * r.acc_ax + r.acc_ay * r.acc_ay + r.acc_az * r.acc_az) : 'N/A';
                    
                    // Determine Alert Statuses
                    const isHrAlert = r.heart_rate > 120 || r.heart_rate < 50;
                    const isSpo2Alert = r.spo2 < 95;
                    const isTempAlert = r.temperature >= 37.8;
                    const isOverallAlert = isHrAlert || isSpo2Alert || isTempAlert;
                    
                    // Helper to toggle color classes (assuming CSS is in place)
                    const toggleColor = (id, isAlert) => {
                        const element = document.getElementById(id);
                        if (element) {
                            element.classList.remove(isAlert ? 'normal-text' : 'alert-text');
                            element.classList.add(isAlert ? 'alert-text' : 'normal-text');
                            
                            const statusElement = element.nextElementSibling;
                            if (statusElement && statusElement.classList.contains('status-indicator')) {
                                statusElement.classList.remove(isAlert ? 'normal' : 'alert');
                                statusElement.classList.add(isAlert ? 'alert' : 'normal');
                                statusElement.innerHTML = `<i class="fas fa-circle"></i> Status: ${isAlert ? (id === 'live-temp-alert' ? 'Fever' : (id === 'live-spo2-alert' ? 'Low' : 'ALERT')) : 'Normal'}`;
                            }
                        }
                    };

                    // Update Metrics & Statuses
                    document.getElementById('live-hr-alert').textContent = r.heart_rate || 'N/A';
                    toggleColor('live-hr-alert', isHrAlert);

                    document.getElementById('live-spo2-alert').textContent = r.spo2 || 'N/A';
                    toggleColor('live-spo2-alert', isSpo2Alert);

                    document.getElementById('live-temp-alert').textContent = parseFloat(r.temperature || 0).toFixed(2);
                    toggleColor('live-temp-alert', isTempAlert);

                    // Update Accel (Magnitude is usually not color-coded unless extreme)
                    // Note: We skip updating individual axis values here for brevity, 
                    // as they are primarily for historical review, but you can add them.
                    
                    // Overall Status
                    const overallStatusP = document.querySelector('#live-vitals-grid-alert .latest-reading-summary .status-indicator');
                    if (overallStatusP) {
                        overallStatusP.classList.remove(isOverallAlert ? 'normal' : 'alert');
                        overallStatusP.classList.add(isOverallAlert ? 'alert' : 'normal');
                        overallStatusP.innerHTML = `<i class="fas fa-circle"></i> Overall Status: ${isOverallAlert ? 'Review Required' : 'Stable'}`;
                    }
                    
                    // Timestamp
                    liveUpdateSpan.textContent = new Date().toLocaleTimeString();

                } else if (data.status === 'success' && !data.reading) {
                     liveUpdateSpan.textContent = 'No data available.';
                }
            })
            .catch(error => {
                console.error('Error fetching live vitals:', error);
                liveUpdateSpan.textContent = 'Live Feed Error';
            });
    }
    
    // Start live updates
    fetchLiveVitals();
    setInterval(fetchLiveVitals, 5000); // Refresh every 5 seconds

    // ===========================================
    // 2. RESOLUTION ACTION
    // ===========================================

    if (resolveBtn) {
        resolveBtn.addEventListener('click', function() {
            // Disable button during processing
            resolveBtn.disabled = true;
            resolveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resolving...';

            // Call the dedicated API to resolve all pending alerts for this patient
            fetch('../../api/resolve_all_alerts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'patient_id=' + PATIENT_ID
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(`Resolution successful! Marked ${data.updated_count} alert(s) as READ.`);
                    
                    // Redirect back to the dashboard after successful resolution
                    window.location.href = 'dashboard_doctor.php?msg=alerts_resolved';
                } else {
                    alert('Resolution failed: ' + data.message);
                    resolveBtn.disabled = false;
                    resolveBtn.innerHTML = '<i class="fas fa-check-double"></i> Resolve All Alerts';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('A network error occurred during resolution.');
                resolveBtn.disabled = false;
                resolveBtn.innerHTML = '<i class="fas fa-check-double"></i> Resolve All Alerts';
            });
        });
    }
});