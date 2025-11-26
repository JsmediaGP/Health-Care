/**
 * patient_details_doctor.js
 * Handles Live Vitals refresh, History Toggle (Readings/Alerts), 
 * and the Mark Read functionality for alerts on the Doctor's Patient Details page.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Global variables PATIENT_ID and PATIENT_PK are set in patient_details.php via <script> tags
    if (typeof PATIENT_ID === 'undefined') {
        console.error("PATIENT_ID is not defined. PHP variable not set correctly.");
        return;
    }

    // DOM Elements
    const historyContainer = document.getElementById('history-data-view');
    const btnReadings = document.getElementById('btn-readings-history');
    const btnAlerts = document.getElementById('btn-alerts-history');
    
    // ===========================================
    // 1. LIVE VITALS AJAX FETCH & RENDER (Section 1: The Grid)
    // ===========================================
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
                    
                    // Function to apply styles
                    const applyStyle = (element, isAlert) => {
                        element.classList.remove(isAlert ? 'normal' : 'alert');
                        element.classList.add(isAlert ? 'alert' : 'normal');
                        element.previousElementSibling.classList.remove(isAlert ? 'normal-text' : 'alert-text');
                        element.previousElementSibling.classList.add(isAlert ? 'alert-text' : 'normal-text');
                    };

                    // --- Update Metrics & Statuses ---

                    // HR
                    document.getElementById('live-hr').textContent = r.heart_rate || 'N/A';
                    document.getElementById('hr-status').innerHTML = `<i class="fas fa-circle"></i> Status: ${isHrAlert ? 'ALERT' : 'Normal'}`;
                    applyStyle(document.getElementById('hr-status'), isHrAlert);

                    // SpO2
                    document.getElementById('live-spo2').textContent = r.spo2 || 'N/A';
                    document.getElementById('spo2-status').innerHTML = `<i class="fas fa-circle"></i> Status: ${isSpo2Alert ? 'Low' : 'Healthy'}`;
                    applyStyle(document.getElementById('spo2-status'), isSpo2Alert);

                    // Temp
                    document.getElementById('live-temp').textContent = parseFloat(r.temperature || 0).toFixed(2);
                    document.getElementById('temp-status').innerHTML = `<i class="fas fa-circle"></i> Status: ${isTempAlert ? 'Fever' : 'Normal'}`;
                    applyStyle(document.getElementById('temp-status'), isTempAlert);

                    // Accel
                    document.getElementById('live-acc-mag').textContent = parseFloat(accMag).toFixed(2);
                    document.getElementById('live-acc-ax').textContent = parseFloat(r.acc_ax).toFixed(2);
                    document.getElementById('live-acc-ay').textContent = parseFloat(r.acc_ay).toFixed(2);
                    document.getElementById('live-acc-az').textContent = parseFloat(r.acc_az).toFixed(2);
                    
                    // Overall Status
                    document.getElementById('overall-status').innerHTML = `<i class="fas fa-circle"></i> Overall Status: ${isOverallAlert ? 'Review Required' : 'Stable'}`;
                    document.getElementById('overall-status').classList.remove(isOverallAlert ? 'normal' : 'alert');
                    document.getElementById('overall-status').classList.add(isOverallAlert ? 'alert' : 'normal');
                    
                    // Timestamp
                    document.getElementById('live-update-time').textContent = new Date(r.timestamp).toLocaleTimeString();

                } else if (data.status === 'success' && !data.reading) {
                     document.getElementById('live-update-time').textContent = 'No data available.';
                }
            })
            .catch(error => {
                console.error('Error fetching live vitals:', error);
                document.getElementById('live-update-time').textContent = 'Live Feed Error';
            });
    }
    
    // Start live updates
    fetchLiveVitals();
    setInterval(fetchLiveVitals, 5000); // Refresh every 5 seconds

    // ===========================================
    // 2. HISTORY DATA RENDERING & AJAX FETCH (Section 2: The Toggle)
    // ===========================================

    // Renders the HTML table based on the fetched JSON data
    function renderHistoryTable(data, type) {
        if (!data || data.length === 0) {
            historyContainer.innerHTML = `<p class="alert alert-info mt-3">No ${type} data recorded for this patient.</p>`;
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-striped table-hover table-sm"><thead><tr>';
        
        if (type === 'readings') {
            // Table Header for Readings
            html += `
                <th>Timestamp</th>
                <th>HR (BPM)</th>
                <th>SpO2 (%)</th>
                <th>Temp (C)</th>
                <th>Accel X/Y/Z</th>
            </tr></thead><tbody>`;
            data.forEach(r => {
                // Table Body for Readings
                html += `
                    <tr>
                        <td>${new Date(r.timestamp).toLocaleString()}</td>
                        <td>${r.heart_rate}</td>
                        <td>${r.spo2}</td>
                        <td>${parseFloat(r.temperature).toFixed(2)}</td>
                        <td>${parseFloat(r.acc_ax).toFixed(2)}/${parseFloat(r.acc_ay).toFixed(2)}/${parseFloat(r.acc_az).toFixed(2)}</td>
                    </tr>
                `;
            });

        } else if (type === 'alerts') {
            // Table Header for Alerts
            html += `
                <th>Time Recorded</th>
                <th>Alert Type</th>
                <th>Trigger Value</th>
                <th>Message</th>
                <th>Status</th>
                <th>Action</th>
            </tr></thead><tbody>`;
            data.forEach(a => {
                const isUnread = a.status === 'UNREAD';
                const statusBadge = `<span class="badge badge-${isUnread ? 'warning' : 'success'}">${a.status}</span>`;
                const actionButton = isUnread ? `<button class="btn btn-sm btn-outline-primary mark-read-btn" data-alert-id="${a.alert_id}">Mark Read</button>` : '';

                // Table Body for Alerts
                html += `
                    <tr class="${isUnread ? 'table-warning' : ''}">
                        <td>${new Date(a.recorded_at).toLocaleString()}</td>
                        <td><span class="badge badge-danger">${a.alert_type}</span></td>
                        <td>${parseFloat(a.value).toFixed(2)}</td>
                        <td>${a.alert_message}</td>
                        <td id="status-${a.alert_id}">${statusBadge}</td>
                        <td>${actionButton}</td>
                    </tr>
                `;
            });
        }
        
        html += '</tbody></table></div>';
        historyContainer.innerHTML = html;
        
        // IMPORTANT: Re-attach Mark Read listeners after rendering the alerts table
        if (type === 'alerts') {
            attachMarkReadListeners();
        }
    }

    // Main function to fetch history dynamically
    function fetchHistory(type) {
        historyContainer.innerHTML = '<p class="text-center text-muted mt-5"><i class="fas fa-spinner fa-spin"></i> Loading ' + type + ' history...</p>';

        // Calls the API to get the readings or alerts history
        fetch(`../../api/fetch_patient_history.php?pid=${PATIENT_ID}&type=${type}`)
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    renderHistoryTable(result.data.history, result.data.type);
                } else {
                    historyContainer.innerHTML = `<p class="alert alert-danger mt-3">Error fetching history: ${result.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                historyContainer.innerHTML = '<p class="alert alert-danger mt-3">A network error occurred while loading history.</p>';
            });
    }

    // ===========================================
    // 3. HISTORY TOGGLE LOGIC
    // ===========================================
    function switchHistory(type) {
        // Visual toggle
        if (type === 'readings') {
            btnReadings.classList.add('active', 'btn-primary');
            btnReadings.classList.remove('btn-secondary');
            btnAlerts.classList.remove('active', 'btn-danger');
            btnAlerts.classList.add('btn-secondary');
        } else {
            btnAlerts.classList.add('active', 'btn-danger');
            btnAlerts.classList.remove('btn-secondary');
            btnReadings.classList.remove('active', 'btn-primary');
            btnReadings.classList.add('btn-secondary');
        }
        // Fetch data for the selected type
        fetchHistory(type);
    }

    btnReadings.addEventListener('click', () => switchHistory('readings'));
    btnAlerts.addEventListener('click', () => switchHistory('alerts'));
    
    // Initial load: show readings when the page first loads
    switchHistory('readings'); 

    // ===========================================
    // 4. MARK READ AJAX FUNCTIONALITY
    // ===========================================
    // Attaches event listeners to dynamically created "Mark Read" buttons
    function attachMarkReadListeners() {
        document.querySelectorAll('.mark-read-btn').forEach(button => {
            button.addEventListener('click', function() {
                const alertId = this.getAttribute('data-alert-id');
                const button = this; 

                // Calls the API to update the alert status
                fetch('../../api/mark_alert_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'alert_id=' + alertId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Visually update the row in the table
                        const statusCell = document.getElementById('status-' + alertId);
                        if (statusCell) {
                            statusCell.innerHTML = '<span class="badge badge-success">READ</span>';
                            button.closest('tr').classList.remove('table-warning');
                        }
                        // Remove the button
                        button.remove();
                    } else {
                        alert('Error marking alert read: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('A network error occurred.');
                });
            });
        });
    }
});