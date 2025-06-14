// Dashboard Charts JavaScript

document.addEventListener("DOMContentLoaded", function() {
    console.log('DOM loaded, initializing charts');
    
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.log('Chart.js not loaded, loading dynamically');
        loadChartJS(function() {
            console.log('Chart.js loaded dynamically');
            // Give a small delay to ensure DOM is fully ready
            setTimeout(function() {
                initializeCharts();
                setupChartTypeButtons();
                setupEventListeners();
            }, 100);
        });
    } else {
        console.log('Chart.js already loaded');
        // Give a small delay to ensure DOM is fully ready
        setTimeout(function() {
            initializeCharts();
            setupChartTypeButtons();
            setupEventListeners();
        }, 100);
    }
});

// Also initialize charts when window loads (as a fallback)
window.addEventListener('load', function() {
    console.log('Window loaded, checking if charts need initialization');
    // Check if charts are already initialized
    const hasCharts = Object.keys(charts).length > 0;
    if (!hasCharts) {
        console.log('Charts not initialized yet, initializing now');
        if (typeof Chart === 'undefined') {
            loadChartJS(function() {
                initializeCharts();
                setupChartTypeButtons();
                setupEventListeners();
            });
        } else {
            initializeCharts();
            setupChartTypeButtons();
            setupEventListeners();
        }
    }
});

// Function to dynamically load Chart.js if it's not available
function loadChartJS(callback) {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
    script.onload = callback;
    script.onerror = function() {
        console.error('Failed to load Chart.js dynamically');
    };
    document.head.appendChild(script);
}

// Helper function to check if a canvas element exists and is valid
function isCanvasValid(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.error(`Canvas element '${canvasId}' not found`);
        return false;
    }
    if (!canvas.getContext) {
        console.error(`Canvas context not available for '${canvasId}'`);
        return false;
    }
    return canvas;
}

// Create default chart data if PHP data is missing
function createDefaultChartData() {
    return {
        trend: createDefaultTrendData(),
        referrers: createDefaultReferrersData(),
        hourly: createDefaultHourlyData(),
        devices: createDefaultDevicesData()
    };
}

function createDefaultTrendData() {
    const today = new Date();
    const dates = Array.from({length: 7}, (_, i) => {
        const date = new Date(today);
        date.setDate(date.getDate() - (6 - i));
        return date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
    });
    
    return {
        dates: dates,
        visits: [120, 150, 180, 210, 190, 240, 280],
        uniqueVisitors: [100, 120, 140, 160, 150, 180, 210]
    };
}

function createDefaultReferrersData() {
    return {
        labels: ['Direct', 'Search', 'Social', 'Referral', 'Email', 'Other'],
        data: [35, 25, 15, 10, 8, 7],
        colors: [
            'rgba(13, 110, 253, 0.8)',  // Blue
            'rgba(25, 135, 84, 0.8)',   // Green
            'rgba(255, 193, 7, 0.8)',   // Yellow
            'rgba(220, 53, 69, 0.8)',   // Red
            'rgba(111, 66, 193, 0.8)',  // Purple
            'rgba(23, 162, 184, 0.8)'   // Cyan
        ]
    };
}

function createDefaultHourlyData() {
    return {
        labels: ['00:00', '02:00', '04:00', '06:00', '08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'],
        data: [5, 10, 8, 15, 25, 30, 35, 40, 30, 25, 20, 10]
    };
}

function createDefaultDevicesData() {
    return [
        { device: 'Desktop', visits: 67, percentage: 53 },
        { device: 'Mobile', visits: 48, percentage: 38 },
        { device: 'Tablet', visits: 12, percentage: 9 }
    ];
}

function initializeCharts() {
    // Debug: Log chart data to console
    console.log("Chart data:", chartData);
    
    // Check if chartData is defined, if not create a default object
    if (typeof chartData === 'undefined') {
        console.error('chartData is undefined, creating default data');
        window.chartData = createDefaultChartData();
    } else if (!chartData) {
        console.error('Chart data is null or empty, creating default data');
        window.chartData = createDefaultChartData();
    }
    
    // Initialize empty objects for missing data to prevent errors
    if (!chartData.trend) chartData.trend = createDefaultTrendData();
    if (!chartData.referrers) chartData.referrers = createDefaultReferrersData();
    if (!chartData.hourly) chartData.hourly = createDefaultHourlyData();
    if (!chartData.devices) chartData.devices = createDefaultDevicesData();
    
    // Log detailed structure of chart data for debugging
    console.log('DETAILED CHART DATA STRUCTURE:');
    console.log('chartData type:', typeof chartData);
    console.log('chartData keys:', Object.keys(chartData));
    console.log('trend data:', chartData.trend);
    console.log('referrers data:', chartData.referrers);
    console.log('hourly data:', chartData.hourly);
    console.log('devices data:', chartData.devices);
    
    // Clear any existing charts to prevent issues
    if (charts.visitorTrend) charts.visitorTrend.destroy();
    if (charts.hourly) charts.hourly.destroy();
    if (charts.traffic) charts.traffic.destroy();
    if (charts.device) charts.device.destroy();
    
    // Reset the charts object
    charts = {};
    
    // Create charts in a specific order with delays to ensure proper rendering
    try {
        console.log('Starting chart initialization sequence');
        
        // First create the trend chart
        setTimeout(function() {
            try {
                createVisitorTrendChart();
                console.log('Visitor trend chart initialized');
            } catch (error) {
                console.error('Error initializing visitor trend chart:', error);
            }
            
            // Create hourly chart after a delay
            setTimeout(function() {
                try {
                    createHourlyChart();
                    console.log('Hourly chart initialized');
                } catch (error) {
                    console.error('Error initializing hourly chart:', error);
                }
                
                // Create traffic chart after another delay
                setTimeout(function() {
                    try {
                        createTrafficChart();
                        console.log('Traffic chart initialized');
                    } catch (error) {
                        console.error('Error initializing traffic chart:', error);
                    }
                    
                    // Create device chart after another delay
                    setTimeout(function() {
                        try {
                            createDeviceChart();
                            console.log('Device chart initialized');
                            console.log('All charts initialized successfully');
                        } catch (error) {
                            console.error('Error initializing device chart:', error);
                        }
                    }, 100);
                }, 100);
            }, 100);
        }, 100);
    } catch (error) {
        console.error('Error in chart initialization sequence:', error);
    }
}

function createHourlyChart() {
    try {
        const hourlyCtx = document.getElementById("hourlyChart");
        if (!hourlyCtx) {
            console.error('hourlyChart canvas not found');
            return;
        }
        
        console.log('Hourly chart data:', chartData.hourly);
        
        // Create a simple dataset if the data structure is different than expected
        let hourlyLabels = [];
        let hourlyData = [];
        
        if (chartData.hourly && Array.isArray(chartData.hourly.labels) && Array.isArray(chartData.hourly.data)) {
            hourlyLabels = chartData.hourly.labels;
            hourlyData = chartData.hourly.data;
        } else if (chartData.hourly) {
            // If the data is in a different format, try to adapt
            if (typeof chartData.hourly === 'object') {
                // Check if it's an array or object
                if (Array.isArray(chartData.hourly)) {
                    // If it's an array of objects with hour and count properties
                    hourlyLabels = chartData.hourly.map(item => `${item.hour || '0'}:00`);
                    hourlyData = chartData.hourly.map(item => item.count || item.visits || 0);
                } else {
                    // If it's an object with hour keys and count values
                    hourlyLabels = Object.keys(chartData.hourly).map(hour => `${hour}:00`);
                    hourlyData = Object.values(chartData.hourly);
                }
            }
        }
        
        // Fallback to sample data if we couldn't extract valid data
        if (hourlyLabels.length === 0) {
            hourlyLabels = ['00:00', '02:00', '04:00', '06:00', '08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'];
            hourlyData = [5, 10, 8, 15, 25, 30, 35, 40, 30, 25, 20, 10];
        }
        
        // Log the data we're using for the chart
        console.log('Using hourly labels:', hourlyLabels);
        console.log('Using hourly data:', hourlyData);
        
        // Ensure we have a clean 2d context
        const ctx = hourlyCtx.getContext("2d");
        if (!ctx) {
            console.error('Could not get 2D context for hourlyChart');
            return;
        }
        
        // Create the chart with a try-catch to catch any errors
        try {
            charts.hourly = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: hourlyLabels,
                    datasets: [{
                        label: "Visits",
                        backgroundColor: "rgba(13, 110, 253, 0.8)",
                        borderColor: "rgba(13, 110, 253, 1)",
                        borderWidth: 1,
                        data: hourlyData
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ": " + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            console.log('Hourly chart created successfully');
        } catch (error) {
            console.error('Error creating hourly chart:', error);
        }
    } catch (error) {
        console.error('Error creating hourly chart:', error);
    }
}

function createTrafficChart() {
    try {
        // Use the helper function to check if canvas is valid
        const trafficCtx = isCanvasValid("trafficChart");
        if (!trafficCtx) return;
        
        // Check if a chart already exists for this canvas and destroy it
        if (charts.traffic) {
            charts.traffic.destroy();
            charts.traffic = null;
        }
        
        console.log('Creating traffic chart...');
        console.log('Traffic chart data:', chartData.referrers);
        
        // Create a simple dataset if the data structure is different than expected
        let trafficLabels = [];
        let trafficData = [];
        let trafficColors = [
            'rgba(13, 110, 253, 0.8)',  // Blue
            'rgba(25, 135, 84, 0.8)',   // Green
            'rgba(255, 193, 7, 0.8)',   // Yellow
            'rgba(220, 53, 69, 0.8)',   // Red
            'rgba(111, 66, 193, 0.8)',  // Purple
            'rgba(23, 162, 184, 0.8)'   // Cyan
        ];
        
        if (chartData.referrers && Array.isArray(chartData.referrers.labels) && Array.isArray(chartData.referrers.data)) {
            trafficLabels = chartData.referrers.labels;
            trafficData = chartData.referrers.data;
            if (Array.isArray(chartData.referrers.colors)) {
                trafficColors = chartData.referrers.colors;
            }
        } else if (chartData.referrers) {
            // If the data is in a different format, try to adapt
            if (Array.isArray(chartData.referrers)) {
                // If it's an array of objects with source and count properties
                trafficLabels = chartData.referrers.map(item => item.source || item.name || 'Unknown');
                trafficData = chartData.referrers.map(item => item.count || item.visits || 0);
            } else if (typeof chartData.referrers === 'object') {
                // If it's an object with source keys and count values
                trafficLabels = Object.keys(chartData.referrers);
                trafficData = Object.values(chartData.referrers);
            }
        }
        
        // Fallback to sample data if we couldn't extract valid data
        if (trafficLabels.length === 0) {
            trafficLabels = ['Direct', 'Search', 'Social', 'Referral', 'Email', 'Other'];
            trafficData = [35, 25, 15, 10, 8, 7];
        }
        
        // Log the data we're using for the chart
        console.log('Using traffic labels:', trafficLabels);
        console.log('Using traffic data:', trafficData);
        
        // Ensure we have a clean 2d context
        const ctx = trafficCtx.getContext("2d");
        if (!ctx) {
            console.error('Could not get 2D context for trafficChart');
            return;
        }
        
        // Create the chart with a try-catch to catch any errors
        try {
            charts.traffic = new Chart(ctx, {
                type: "doughnut",
                data: {
                    labels: trafficLabels,
                    datasets: [{
                        data: trafficData,
                        backgroundColor: trafficColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
            console.log('Traffic chart created successfully');
        } catch (error) {
            console.error('Error creating traffic chart:', error);
        }
    } catch (error) {
        console.error('Error creating traffic chart:', error);
    }
}

function createDeviceChart() {
    try {
        // Use the helper function to check if canvas is valid
        const deviceCtx = isCanvasValid("deviceChart");
        if (!deviceCtx) return;
        
        // Check if a chart already exists for this canvas and destroy it
        if (charts.device) {
            charts.device.destroy();
            charts.device = null;
        }
        
        console.log('Creating device chart...');
        console.log('Device chart data:', chartData.devices);
        
        // Create a simple dataset if the data structure is different than expected
        let deviceLabels = ["Desktop", "Mobile", "Tablet"];
        let deviceData = [67, 48, 12]; // Default sample data
        let deviceColors = [
            "rgba(13, 110, 253, 0.8)", // Blue for Desktop
            "rgba(25, 135, 84, 0.8)",  // Green for Mobile
            "rgba(255, 193, 7, 0.8)"   // Yellow for Tablet
        ];
        
        // Try to extract data from chartData
        if (chartData.devices) {
            if (Array.isArray(chartData.devices)) {
                // If it's an array of device objects
                deviceLabels = chartData.devices.map(device => device.device || device.name || 'Unknown');
                deviceData = chartData.devices.map(device => device.visits || device.count || 0);
            } else if (typeof chartData.devices === 'object') {
                // If it's an object with device types as keys
                deviceLabels = Object.keys(chartData.devices);
                deviceData = Object.values(chartData.devices);
            }
        }
        
        // Log the data we're using for the chart
        console.log('Using device labels:', deviceLabels);
        console.log('Using device data:', deviceData);
        
        // Ensure we have a clean 2d context
        const ctx = deviceCtx.getContext("2d");
        if (!ctx) {
            console.error('Could not get 2D context for deviceChart');
            return;
        }
        
        // Create the chart with a try-catch to catch any errors
        try {
            charts.device = new Chart(ctx, {
                type: "pie",
                data: {
                    labels: deviceLabels,
                    datasets: [{
                        data: deviceData,
                        backgroundColor: deviceColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((acc, data) => acc + data, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            console.log('Device chart created successfully');
        } catch (error) {
            console.error('Error creating device chart:', error);
        }
    } catch (error) {
        console.error('Error creating device chart:', error);
    }
}

function createVisitorTrendChart() {
    try {
        // Use the helper function to check if canvas is valid
        const visitorCtx = isCanvasValid("visitorTrendChart");
        if (!visitorCtx) return;
        
        // Check if a chart already exists for this canvas and destroy it
        if (charts.visitorTrend) {
            charts.visitorTrend.destroy();
            charts.visitorTrend = null;
        }
        
        console.log('Visitor trend data:', chartData.trend);
        
        // Create a simple dataset if the data structure is different than expected
        let trendLabels = [];
        let trendVisits = [];
        let trendUniqueVisitors = [];
        
        if (chartData.trend && Array.isArray(chartData.trend.dates) && 
            Array.isArray(chartData.trend.visits) && 
            Array.isArray(chartData.trend.uniqueVisitors)) {
            trendLabels = chartData.trend.dates;
            trendVisits = chartData.trend.visits;
            trendUniqueVisitors = chartData.trend.uniqueVisitors;
        } else if (chartData.trend) {
            // Try to extract data from different formats
            if (Array.isArray(chartData.trend)) {
                // If it's an array of objects with date and visits properties
                trendLabels = chartData.trend.map(item => item.date || '');
                trendVisits = chartData.trend.map(item => item.visits || 0);
                trendUniqueVisitors = chartData.trend.map(item => item.unique || item.uniqueVisitors || Math.floor(item.visits * 0.8) || 0);
            } else if (typeof chartData.trend === 'object') {
                // If it has separate arrays for different metrics
                if (chartData.trend.dates) trendLabels = chartData.trend.dates;
                if (chartData.trend.visits) trendVisits = chartData.trend.visits;
                if (chartData.trend.unique) trendUniqueVisitors = chartData.trend.unique;
                else if (chartData.trend.uniqueVisitors) trendUniqueVisitors = chartData.trend.uniqueVisitors;
            }
        }
        
        // Fallback to sample data if we still don't have valid data
        if (trendLabels.length === 0) {
            const today = new Date();
            trendLabels = Array.from({length: 7}, (_, i) => {
                const date = new Date(today);
                date.setDate(date.getDate() - (6 - i));
                return date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
            });
            trendVisits = [120, 150, 180, 210, 190, 240, 280];
            trendUniqueVisitors = [100, 120, 140, 160, 150, 180, 210];
        }
        
        // Log the data we're using for the chart
        console.log('Using trend labels:', trendLabels);
        console.log('Using trend visits data:', trendVisits);
        console.log('Using trend unique visitors data:', trendUniqueVisitors);
        
        // Create chart configuration
        const chartConfig = {
            type: "line", // Default type, will be changed by buttons
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: "Total Visits",
                        data: trendVisits,
                        backgroundColor: "rgba(13, 110, 253, 0.1)",
                        borderColor: "rgba(13, 110, 253, 1)",
                        pointBackgroundColor: "rgba(13, 110, 253, 1)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgba(13, 110, 253, 1)",
                        tension: 0.3,
                        fill: false // Will be changed for area chart
                    },
                    {
                        label: "Unique Visitors",
                        data: trendUniqueVisitors,
                        backgroundColor: "rgba(25, 135, 84, 0.1)",
                        borderColor: "rgba(25, 135, 84, 1)",
                        pointBackgroundColor: "rgba(25, 135, 84, 1)",
                        pointBorderColor: "#fff",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgba(25, 135, 84, 1)",
                        tension: 0.3,
                        fill: false // Will be changed for area chart
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        };
        
        // Create the chart
        charts.visitorTrend = new Chart(visitorCtx.getContext("2d"), chartConfig);
        console.log('Visitor trend chart created successfully');
    } catch (error) {
        console.error('Error creating visitor trend chart:', error);
    }
}

function setupEventListeners() {
    // Add event listeners for any interactive elements here
    console.log('Setting up event listeners');
    
    // Period selector
    const periodSelector = document.getElementById('period-selector');
    if (periodSelector) {
        periodSelector.addEventListener('change', function() {
            window.location.href = '?period=' + this.value;
        });
    }

    // Comparison toggle
    const compareToggle = document.getElementById('compare-toggle');
    if (compareToggle) {
        compareToggle.addEventListener('change', function() {
            const comparisonSection = document.getElementById('comparison-section');
            if (comparisonSection) {
                comparisonSection.style.display = this.checked ? 'block' : 'none';
            }
        });
    }
    
    // Refresh chart buttons
    document.querySelectorAll('.refresh-chart').forEach(button => {
        button.addEventListener('click', function() {
            const targetChart = this.getAttribute('data-target');
            refreshChart(targetChart);
        });
    });
}

function setupChartTypeButtons() {
    // Set up buttons to switch between chart types for visitor trend
    console.log('Setting up chart type buttons');
    
    const chartTypeButtons = document.querySelectorAll('[data-chart-type]');
    if (chartTypeButtons.length === 0) {
        console.error('Chart type buttons not found');
        return;
    }
    
    // Remove any existing event listeners (to prevent duplicates)
    chartTypeButtons.forEach(button => {
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
    });
    
    // Re-select the buttons after replacing them
    const updatedButtons = document.querySelectorAll('[data-chart-type]');
    
    chartTypeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const chartType = this.getAttribute('data-chart-type');
            console.log('Switching chart type to:', chartType);
            
            // Remove active class from all buttons
            chartTypeButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            // Update chart type
            updateChartType(chartType);
        });
    });
}

function updateChartType(chartType) {
    if (!charts.visitorTrend) {
        console.error('Visitor trend chart not initialized');
        return;
    }
    
    const chart = charts.visitorTrend;
    
    // Save current data
    const labels = chart.data.labels;
    const datasets = chart.data.datasets;
    
    // Destroy current chart
    chart.destroy();
    
    // Update dataset configuration based on chart type
    datasets.forEach(dataset => {
        if (chartType === 'line') {
            dataset.fill = false;
            dataset.borderWidth = 2;
        } else if (chartType === 'bar') {
            dataset.fill = false;
            dataset.borderWidth = 1;
        } else if (chartType === 'area') {
            dataset.fill = true;
            dataset.borderWidth = 1;
        }
    });
    
    // Create new chart with updated type
    const visitorCtx = document.getElementById("visitorTrendChart");
    charts.visitorTrend = new Chart(visitorCtx.getContext("2d"), {
        type: chartType === 'area' ? 'line' : chartType, // 'area' is actually a line chart with fill=true
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    console.log('Chart type updated to:', chartType);
}

function refreshChart(chartId) {
    console.log('Refreshing chart:', chartId);
    // Add animation to show refresh is happening
    const button = document.querySelector(`[data-target="${chartId}"]`);
    if (button) {
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.add('fa-spin');
            setTimeout(() => {
                icon.classList.remove('fa-spin');
            }, 1000);
        }
    }
    
    // In a real application, you would fetch new data from the server here
    // For demo purposes, we'll just simulate a data refresh
    if (chartId === 'visitorTrendChart' && charts.visitorTrend) {
        const chart = charts.visitorTrend;
        // Slightly modify the data
        chart.data.datasets.forEach(dataset => {
            dataset.data = dataset.data.map(value => {
                return Math.max(0, value + Math.floor(Math.random() * 20) - 10);
            });
        });
        chart.update();
    } else if (chartId === 'trafficChart' && charts.traffic) {
        const chart = charts.traffic;
        // Slightly modify the data
        chart.data.datasets[0].data = chart.data.datasets[0].data.map(value => {
            return Math.max(1, value + Math.floor(Math.random() * 5) - 2);
        });
        chart.update();
    } else if (chartId === 'deviceChart' && charts.device) {
        const chart = charts.device;
        // Slightly modify the data
        chart.data.datasets[0].data = chart.data.datasets[0].data.map(value => {
            return Math.max(1, value + Math.floor(Math.random() * 5) - 2);
        });
        chart.update();
    } else if (chartId === 'hourlyChart' && charts.hourly) {
        const chart = charts.hourly;
        // Slightly modify the data
        chart.data.datasets[0].data = chart.data.datasets[0].data.map(value => {
            return Math.max(0, value + Math.floor(Math.random() * 5) - 2);
        });
        chart.update();
    }
}
