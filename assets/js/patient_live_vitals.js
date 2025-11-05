// Function to determine the status class/text based on clinical thresholds
function getStatus(value, min, max, type) {
    const numericValue = parseFloat(value);
    
    if (isNaN(numericValue)) return { status: 'normal', text: 'N/A' };

    let isAlert = false;
    let text = 'Normal';

    if (type === 'hr') {
        if (numericValue > 120 || numericValue < 50) { // Pregnancy standard: 60-120 BPM
            isAlert = true;
            text = numericValue > 120 ? 'ALERT High' : 'ALERT Low';
        }
    } else if (type === 'spo2') {
        if (numericValue < 95) { // Standard: >= 95%
            isAlert = true;
            text = 'Low';
        } else {
            text = 'Healthy';
        }
    } else if (type === 'temp') {
        if (numericValue >= 37.8) { // Fever threshold
            isAlert = true;
            text = 'Fever';
        }
    }

    return { 
        status: isAlert ? 'alert' : 'normal', 
        text: text 
    };
}

function updateVitals() {
    // Path to your new API endpoint
    const endpoint = '../../api/fetch_latest_reading.php'; 

    fetch(endpoint)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.heart_rate !== 'N/A') {
                
                // --- VITAL SIGNS ---
                const hrStatus = getStatus(data.heart_rate, 50, 120, 'hr');
                const spo2Status = getStatus(data.spo2, 95, 100, 'spo2');
                const tempStatus = getStatus(data.temperature, null, 37.8, 'temp');

                // 1. Heart Rate Card
                document.querySelector('.heart-rate-card .data-value').textContent = data.heart_rate;
                const hrIndicator = document.querySelector('.heart-rate-card .status-indicator');
                hrIndicator.className = 'status-indicator ' + hrStatus.status;
                hrIndicator.querySelector('i').nextSibling.textContent = ' Status: ' + hrStatus.text;


                // 2. SpO2 Card
                document.querySelector('.spo2-card .data-value').textContent = data.spo2;
                const spo2Indicator = document.querySelector('.spo2-card .status-indicator');
                spo2Indicator.className = 'status-indicator ' + spo2Status.status;
                spo2Indicator.querySelector('i').nextSibling.textContent = ' Status: ' + spo2Status.text;

                // 3. Temperature Card
                document.querySelector('.temp-card .data-value').textContent = data.temperature;
                const tempIndicator = document.querySelector('.temp-card .status-indicator');
                tempIndicator.className = 'status-indicator ' + tempStatus.status;
                tempIndicator.querySelector('i').nextSibling.textContent = ' Status: ' + tempStatus.text;

                // 4. Acceleration Card
                document.querySelector('.acc-card .data-value').textContent = data.acc_magnitude + ' G';
                document.querySelector('.acc-card .status-indicator').textContent = 
                    ` Components: X: ${data.acc_ax} | Y: ${data.acc_ay} | Z: ${data.acc_az}`;


                // 5. Summary Card (Need manual update for inner values)
                document.querySelector('.latest-reading-summary .vitals-item:nth-child(1) .value').textContent = data.heart_rate + ' BPM';
                document.querySelector('.latest-reading-summary .vitals-item:nth-child(2) .value').textContent = data.spo2 + ' %';
                
                // Update overall status
                const isOverallAlert = hrStatus.status === 'alert' || spo2Status.status === 'alert' || tempStatus.status === 'alert';
                const overallIndicator = document.querySelector('.latest-reading-summary .status-indicator');
                overallIndicator.className = 'status-indicator ' + (isOverallAlert ? 'alert' : 'normal');
                overallIndicator.querySelector('i').nextSibling.textContent = ' Overall Status: ' + (isOverallAlert ? 'Review Required' : 'Stable');

                // 6. Last Updated Card
                document.querySelector('.timestamp-card .updated-time').textContent = data.last_updated;
                
            } else if (data.message === 'No readings found yet.') {
                 console.log("No data yet.");
            } else {
                console.error("API Error:", data.message);
            }
        })
        .catch(error => console.error('Network or Parse Error:', error));
}

// Ensure the DOM is fully loaded before starting the polling
document.addEventListener('DOMContentLoaded', function() {
    // Run once immediately on load (besides the PHP fetch)
    updateVitals(); 
    
    // Run every 5 seconds (5000 milliseconds)
    setInterval(updateVitals, 5000); 
});