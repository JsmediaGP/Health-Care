/**
 * patient_details_doctor.js
 * Handles Live Vitals refresh, History Toggle (Readings/Alerts), 
 * and the Mark Read functionality for alerts.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Ensure the PHP variables (PATIENT_ID and PATIENT_PK) are available globally
    if (typeof PATIENT_ID === 'undefined') {
        console.error("PATIENT_ID is not defined. PHP variable not set.");
        return;
    }

    // DOM Elements
    const historyContainer = document.getElementById('history-data-view');
    const btnReadings = document.getElementById('btn-readings-history');
    const btnAlerts = document.getElementById('btn-alerts-history');
    
    // ===========================================
    // 1. LIVE VITALS AJAX FETCH & RENDER
    // ===========================================
    function fetchLiveVitals() {
        // NOTE: This assumes you have an API endpoint at api/fetch_latest_vitals.php
        fetch(`../../api/fetch_patient_history.php?pid=${PATIENT_ID}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.reading) {
                    const r = data.reading;

                    // Update HR
                    const hrElement = document.getElementById('live-hr');
                    hrElement.textContent = r.heart_rate || 'N/A';
                    hrElement.className = 'h1 mb-0 ' + (r.heart_rate > 120 ? 'text-danger' : 'text-success');

                    // Update SpO2
                    const spo2Element = document.getElementById('live-spo2');
                    spo2Element.textContent = r.spo2 || 'N/A';
                    spo2Element.className = 'h1 mb-0 ' + (r.spo2 < 95 ? 'text-danger' : 'text-success');

                    // Update Temp
                    const tempElement = document.getElementById('live-temp');
                    tempElement.textContent = parseFloat(r.temperature || 0).toFixed(2);
                    tempElement.className = 'h1 mb-0 ' + (r.temperature >= 37.8 ? 'text-danger' : 'text-success');
                    
                    // Update Timestamp
                    document.getElementById('live-update-time').textContent = 'Last Update: ' + r.timestamp;

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
    // 2. HISTORY DATA RENDERING
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

        // NOTE: Assumes you have an API endpoint at api/fetch_patient_history.php
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

                // NOTE: Assumes you have an API endpoint at api/mark_alert_read.php
                fetch('../../api/mark_alert_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'alert_id=' + alertId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update the status cell text and class
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