<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Force no caching for this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if period filter was applied
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$validPeriods = ['week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'month';
}

// Get all view data with error handling and debugging
function getAnalyticsData($pdo, $period = 'month') {
    $timeConstraint = '';
    
    // Find the actual date column in the visits table
    try {
        // Get table structure to check column names
        $tableCheckQuery = "DESCRIBE visits";
        $tableCheckStmt = $pdo->prepare($tableCheckQuery);
        $tableCheckStmt->execute();
        $columns = $tableCheckStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Determine the date column (it could be 'visit_date', 'created_at', or something else)
        $dateColumn = 'created_at'; // Default to created_at
        if (in_array('visit_date', $columns)) {
            $dateColumn = 'visit_date';
        }
        
    } catch (PDOException $e) {
        // If we can't check the columns, assume it's created_at
        $dateColumn = 'created_at';
    }
    
    switch ($period) {
        case 'week':
            $timeConstraint = "WHERE $dateColumn >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
            break;
        case 'month':
            $timeConstraint = "WHERE $dateColumn >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
            break;
        case 'year':
            $timeConstraint = "WHERE $dateColumn >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)";
            break;
        case 'all':
            $timeConstraint = "";
            break;
        default:
            $timeConstraint = "WHERE $dateColumn >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
    }
    
    try {
        // Debug information
        $debug = [];
        $debug[] = "Period: $period";
        $debug[] = "Date column used: $dateColumn";
        $debug[] = "Time constraint: $timeConstraint";
        
        // Overall stats
        $visitsQuery = "SELECT 
                          COUNT(*) as total_visits,
                          COUNT(DISTINCT visitor_ip) as unique_visitors,
                          AVG(pages_viewed) as avg_pages,
                          AVG(time_on_site) as avg_time
                        FROM visits $timeConstraint";
                        
        $debug[] = "Visits query: $visitsQuery";
        
        $visitsStmt = $pdo->prepare($visitsQuery);
        $visitsStmt->execute();
        $visitsStats = $visitsStmt->fetch();
        
        $debug[] = "Visits stats: " . print_r($visitsStats, true);
        
        // eBay clicks
        $ebayClickDateColumn = 'click_date'; // Default
        try {
            $ebayTableCheckQuery = "DESCRIBE ebay_clicks";
            $ebayTableCheckStmt = $pdo->prepare($ebayTableCheckQuery);
            $ebayTableCheckStmt->execute();
            $ebayColumns = $ebayTableCheckStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('created_at', $ebayColumns)) {
                $ebayClickDateColumn = 'created_at';
            }
        } catch (PDOException $e) {
            // Keep default if error
        }
        
        $ebayTimeConstraint = str_replace($dateColumn, $ebayClickDateColumn, $timeConstraint);
        $ebayQuery = "SELECT COUNT(*) as total_clicks FROM ebay_clicks $ebayTimeConstraint";
        $ebayStmt = $pdo->prepare($ebayQuery);
        $ebayStmt->execute();
        $ebayClicks = $ebayStmt->fetch()['total_clicks'];
        
        $debug[] = "eBay date column: $ebayClickDateColumn";
        $debug[] = "eBay clicks: $ebayClicks";
        
        // Whatnot clicks
        $whatnotClickDateColumn = 'click_date'; // Default
        try {
            $whatnotTableCheckQuery = "DESCRIBE whatnot_clicks";
            $whatnotTableCheckStmt = $pdo->prepare($whatnotTableCheckQuery);
            $whatnotTableCheckStmt->execute();
            $whatnotColumns = $whatnotTableCheckStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('created_at', $whatnotColumns)) {
                $whatnotClickDateColumn = 'created_at';
            }
        } catch (PDOException $e) {
            // Keep default if error
        }
        
        $whatnotTimeConstraint = str_replace($dateColumn, $whatnotClickDateColumn, $timeConstraint);
        $whatnotQuery = "SELECT COUNT(*) as total_clicks FROM whatnot_clicks $whatnotTimeConstraint";
        $whatnotStmt = $pdo->prepare($whatnotQuery);
        $whatnotStmt->execute();
        $whatnotClicks = $whatnotStmt->fetch()['total_clicks'];
        
        $debug[] = "Whatnot date column: $whatnotClickDateColumn";
        $debug[] = "Whatnot clicks: $whatnotClicks";
        
        // Daily visits trend
        $dailyTrendQuery = "SELECT 
                               DATE_FORMAT($dateColumn, '%Y-%m-%d') as date,
                               COUNT(*) as visits,
                               COUNT(DISTINCT visitor_ip) as unique_visitors
                             FROM visits 
                             $timeConstraint
                             GROUP BY DATE_FORMAT($dateColumn, '%Y-%m-%d')
                             ORDER BY date ASC";
                             
        $dailyTrendStmt = $pdo->prepare($dailyTrendQuery);
        $dailyTrendStmt->execute();
        $dailyTrend = $dailyTrendStmt->fetchAll();
        
        $debug[] = "Daily trend count: " . count($dailyTrend);
        
        // Referrer sources
        $referrerQuery = "SELECT 
                            CASE
                                WHEN referrer LIKE '%google.com%' THEN 'Google'
                                WHEN referrer LIKE '%facebook.com%' THEN 'Facebook'
                                WHEN referrer LIKE '%instagram.com%' THEN 'Instagram'
                                WHEN referrer LIKE '%twitter.com%' THEN 'Twitter'
                                WHEN referrer LIKE '%whatnot.com%' THEN 'Whatnot'
                                WHEN referrer LIKE '%ebay.com%' THEN 'eBay'
                                WHEN referrer = '' THEN 'Direct'
                                ELSE 'Other'
                            END as source,
                            COUNT(*) as count
                          FROM visits
                          $timeConstraint
                          GROUP BY source
                          ORDER BY count DESC";
                          
        $referrerStmt = $pdo->prepare($referrerQuery);
        $referrerStmt->execute();
        $referrers = $referrerStmt->fetchAll();
        
        $debug[] = "Referrer count: " . count($referrers);
        
        // Log debug information for admins
        if (isset($_GET['debug']) && $_GET['debug'] == 1) {
            error_log(print_r($debug, true));
        }
        
        return [
            'visits' => $visitsStats,
            'ebay_clicks' => $ebayClicks,
            'whatnot_clicks' => $whatnotClicks,
            'daily_trend' => $dailyTrend,
            'referrers' => $referrers,
            'debug' => $debug
        ];
    } catch (PDOException $e) {
        // Log the error
        error_log('Analytics data error: ' . $e->getMessage());
        
        // Return default values on error
        return [
            'visits' => [
                'total_visits' => 0,
                'unique_visitors' => 0,
                'avg_pages' => 0,
                'avg_time' => 0
            ],
            'ebay_clicks' => 0,
            'whatnot_clicks' => 0,
            'daily_trend' => [],
            'referrers' => [],
            'error' => $e->getMessage(),
            'debug' => isset($debug) ? $debug : []
        ];
    }
}

// Get analytics data with fresh query
try {
    // Add a random query parameter to force fresh data
    $analyticsData = getAnalyticsData($pdo, $period);

    // Format daily trend data for chart
    $dates = [];
    $visits = [];
    $uniqueVisitors = [];

    foreach ($analyticsData['daily_trend'] as $day) {
        $dates[] = date('M j', strtotime($day['date']));
        $visits[] = $day['visits'];
        $uniqueVisitors[] = $day['unique_visitors'];
    }

    // Format referrer data for chart
    $referrerLabels = [];
    $referrerData = [];

    foreach ($analyticsData['referrers'] as $referrer) {
        $referrerLabels[] = $referrer['source'];
        $referrerData[] = $referrer['count'];
    }
} catch (Exception $e) {
    // Log the error
    error_log('Analytics processing error: ' . $e->getMessage());
    
    // Default values if anything fails
    $analyticsData = [
        'visits' => [
            'total_visits' => 0,
            'unique_visitors' => 0,
            'avg_pages' => 0,
            'avg_time' => 0
        ],
        'ebay_clicks' => 0,
        'whatnot_clicks' => 0,
        'error' => $e->getMessage()
    ];
    $dates = [];
    $visits = [];
    $uniqueVisitors = [];
    $referrerLabels = [];
    $referrerData = [];
}

// Page variables
$page_title = 'Analytics Dashboard';
$use_charts = true;

// Header actions for period filtering
$header_actions = '
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <a href="?period=week&t=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'week' ? 'active' : '') . '">Week</a>
        <a href="?period=month&t=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'month' ? 'active' : '') . '">Month</a>
        <a href="?period=year&t=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'year' ? 'active' : '') . '">Year</a>
        <a href="?period=all&t=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'all' ? 'active' : '') . '">All Time</a>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="refresh-data">
        <i class="fas fa-sync-alt me-1"></i> Refresh Data
    </button>
</div>
';

// Extra scripts for charts and refresh functionality
$extra_scripts = '
<script>
    ' . (count($dates) > 0 ? '
    // Visitor Trend Chart
    const visitorTrendChart = document.getElementById("visitorTrendChart").getContext("2d");
    
    new Chart(visitorTrendChart, {
        type: "line",
        data: {
            labels: ' . json_encode($dates) . ',
            datasets: [
                {
                    label: "Total Visits",
                    data: ' . json_encode($visits) . ',
                    backgroundColor: "rgba(13, 110, 253, 0.05)",
                    borderColor: "rgba(13, 110, 253, 1)",
                    pointBackgroundColor: "rgba(13, 110, 253, 1)",
                    pointBorderColor: "#fff",
                    pointHoverBackgroundColor: "#fff",
                    pointHoverBorderColor: "rgba(13, 110, 253, 1)",
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: "Unique Visitors",
                    data: ' . json_encode($uniqueVisitors) . ',
                    backgroundColor: "rgba(25, 135, 84, 0.05)",
                    borderColor: "rgba(25, 135, 84, 1)",
                    pointBackgroundColor: "rgba(25, 135, 84, 1)",
                    pointBorderColor: "#fff",
                    pointHoverBackgroundColor: "#fff",
                    pointHoverBorderColor: "rgba(25, 135, 84, 1)",
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "top",
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            animation: {
                duration: 1000
            }
        }
    });
    ' : '') . '
    
    ' . (count($referrerLabels) > 0 ? '
    // Referrer Chart
    const referrerChart = document.getElementById("referrerChart").getContext("2d");
    
    new Chart(referrerChart, {
        type: "doughnut",
        data: {
            labels: ' . json_encode($referrerLabels) . ',
            datasets: [
                {
                    data: ' . json_encode($referrerData) . ',
                    backgroundColor: [
                        "rgba(13, 110, 253, 0.8)",
                        "rgba(25, 135, 84, 0.8)",
                        "rgba(220, 53, 69, 0.8)",
                        "rgba(255, 193, 7, 0.8)",
                        "rgba(111, 66, 193, 0.8)",
                        "rgba(23, 162, 184, 0.8)",
                        "rgba(108, 117, 125, 0.8)",
                        "rgba(40, 167, 69, 0.8)"
                    ],
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "right",
                }
            },
            cutout: "70%",
            animation: {
                animateRotate: true,
                animateScale: true
            }
        }
    });
    ' : '') . '
    
    // Add event listeners to refresh buttons
    document.querySelectorAll(".refresh-btn").forEach(button => {
        button.addEventListener("click", function() {
            const chartId = this.getAttribute("data-chart");
            const section = this.getAttribute("data-section");
            
            // Add spinner to the refresh icon
            this.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i>";
            
            // Refresh the page after a short delay
            setTimeout(() => {
                refreshPage();
            }, 300);
        });
    });
    
    // Add event listener to the refresh data button
    document.getElementById("refresh-data").addEventListener("click", function() {
        this.innerHTML = "<i class=\"fas fa-spinner fa-spin me-1\"></i> Refreshing...";
        refreshPage();
    });
    
    // Function to refresh the page with cache busting
    function refreshPage() {
        showLoading();
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set("t", Date.now());  // Add timestamp to bust cache
        window.location.href = currentUrl.toString();
    }
    
    // Check if the page was just refreshed and hide the loading overlay
    window.onload = function() {
        hideLoading();
    };
</script>
';

// Include admin header
include_once '../includes/header.php';
?>

<?php if (isset($analyticsData['error'])): ?>
<div class="alert alert-warning">
    <h5><i class="fas fa-exclamation-triangle me-2"></i> Analytics Error</h5>
    <p>There was an error processing your analytics data. This might be due to a database issue or missing tables.</p>
    <p><strong>Error details:</strong> <?php echo htmlspecialchars($analyticsData['error']); ?></p>
    <p>Try refreshing the page or check your database configuration.</p>
</div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="row mt-4">
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title text-muted">Total Visits</h6>
                <div class="stat-value text-primary"><?php echo number_format($analyticsData['visits']['total_visits']); ?></div>
                <div class="stat-label"><?php echo ucfirst($period); ?> total</div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title text-muted">Unique Visitors</h6>
                <div class="stat-value text-success"><?php echo number_format($analyticsData['visits']['unique_visitors']); ?></div>
                <div class="stat-label"><?php echo ucfirst($period); ?> total</div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title text-muted">Whatnot Clicks</h6>
                <div class="stat-value text-danger"><?php echo number_format($analyticsData['whatnot_clicks']); ?></div>
                <div class="stat-label"><?php echo ucfirst($period); ?> total</div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title text-muted">eBay Clicks</h6>
                <div class="stat-value text-warning"><?php echo number_format($analyticsData['ebay_clicks']); ?></div>
                <div class="stat-label"><?php echo ucfirst($period); ?> total</div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <!-- Visitor Trend Chart -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Visitor Trend</h5>
                <span class="refresh-btn" data-chart="visitorTrendChart">
                    <i class="fas fa-sync-alt"></i>
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($dates)): ?>
                <div class="alert alert-info">
                    <p><strong>No visitor data available</strong> for the selected period. Data will appear here as visitors browse your site.</p>
                    <p>If you believe this is an error, try:</p>
                    <ol>
                        <li>Checking that your tracking code is correctly implemented</li>
                        <li>Verifying that the visits table exists in your database</li>
                        <li>Trying a different time period</li>
                    </ol>
                </div>
                <?php else: ?>
                <div class="chart-container">
                    <canvas id="visitorTrendChart"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Referrer Chart -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Traffic Sources</h5>
                <span class="refresh-btn" data-chart="referrerChart">
                    <i class="fas fa-sync-alt"></i>
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($referrerLabels)): ?>
                <div class="alert alert-info">
                    <p><strong>No referrer data available</strong> for the selected period. Data will appear here as visitors arrive from different sources.</p>
                </div>
                <?php else: ?>
                <div class="chart-container">
                    <canvas id="referrerChart"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- User Behavior Stats -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">User Behavior</h5>
                <span class="refresh-btn" data-section="behavior">
                    <i class="fas fa-sync-alt"></i>
                </span>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h6 class="small fw-bold">Pages Per Visit <span class="float-end"><?php echo number_format($analyticsData['visits']['avg_pages'], 1); ?></span></h6>
                    <div class="progress mb-4">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(100, $analyticsData['visits']['avg_pages'] * 25); ?>%" aria-valuenow="<?php echo $analyticsData['visits']['avg_pages']; ?>" aria-valuemin="0" aria-valuemax="10"></div>
                    </div>
                    
                    <h6 class="small fw-bold">Avg. Time on Site <span class="float-end"><?php echo formatTimeDuration($analyticsData['visits']['avg_time']); ?></span></h6>
                    <div class="progress mb-4">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, ($analyticsData['visits']['avg_time'] / 300) * 100); ?>%" aria-valuenow="<?php echo $analyticsData['visits']['avg_time']; ?>" aria-valuemin="0" aria-valuemax="300"></div>
                    </div>
                    
                    <h6 class="small fw-bold">Conversion Rate (Clicks/Visits) <span class="float-end"><?php echo number_format(($analyticsData['ebay_clicks'] + $analyticsData['whatnot_clicks']) / max(1, $analyticsData['visits']['total_visits']) * 100, 1); ?>%</span></h6>
                    <div class="progress mb-4">
                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo min(100, (($analyticsData['ebay_clicks'] + $analyticsData['whatnot_clicks']) / max(1, $analyticsData['visits']['total_visits']) * 100) * 2); ?>%" aria-valuenow="<?php echo ($analyticsData['ebay_clicks'] + $analyticsData['whatnot_clicks']) / max(1, $analyticsData['visits']['total_visits']) * 100; ?>" aria-valuemin="0" aria-valuemax="50"></div>
                    </div>
                </div>
                
                <p class="mb-0 text-muted">
                    <i class="fas fa-info-circle me-1"></i> 
                    These metrics help you understand how visitors interact with your site. Higher pages per visit and time on site generally indicate more engaged users.
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="list-group mb-4">
                    <a href="/admin/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Go to Dashboard
                        <span class="badge bg-primary rounded-pill"><i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="/admin/whatnot/settings.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Update Whatnot Settings
                        <span class="badge bg-primary rounded-pill"><i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="/admin/blog/list.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Manage Blog Posts
                        <span class="badge bg-primary rounded-pill"><i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank">
                        View Website
                        <span class="badge bg-primary rounded-pill"><i class="fas fa-external-link-alt"></i></span>
                    </a>
                </div>
                
                <div class="alert alert-info">
                    <h6 class="alert-heading">Analytics Tips</h6>
                    <p class="mb-0">Track which eBay listings and Whatnot streams get the most clicks to optimize your content strategy. Higher engagement typically leads to more sales.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['debug']) && $_GET['debug'] == 1 && isset($analyticsData['debug'])): ?>
<!-- Debug Information (Only visible with ?debug=1) -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Debug Information</h5>
    </div>
    <div class="card-body">
        <pre class="mb-0"><?php print_r($analyticsData['debug']); ?></pre>
    </div>
</div>
<?php endif; ?>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>