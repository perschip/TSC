// Dashboard Charts JavaScript
let charts = {};

document.addEventListener("DOMContentLoaded", function() {
    console.log('DOM loaded, initializing charts');
    initializeCharts();
    setupChartTypeButtons();
    setupEventListeners();
});

function initializeCharts() {
    // Debug: Log chart data to console
    console.log("Chart data:", chartData);
    
    // Check if we have valid data
    if (!chartData) {
        console.error('Chart data is missing entirely');
        chartData = {}; // Initialize empty object to prevent errors
    }
    
    // Initialize empty objects for missing data to prevent errors
    if (!chartData.trend) chartData.trend = {};
    if (!chartData.referrers) chartData.referrers = {};
    if (!chartData.hourly) chartData.hourly = {};
    if (!chartData.devices) chartData.devices = {};
    
    // Create Hourly Activity Pattern Chart
    createHourlyChart();
    
    // Create Traffic Sources Chart
    createTrafficChart();
    
    // Create Device Breakdown Chart
    createDeviceChart();
    
    // Create Visitor Trend Chart
    createVisitorTrendChart();
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
                hourlyLabels = Object.keys(chartData.hourly).map(hour => `${hour}:00`);
                hourlyData = Object.values(chartData.hourly);
            }
        }
        
        // Fallback to sample data if we couldn't extract valid data
        if (hourlyLabels.length === 0) {
            hourlyLabels = ['00:00', '02:00', '04:00', '06:00', '08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'];
            hourlyData = [5, 10, 8, 15, 25, 30, 35, 40, 30, 25, 20, 10];
        }
        
        charts.hourly = new Chart(hourlyCtx.getContext("2d"), {
            type: "bar",
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Visitors',
                    data: hourlyData,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false
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
                        backgroundColor: "rgba(0, 0, 0, 0.8)",
                        titleColor: "#fff",
                        bodyColor: "#fff",
                        borderColor: "rgba(255, 255, 255, 0.1)",
                        borderWidth: 1,
                        cornerRadius: 8
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
        console.log('Hourly activity chart created successfully');
    } catch (error) {
        console.error('Error creating hourly activity chart:', error);
    }
}

function createTrafficChart() {
    try {
        const trafficCtx = document.getElementById("trafficChart");
        if (!trafficCtx) {
            console.error('trafficChart canvas not found');
            return;
        }
        
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
            }
        }
        
        // Fallback to sample data if we couldn't extract valid data
        if (trafficLabels.length === 0) {
            trafficLabels = ['Direct', 'Search', 'Social', 'Referral', 'Email', 'Other'];
            trafficData = [35, 25, 15, 10, 8, 7];
        }
        
        charts.traffic = new Chart(trafficCtx.getContext("2d"), {
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
                        position: 'right',
                    }
                },
                cutout: '70%'
            }
        });
        console.log('Traffic sources chart created successfully');
    } catch (error) {
        console.error('Error creating traffic sources chart:', error);
    }
}

function createDeviceChart() {
    try {
        const deviceCtx = document.getElementById("deviceChart");
        if (!deviceCtx) {
            console.error('deviceChart canvas not found');
            return;
        }
        
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
        
        charts.device = new Chart(deviceCtx.getContext("2d"), {
            type: "pie",
            data: {
                labels: deviceLabels,
                datasets: [{
                    data: deviceData,
                    backgroundColor: deviceColors,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        console.log('Device breakdown chart created successfully');
    } catch (error) {
        console.error('Error creating device breakdown chart:', error);
    }
}

function createVisitorTrendChart() {
    try {
        const visitorCtx = document.getElementById("visitorTrendChart");
        if (!visitorCtx) {
            console.error('visitorTrendChart canvas not found');
            return;
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
        } else {
            // Fallback to sample data
            const today = new Date();
            trendLabels = Array.from({length: 7}, (_, i) => {
                const date = new Date(today);
                date.setDate(date.getDate() - (6 - i));
                return date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
            });
            trendVisits = [120, 150, 180, 210, 190, 240, 280];
            trendUniqueVisitors = [100, 120, 140, 160, 150, 180, 210];
        }
        
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
