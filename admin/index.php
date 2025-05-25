<?php
// Include database connection and auth check
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Get visitor statistics
function getVisitorStats($pdo, $period = 'week') {
    try {
        return safeGetVisitorStats($pdo, $period);
    } catch (Exception $e) {
        error_log('Error in getVisitorStats: ' . $e->getMessage());
        return [
            'total_visits' => 0,
            'unique_visitors' => 0,
            'avg_pages' => 0,
            'avg_time' => 0,
            'whatnot_clicks' => 0,
            'ebay_clicks' => 0
        ];
    }
}

// Get monthly visitor data for chart
function getMonthlyVisitorData($pdo) {
    try {
        return safeGetMonthlyVisitorData($pdo);
    } catch (Exception $e) {
        error_log('Error in getMonthlyVisitorData: ' . $e->getMessage());
        return [];
    }
}

// Get Whatnot status
function getWhatnotStatus($pdo) {
    try {
        $query = "SELECT 
                    is_live, 
                    stream_title,
                    stream_url,
                    scheduled_time,
                    last_checked
                  FROM whatnot_status 
                  ORDER BY id DESC 
                  LIMIT 1";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error in getWhatnotStatus: ' . $e->getMessage());
        return null;
    }
}

// Check if period filter was applied
$period = isset($_GET['period']) ? $_GET['period'] : 'week';
$validPeriods = ['day', 'week', 'month', 'year'];
if (!in_array($period, $validPeriods)) {
    $period = 'week';
}

// Get dashboard data with error handling
try {
    $visitorStats = getVisitorStats($pdo, $period);
    $whatnotStatus = getWhatnotStatus($pdo);
    $monthlyData = getMonthlyVisitorData($pdo);
    
    // Format chart data
    $chartLabels = getLastSixMonths();
    $chartVisits = [];
    $chartUnique = [];
    
    foreach ($chartLabels as $month) {
        $found = false;
        foreach ($monthlyData as $data) {
            if (isset($data['month']) && $data['month'] === $month) {
                $chartVisits[] = $data['visits'];
                $chartUnique[] = $data['unique_visitors'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chartVisits[] = 0;
            $chartUnique[] = 0;
        }
    }
    
    $chartLabelsJSON = json_encode($chartLabels);
    $chartVisitsJSON = json_encode($chartVisits);
    $chartUniqueJSON = json_encode($chartUnique);
} catch (Exception $e) {
    // Default values if anything fails
    $visitorStats = [
        'total_visits' => 0,
        'unique_visitors' => 0,
        'avg_pages' => 0,
        'avg_time' => 0,
        'whatnot_clicks' => 0,
        'ebay_clicks' => 0
    ];
    $whatnotStatus = null;
    $chartLabelsJSON = json_encode(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']);
    $chartVisitsJSON = json_encode([0, 0, 0, 0, 0, 0]);
    $chartUniqueJSON = json_encode([0, 0, 0, 0, 0, 0]);
}

// Page variables
$page_title = 'Dashboard';
$use_charts = true;

// Header actions for period filtering
$header_actions = '
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <a href="?period=day" class="btn btn-sm btn-outline-secondary ' . ($period === 'day' ? 'active' : '') . '">Day</a>
        <a href="?period=week" class="btn btn-sm btn-outline-secondary ' . ($period === 'week' ? 'active' : '') . '">Week</a>
        <a href="?period=month" class="btn btn-sm btn-outline-secondary ' . ($period === 'month' ? 'active' : '') . '">Month</a>
        <a href="?period=year" class="btn btn-sm btn-outline-secondary ' . ($period === 'year' ? 'active' : '') . '">Year</a>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="refresh-data">
        <i class="fas fa-sync-alt me-1"></i> Refresh Data
    </button>
</div>
';

// Extra scripts for charts
$extra_scripts = '<script>
    // Visitors Chart
    const visitorsChart = document.getElementById("visitorsChart").getContext("2d");
    
    new Chart(visitorsChart, {
        type: "line",
        data: {
            labels: ' . $chartLabelsJSON . ',
            datasets: [
                {
                    label: "Total Visits",
                    data: ' . $chartVisitsJSON . ',
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
                    data: ' . $chartUniqueJSON . ',
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
    
    // Add refresh functionality
    document.getElementById("refresh-data").addEventListener("click", function() {
        this.innerHTML = "<i class=\"fas fa-spinner fa-spin me-1\"></i> Refreshing...";
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set("t", Date.now());  // Add timestamp to bust cache
        window.location.href = currentUrl.toString();
    });
</script>';

// Include admin header
include_once 'includes/header.php';
?>

<!-- Stats Overview -->
<div class="row mt-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="stat-value text-primary"><?php echo number_format($visitorStats['unique_visitors']); ?></div>
                        <div class="stat-label">Unique Visitors</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users stat-icon visitors-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="stat-value text-success"><?php echo number_format($visitorStats['total_visits']); ?></div>
                        <div class="stat-label">Total Visits</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line stat-icon views-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="stat-value text-danger"><?php echo number_format($visitorStats['whatnot_clicks']); ?></div>
                        <div class="stat-label">Whatnot Clicks</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-video stat-icon whatnot-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="stat-value text-warning"><?php echo number_format($visitorStats['ebay_clicks']); ?></div>
                        <div class="stat-label">eBay Clicks</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tag stat-icon ebay-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Tables Row -->
<div class="row">
    <!-- Visitor Chart -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold">Visitor Overview</h6>
                <span class="refresh-btn" data-chart="visitorTrendChart">
                    <i class="fas fa-sync-alt"></i>
                </span>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="visitorsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Whatnot Status Card -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Whatnot Status</h6>
            </div>
            <div class="card-body">
                <?php if ($whatnotStatus): ?>
                    <?php if ($whatnotStatus['is_live']): ?>
                        <div class="d-flex align-items-center mb-3">
                            <span class="whatnot-status-indicator status-live me-2"></span>
                            <span class="fw-bold">LIVE NOW</span>
                            <span class="ms-auto small text-muted">Last checked: <?php echo date('M j, Y g:i A', strtotime($whatnotStatus['last_checked'])); ?></span>
                        </div>
                        <h5><?php echo htmlspecialchars($whatnotStatus['stream_title']); ?></h5>
                        <a href="<?php echo htmlspecialchars($whatnotStatus['stream_url']); ?>" target="_blank" class="btn btn-success btn-sm mt-2">
                            <i class="fas fa-external-link-alt me-1"></i> View Stream
                        </a>
                    <?php elseif ($whatnotStatus['scheduled_time'] && strtotime($whatnotStatus['scheduled_time']) > time()): ?>
                        <div class="d-flex align-items-center mb-3">
                            <span class="whatnot-status-indicator status-upcoming me-2"></span>
                            <span class="fw-bold">UPCOMING</span>
                            <span class="ms-auto small text-muted">Last checked: <?php echo date('M j, Y g:i A', strtotime($whatnotStatus['last_checked'])); ?></span>
                        </div>
                        <h5><?php echo htmlspecialchars($whatnotStatus['stream_title']); ?></h5>
                        <p>Scheduled for: <?php echo date('M j, Y g:i A', strtotime($whatnotStatus['scheduled_time'])); ?></p>
                        <a href="/admin/whatnot/settings.php" class="btn btn-primary btn-sm mt-2">
                            <i class="fas fa-cog me-1"></i> Update Stream Info
                        </a>
                    <?php else: ?>
                        <div class="d-flex align-items-center mb-3">
                            <span class="whatnot-status-indicator status-offline me-2"></span>
                            <span class="fw-bold">OFFLINE</span>
                            <span class="ms-auto small text-muted">Last checked: <?php echo date('M j, Y g:i A', strtotime($whatnotStatus['last_checked'])); ?></span>
                        </div>
                        <p>No active or upcoming streams.</p>
                        <a href="/admin/whatnot/settings.php" class="btn btn-primary btn-sm mt-2">
                            <i class="fas fa-plus me-1"></i> Schedule Stream
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Whatnot integration not configured.</p>
                    <a href="/admin/whatnot/settings.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-cog me-1"></i> Configure Whatnot
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- User Behavior Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">User Behavior</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="small fw-bold">Pages Per Visit <span class="float-end"><?php echo number_format($visitorStats['avg_pages'], 1); ?></span></h6>
                    <div class="progress">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(100, $visitorStats['avg_pages'] * 25); ?>%" aria-valuenow="<?php echo $visitorStats['avg_pages']; ?>" aria-valuemin="0" aria-valuemax="10"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <h6 class="small fw-bold">Avg. Time on Site <span class="float-end"><?php echo formatTimeDuration($visitorStats['avg_time']); ?></span></h6>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, ($visitorStats['avg_time'] / 300) * 100); ?>%" aria-valuenow="<?php echo $visitorStats['avg_time']; ?>" aria-valuemin="0" aria-valuemax="300"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Third Row -->
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Website Status</h6>
                <a href="/" class="btn btn-sm btn-outline-primary" target="_blank">View Site</a>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h6>Quick Links</h6>
                    <div class="list-group">
                        <a href="/admin/whatnot/settings.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            Whatnot Settings
                            <span class="badge bg-primary rounded-pill"><i class="fas fa-arrow-right"></i></span>
                        </a>
                        <a href="/admin/analytics/dashboard.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            Analytics Dashboard
                            <span class="badge bg-primary rounded-pill"><i class="fas fa-arrow-right"></i></span>
                        </a>
                        <a href="/admin/blog/list.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            Manage Blog Posts
                            <span class="badge bg-primary rounded-pill"><i class="fas fa-arrow-right"></i></span>
                        </a>
                        <a href="/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" target="_blank">
                            Homepage
                            <span class="badge bg-primary rounded-pill"><i class="fas fa-external-link-alt"></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">eBay Listing Clicks</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php
                    try {
                        $ebay_query = "SELECT listing_id, COUNT(*) as clicks, MAX(click_date) as last_click 
                                    FROM ebay_clicks 
                                    GROUP BY listing_id 
                                    ORDER BY clicks DESC 
                                    LIMIT 4";
                        $ebay_stmt = $pdo->prepare($ebay_query);
                        $ebay_stmt->execute();
                        $ebay_clicks = $ebay_stmt->fetchAll();
                    } catch (PDOException $e) {
                        $ebay_clicks = [];
                    }
                    
                    if (!empty($ebay_clicks)):
                    ?>
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Listing ID</th>
                                <th>Clicks</th>
                                <th>Last Click</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ebay_clicks as $click): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($click['listing_id']); ?></td>
                                <td><?php echo number_format($click['clicks']); ?></td>
                                <td><?php echo date('M j, g:i A', strtotime($click['last_click'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="alert alert-info">
                        No eBay clicks recorded yet. Data will appear here when users click on your eBay listings.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include admin footer
include_once 'includes/footer.php'; 
?>