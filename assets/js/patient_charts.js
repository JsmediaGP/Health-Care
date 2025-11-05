/**
 * PATIENT_CHARTS.JS
 * Fetches the logged-in patient's 24-hour vital signs data and renders a wave-form time-series chart.
 * NOTE: Requires Moment.js to be included before Chart.js.
 */

function drawPatientDashboardChart(data) {
    const canvas = document.getElementById('vitalsChart');
    if (!canvas) {
        console.error("Canvas element 'vitalsChart' not found.");
        document.getElementById('chartError').textContent = "Error: Chart canvas not found.";
        return;
    }
    const ctx = canvas.getContext('2d');
    
    if (Chart.getChart("vitalsChart")) {
        Chart.getChart("vitalsChart").destroy();
    }
    
    // Map the fetched parallel arrays into the (x: timestamp, y: value) format
    const hrData = data.heart_rate.map((hr, index) => ({
        x: data.labels[index], 
        y: hr
    }));
    
    const spo2Data = data.spo2.map((spo2, index) => ({
        x: data.labels[index], 
        y: spo2
    }));

    new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Heart Rate (BPM) - ECG Style',
                    data: hrData,
                    borderColor: 'rgb(220, 53, 69)', // Strong Red for ECG look
                    backgroundColor: 'transparent', // Remove fill
                    yAxisID: 'yHeart',
                    tension: 0, // CRITICAL: Makes the line sharp and angular (ECG)
                    fill: false, 
                    pointRadius: 0, 
                    borderWidth: 2
                },
                {
                    label: 'Oxygen Saturation (SpO2 %)',
                    data: spo2Data,
                    borderColor: 'rgb(40, 167, 69)', // Keep Green for SpO2
                    backgroundColor: 'transparent',
                    yAxisID: 'ySpO2',
                    tension: 0.5, // Smoother line for SpO2
                    fill: false, 
                    pointRadius: 0,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true, 
            interaction: {
                mode: 'index',
                intersect: false,
            },
            stacked: false, 
            scales: {
                x: {
                    type: 'time', 
                    time: {
                        unit: 'hour',
                        displayFormats: {
                            hour: 'h:mm A'
                        },
                        tooltipFormat: 'MMM D, h:mm A'
                    },
                    title: { display: true, text: 'Time (Last 24 Hours)' },
                    grid: { display: false }, 
                },
                yHeart: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: { display: true, text: 'Heart Rate (BPM)' },
                    min: 50,
                    max: 120,
                    grid: { color: 'rgba(0, 0, 0, 0.1)' } 
                },
                ySpO2: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { drawOnChartArea: false }, 
                    min: 90,
                    max: 100,
                    title: { display: true, text: 'SpO2 (%)' }
                }
            },
            plugins: {
                legend: { position: 'top' },
                title: { display: false }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const chartErrorElement = document.getElementById('chartError'); 
    
    if (!chartErrorElement) {
        console.error("HTML error element 'chartError' missing.");
        return;
    }

    const apiUrl = '../../api/patient_fetch_data.php'; 

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { 
                    throw new Error(err.error || response.statusText); 
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success' && data.labels.length > 0) {
                drawPatientDashboardChart(data);
            } else {
                chartErrorElement.textContent = 'No readings available in the last 24 hours.';
            }
        })
        .catch(error => {
            console.error('Fatal Fetch/Authorization Error:', error);
            chartErrorElement.textContent = 'Error loading vital data. Check browser console for details.';
        });
});