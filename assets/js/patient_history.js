document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('historyTable');
    const loadingMessage = document.getElementById('loading-message');
    const buttons = document.querySelectorAll('.btn-group button');
    const endpoint = '../../api/fetch_history_data.php?type=';
    
    /**
     * Maps database column names to professional, readable table headers.
     */
    const headerMap = {
        readings: {
            'timestamp': 'Time Recorded',
            'heart_rate': 'HR (BPM)',
            'spo2': 'SpO2 (%)',
            'temperature': 'Temp (°C)',
            'acc_ax': 'Accel X',
            'acc_ay': 'Accel Y',
            'acc_az': 'Accel Z'
        },
        alerts: {
            'timestamp': 'Time Recorded',
            'alert_type': 'Alert Type',
            'value': 'Trigger Value',
            'alert_message': 'Message',
            'status': 'Status'
        }
    };
    
    /**
     * Renders the table content (headers and rows).
     * @param {string} type - 'readings' or 'alerts'.
     * @param {Array<Object>} data - The array of history objects.
     */
    function renderTable(type, data) {
        if (!data || data.length === 0) {
            table.innerHTML = `<caption>No ${type} found.</caption>`;
            return;
        }

        const headers = headerMap[type];
        let theadHTML = '<thead><tr>';
        
        // 1. Generate Headers
        for (const key in headers) {
            theadHTML += `<th>${headers[key]}</th>`;
        }
        theadHTML += '</tr></thead>';
        
        let tbodyHTML = '<tbody>';
        
        // 2. Generate Rows Dynamically
        data.forEach(item => {
            tbodyHTML += '<tr>';
            for (const key in headers) {
                let displayValue = item[key];
                
                // --- Formatting and Badges (Professionalism) ---
                if (key === 'timestamp' || key === 'recorded_at') {
                    displayValue = moment(displayValue).format('MMM DD, YYYY HH:mm:ss');
                } else if (key === 'value' || key === 'temperature' || key.startsWith('acc_')) {
                    displayValue = parseFloat(displayValue).toFixed(2);
                } else if (key === 'status') {
                    const statusClass = displayValue === 'READ' ? 'badge-success' : 'badge-warning';
                    displayValue = `<span class="badge ${statusClass}">${displayValue}</span>`;
                } else if (key === 'alert_type') {
                     displayValue = `<span class="badge badge-danger">${displayValue}</span>`;
                }

                tbodyHTML += `<td>${displayValue}</td>`;
            }
            tbodyHTML += '</tr>';
        });
        tbodyHTML += '</tbody>';
        
        table.innerHTML = theadHTML + tbodyHTML;
    }

    /**
     * Fetches data via AJAX and updates the table.
     * @param {string} type - 'readings' or 'alerts'.
     */
    function loadData(type) {
        loadingMessage.textContent = `Loading ${type}...`;
        loadingMessage.style.display = 'block';
        table.innerHTML = ''; // Clear previous table

        fetch(endpoint + type)
            .then(response => response.json())
            .then(data => {
                loadingMessage.style.display = 'none';
                if (data.status === 'success') {
                    renderTable(data.type, data.data);
                } else {
                    table.innerHTML = `<caption>Error loading data: ${data.message}</caption>`;
                }
            })
            .catch(error => {
                loadingMessage.style.display = 'none';
                console.error('Fetch error:', error);
                table.innerHTML = '<caption>Network or server error.</caption>';
            });
    }

    // --- Event Listeners ---
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            
            // 1. Toggle Button Active State
            buttons.forEach(btn => btn.classList.remove('active', 'btn-primary', 'btn-secondary'));
            this.classList.add('active', 'btn-primary');
            // Ensure the inactive button uses the secondary style
            const otherButton = document.querySelector(`.btn-group button:not([data-type='${type}'])`);
            otherButton.classList.add('btn-secondary');

            // 2. Load New Data
            loadData(type);
        });
    });

    // Load 'readings' by default on page load
    loadData('readings');
});