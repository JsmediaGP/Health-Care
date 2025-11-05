// /**
//  * DOCTOR_CHARTS.JS
//  * Fetches patient vital signs data via API and renders a 7-day trend chart for doctor viewing.
//  * Requires Chart.js library to be loaded first (e.g., in includes/header.php or includes/footer.php).
//  */

// function drawPatientVitalsChart(data) {
//     const ctx = document.getElementById('patientVitalsChart').getContext('2d');
    
//     new Chart(ctx, {
//         type: 'line',
//         data: {
//             labels: data.labels,
//             datasets: [
//                 {
//                     label: 'Heart Rate (BPM)',
//                     data: data.heart_rate,
//                     borderColor: 'rgb(255, 99, 132)', // Red/Pink
//                     backgroundColor: 'rgba(255, 99, 132, 0.1)',
//                     yAxisID: 'yHeart',
//                     tension: 0.2,
//                     fill: false,
//                     pointRadius: 2
//                 },
//                 {
//                     label: 'Oxygen Saturation (SpO2 %)',
//                     data: data.spo2,
//                     borderColor: 'rgb(54, 162, 235)', // Blue
//                     backgroundColor: 'rgba(54, 162, 235, 0.1)',
//                     yAxisID: 'ySpO2',
//                     tension: 0.2,
//                     fill: false,
//                     pointRadius: 2
//                 }
//             ]
//         },
//         options: {
//             responsive: true,
//             maintainAspectRatio: false,
//             scales: {
//                 x: {
//                     type: 'category',
//                     title: { display: true, text: 'Timestamp' },
//                     ticks: {
//                         autoSkip: true,
//                         maxRotation: 0,
//                         minRotation: 0,
//                         maxTicksLimit: 12 // Keeps the X-axis labels clean
//                     }
//                 },
//                 yHeart: {
//                     type: 'linear',
//                     display: true,
//                     position: 'left',
//                     title: { display: true, text: 'Heart Rate (BPM)' },
//                     min: 50,
//                     max: 120, // Helps visualize normal/alert ranges clearly
//                 },
//                 ySpO2: {
//                     type: 'linear',
//                     display: true,
//                     position: 'right',
//                     grid: { drawOnChartArea: false }, // Prevent overlap with yHeart gridlines
//                     min: 90,
//                     max: 100, // Helps visualize normal/alert ranges clearly
//                     title: { display: true, text: 'SpO2 (%)' }
//                 }
//             },
//             plugins: {
//                 legend: { position: 'top' },
//                 title: { display: true, text: '7-Day Patient Vitals Trend' }
//             }
//         }
//     });
// }

// document.addEventListener('DOMContentLoaded', () => {
//     // 1. Extract Patient ID (pid) from the URL
//     const urlParams = new URLSearchParams(window.location.search);
//     const patientId = urlParams.get('pid');
//     const chartErrorElement = document.getElementById('chartError');

//     if (!patientId) {
//         chartErrorElement.textContent = 'Error: Patient ID not found in URL.';
//         return;
//     }

//     // 2. Construct the API URL to fetch data (assuming path relative to doctor_charts.js)
//     // The path from assets/js/ to api/ is '../../api/'
//     const apiUrl = '../../api/doctor_fetch_data.php?pid=' + patientId; 

//     fetch(apiUrl)
//         .then(response => {
//             if (!response.ok) {
//                 // If unauthorized (401/403) or server error (500), try to read JSON error
//                 return response.json().then(err => { 
//                     throw new Error(err.error || response.statusText); 
//                 });
//             }
//             return response.json();
//         })
//         .then(data => {
//             // 3. Check for successful data and draw the chart
//             if (data.status === 'success' && data.labels.length > 0) {
//                 drawPatientVitalsChart(data);
//             } else {
//                 chartErrorElement.textContent = 'No readings available for the last 7 days.';
//             }
//         })
//         .catch(error => {
//             // 4. Handle fetch or authorization errors
//             console.error('Fatal Fetch Error:', error);
//             chartErrorElement.textContent = 'Error: Could not retrieve vital data. ' + error.message;
//         });
// });





/**
 * DOCTOR_CHARTS.JS
 * Fetches patient vital signs data via API and renders a 7-day trend chart for doctor viewing.
 * Features: Wave-form style, dual-axis for HR/SpO2, and horizontal scrolling for dense time-series data.
 * * NOTE: For the 'time' axis type to work in Chart.js, ensure you include Moment.js 
 * in your <head> or before Chart.js:
 * <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
 */

function drawPatientVitalsChart(data) {
    const ctx = document.getElementById('patientVitalsChart').getContext('2d');
    
    // Destroy existing chart instance to prevent memory leaks/overlap errors
    if (Chart.getChart("patientVitalsChart")) {
        Chart.getChart("patientVitalsChart").destroy();
    }
    
    // Map the fetched parallel arrays into the (x: timestamp, y: value) format required for time-series charts
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
                    label: 'Heart Rate (BPM)',
                    data: hrData,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    yAxisID: 'yHeart',
                    tension: 0.4, // Smooth, non-linear curve for wave effect
                    fill: 'origin', // Shaded wave area
                    pointRadius: 0, 
                    borderWidth: 3
                },
                {
                    label: 'Oxygen Saturation (SpO2 %)',
                    data: spo2Data,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'transparent',
                    yAxisID: 'ySpO2',
                    tension: 0.5, // Smooth, non-linear curve
                    fill: false, // Allows HR wave to show underneath (overlapping)
                    pointRadius: 0,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Required for horizontal scrolling
            interaction: {
                mode: 'index',
                intersect: false, // Tooltip shows for nearest point across both datasets
            },
            stacked: false, 
            scales: {
                x: {
                    // CRITICAL: Uses the 'time' type to accurately space timestamps
                    type: 'time', 
                    time: {
                        unit: 'hour',
                        displayFormats: {
                            hour: 'MMM D, h:mm A'
                        },
                        tooltipFormat: 'MMM D, h:mm A' 
                    },
                    title: { display: true, text: 'Time and Date' },
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
                    grid: { drawOnChartArea: false }, // Keep chart area clean
                    min: 90,
                    max: 100,
                    title: { display: true, text: 'SpO2 (%)' }
                }
            },
            plugins: {
                legend: { position: 'top' },
                title: { display: true, text: '7-Day Patient Vitals Trend (Time-Series Wave)' }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. HORIZONTAL SCROLLING SETUP (Must match the HTML wrapper in patient_detail.php)
    const chartWrapper = document.querySelector('.chart-content-wrapper');
    if (chartWrapper) {
        // Set fixed width for the inner wrapper to force horizontal scroll in the outer container
        chartWrapper.style.width = '1500px'; 
    }
    
    // 2. DATA FETCHING LOGIC
    const urlParams = new URLSearchParams(window.location.search);
    const patientId = urlParams.get('pid');
    const chartErrorElement = document.getElementById('chartError');

    if (!patientId) {
        chartErrorElement.textContent = 'Error: Patient ID not found in URL.';
        return;
    }

    // Path from assets/js/ to api/
    const apiUrl = '../../api/doctor_fetch_data.php?pid=' + patientId; 

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
                drawPatientVitalsChart(data);
            } else {
                chartErrorElement.textContent = 'No readings available for the last 7 days.';
            }
        })
        .catch(error => {
            console.error('Fatal Fetch/Authorization Error:', error);
            chartErrorElement.textContent = 'Error: Could not retrieve vital data. ' + error.message;
        });
});