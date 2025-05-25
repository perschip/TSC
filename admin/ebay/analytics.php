<?php
// admin/ebay/analytics.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Get date range from URL parameters
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$valid_periods = ['week', 'month', 'quarter', 'year'];
if (!in_array($period, $valid_periods)) {
    $period = 'month';
}

// Calculate date range
switch ($period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_label = 'Last 7 Days';
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
        break;
    case 'quarter':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $period_label = 'Last 90 Days';
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        $period_label = 'Last 365 Days';
        break;
}

// Get eBay listing analytics
function getEbayAnalytics($pdo, $start_date) {
    try {
        // Total listings stats
        $stats_query = "SELECT 
            COUNT(*) as total_listings,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_listings,
            COUNT(CASE WHEN is_featured = 1 THEN 1 END) as featured_listings,
            COUNT(CASE WHEN end_time > NOW() THEN 1 END) as live_listings,
            AVG(current_price) as avg_price,
            MAX(current_price) as max_price,
            MIN(current_price) as min_price,
            SUM(watch_count) as total_watchers,
            AVG(watch_count) as avg_watchers
        FROM ebay_listings 
        WHERE created_at >= :start_date";
        
        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->execute([':start_date' => $start_date]);
        $stats = $stats_stmt->fetch();
        
        // Click tracking (from ebay_clicks table)
        $clicks_query = "SELECT 
            COUNT(*) as total_clicks,
            COUNT(DISTINCT listing_id) as listings_clicked,
            COUNT(DISTINCT visitor_ip) as unique_visitors
        FROM ebay_clicks 
        WHERE click_date >= :start_date";
        
        $clicks_stmt = $pdo->prepare($clicks_query);
        $clicks_stmt->execute([':start_date' => $start_date]);
        $clicks = $clicks_stmt->fetch();
        
        // Top performing listings by clicks
        $top_listings_query = "SELECT 
            l.title, 
            l.current_price,
            l.view_item_url,
            l.gallery_url,
            COUNT(c.id) as click_count,
            l.watch_count
        FROM ebay_listings l
        LEFT JOIN ebay_clicks c ON l.item_id = c.listing_id AND c.click_date >= :start_date
        WHERE l.is_active = 1
        GROUP BY l.id
        ORDER BY click_count DESC, l.watch_count DESC
        LIMIT 10";
        
        $top_stmt = $pdo->prepare($top_listings_query);
        $top_stmt->execute([':start_date' => $start_date]);
        $top_listings = $top_stmt->fetchAll();
        
        // Category performance
        $category_query = "SELECT 
            l.category_name,
            COUNT(l.id) as listing_count,
            COUNT(c.id) as click_count,
            AVG(l.current_price) as avg_price,
            SUM(l.watch_count) as total_watchers
        FROM ebay_listings l
        LEFT JOIN ebay_clicks c ON l.item_id = c.listing_id AND c.click_date >= :start_date
        WHERE l.category_name IS NOT NULL AND l.is_active = 1
        GROUP BY l.category_name
        ORDER BY click_count DESC, listing_count DESC
        LIMIT 8";
        
        $category_stmt = $pdo->prepare($category_query);
        $category_stmt->execute([':start_date' => $start_date]);
        $categories = $category_stmt->fetchAll();
        
        // Daily clicks trend
        $daily_query = "SELECT 
            DATE(click_date) as date,
            COUNT(*) as clicks,
            COUNT(DISTINCT visitor_ip) as unique_visitors
        FROM ebay_clicks 
        WHERE click_date >= :start_date
        GROUP BY DATE(click_date)
        ORDER BY date ASC";
        
        $daily_stmt = $pdo->prepare($daily_query);
        $daily_stmt->execute([':start_date' => $start_date]);
        $daily_data = $daily_stmt->fetchAll();
        
        // Price distribution
        $price_ranges = [
            '$0-$10' => [0, 10],
            '$10-$25' => [10, 25],
            '$25-$50' => [25, 50],
            '$50-$100' => [50, 100],
            '$100-$250' => [100, 250],
            '$250+' => [250, 999999]
        ];
        
        $price_distribution = [];
        foreach ($price_ranges as $range => $limits) {
            $price_query = "SELECT COUNT(*) as count FROM ebay_listings 
                           WHERE current_price >= :min AND current_price < :max AND is_active = 1";
            $price_stmt = $pdo->prepare($price_query);
            $price_stmt->execute([':min' => $limits[0], ':max' => $limits[1]]);
            $count = $price_stmt->fetch()['count'];
            $price_distribution[$range] = $count;
        }
        
        return [
            'stats' => $stats,
            'clicks' => $clicks,
            'top_listings' => $top_listings,
            'categories' => $categories,
            'daily_data' => $daily_data,
            'price_distribution' => $price_distribution
        ];
        
    } catch (PDOException $e) {
        error_log('eBay analytics error: ' . $e->getMessage());
        return [
            'stats' => ['total_listings' => 0, 'active_listings' => 0, 'featured_listings' => 0, 
                       'live_listings' => 0, 'avg_price' => 0, 'max_price' => 0, 'min_price' => 0,
                       'total_watchers' => 0, 'avg_watchers' => 0],
            'clicks' => ['total_clicks' => 0, 'listings_clicked' => 0, 'unique_visitors' => 0],
            'top_listings' => [],
            'categories' => [],
            'daily_data' => [],
            'price_distribution' => []
        ];
    }
}

$analytics = getEbayAnalytics($pdo, $start_date);

// Prepare chart data
$daily_dates = [];
$daily_clicks = [];
$daily_visitors = [];

foreach ($analytics['daily_data'] as $day) {
    $daily_dates[] = date('M j', strtotime($day['date']));
    $daily_clicks[] = (int)$day['clicks'];
    $daily_visitors[] = (int)$day['unique_visitors'];
}

$category_labels = array_column($analytics['categories'], 'category_name');
$category_clicks = array_column($analytics['categories'], 'click_count');

$price_labels = array_keys($analytics['price_distribution']);
$price_counts = array_values($analytics['price_distribution']);

// Calculate conversion rate
$conversion_rate = $analytics['stats']['total_listings'] > 0 ? 
    ($analytics['clicks']['total_clicks'] / $analytics['stats']['total_listings']) * 100 : 0;

// Page variables
$page_title = 'eBay Analytics';
$use_charts = true;

$header_actions = '
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <a href="?period=week" class="btn btn-sm btn-outline-secondary ' . ($period === 'week' ? 'active' : '') . '">Week</a>
        <a href="?period=month" class="btn btn-sm btn-outline-secondary ' . ($period === 'month' ? 'active' : '') . '">Month</a>
        <a href="?period=quarter" class="btn btn-sm btn-outline-secondary ' . ($period === 'quarter' ? 'active' : '') . '">Quarter</a>
        <a href="?period=year" class="btn btn-sm btn-outline-secondary ' . ($period === 'year' ? 'active' : '') . '">Year</a>
    </div>
    <a href="listings.php" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-list me-1"></i> View Listings
    </a>
</div>
';

$extra_scripts = '
<script>
// Daily clicks chart
const dailyChart = document.getElementById("dailyChart");
if (dailyChart && ' . json_encode($daily_dates) . '.length > 0) {
    new Chart(dailyChart, {
        type: "line",
        data: {
            labels: ' . json_encode($daily_dates) . ',
            datasets: [{
                label: "Clicks",
                data: ' . json_encode($daily_clicks) . ',
                backgroundColor: "rgba(13, 110, 253, 0.1)",
                borderColor: "rgba(13, 110, 253, 1)",
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }, {
                label: "Unique Visitors",
                data: ' . json_encode($daily_visitors) . ',
                backgroundColor: "rgba(25, 135, 84, 0.1)",
                borderColor: "rgba(25, 135, 84, 1)",
                borderWidth: 2,
                tension: 0.4,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                legend: { position: "top" }
            }
        }
    });
}

// Category performance chart
const categoryChart = document.getElementById("categoryChart");
if (categoryChart && ' . json_encode($category_labels) . '.length > 0) {
    new Chart(categoryChart, {
        type: "doughnut",
        data: {
            labels: ' . json_encode($category_labels) . ',
            datasets: [{
                data: ' . json_encode($category_clicks) . ',
                backgroundColor: [
                    "rgba(13, 110, 253, 0.8)",
                    "rgba(25, 135, 84, 0.8)",
                    "rgba(220, 53, 69, 0.8)",
                    "rgba(255, 193, 7, 0.8)",
                    "rgba(111, 66, 193, 0.8)",
                    "rgba(23, 162, 184, 0.8)",
                    "rgba(108, 117, 125, 0.8)",
                    "rgba(40, 167, 69, 0.8)"
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: "right" }
            }
        }
    });
}

// Price distribution chart
const priceChart = document.getElementById("priceChart");
if (priceChart && ' . json_encode($price_labels) . '.length > 0) {
    new Chart(priceChart, {
        type: "bar",
        data: {
            labels: ' . json_encode($price_labels) . ',
            datasets: [{
                label: "Listings",
                data: ' . json_encode($price_counts) . ',
                backgroundColor: "rgba(255, 193, 7, 0.8)",
                borderColor: "rgba(255, 193, 7, 1)",
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}
</script>';

// Include admin header
include_once '../includes/header.php';
?>

<!-- Stats Overview -->
<div class="row mt-4">
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title text-muted">Total Listings</h6>
                <div class="stat-value text-primary"><?php echo number_format($analytics['stats']['total_listings']); ?></div>
                <div class="stat-label"><?php echo $analytics['stats']['active_listings']; ?> active</div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title text-muted">Total Clicks</h6>
                <div class="stat-value text-success"><?php echo number_format($analytics['clicks']['total_clicks']); ?></div>
                <div class="stat-label"><?php echo $analytics['clicks']['unique_visitors']; ?> unique visitors</div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title text-muted">Avg Price</h6>
                <div class="stat-value text-warning">$<?php echo number_format($analytics['stats']['avg_price'], 2); ?></div>
                <div class="stat-label">Range: $<?php echo number_format($analytics['stats']['min_price'], 2); ?> - $<?php echo number_format($analytics['stats']['max_price'], 2); ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title text-muted">Total Watchers</h6>
                <div class="stat-value text-info"><?php echo number_format($analytics['stats']['total_watchers']); ?></div>
                <div class="stat-label"><?php echo number_format($analytics['stats']['avg_watchers'], 1); ?> avg per listing</div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <!-- Daily Activity Chart -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Daily Activity - <?php echo $period_label; ?></h5>
            </div>
            <div class="card-body">
                <?php if (empty($analytics['daily_data'])): ?>
                    <div class="alert alert-info">
                        <p>No click data available for the selected period.</p>
                        <p>Make sure your click tracking is working and visitors are clicking on your eBay listings.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="dailyChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Performance -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Category Performance</h5>
            </div>
            <div class="card-body">
                <?php if (empty($analytics['categories'])): ?>
                    <div class="alert alert-info">
                        <p>No category data available.</p>
                        <p>Sync your eBay listings to see category performance.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Performance Tables Row -->
<div class="row">
    <!-- Top Performing Listings -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Top Performing Listings</h5>
                <a href="listings.php?sort=watch_count&order=desc" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($analytics['top_listings'])): ?>
                    <div class="alert alert-info">
                        No performance data available. Make sure your eBay listings are synced and click tracking is enabled.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Listing</th>
                                    <th>Price</th>
                                    <th>Clicks</th>
                                    <th>Watchers</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics['top_listings'] as $listing): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($listing['gallery_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($listing['gallery_url']); ?>" 
                                                         alt="Item" class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars(substr($listing['title'], 0, 50)); ?>...</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="fw-bold text-success">$<?php echo number_format($listing['current_price'], 2); ?></td>
                                        <td>
                                            <?php if ($listing['click_count'] > 0): ?>
                                                <span class="badge bg-primary"><?php echo $listing['click_count']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($listing['watch_count'] > 0): ?>
                                                <span class="badge bg-info"><?php echo $listing['watch_count']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($listing['view_item_url']); ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Price Distribution -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Price Distribution</h5>
            </div>
            <div class="card-body">
                <?php if (array_sum($analytics['price_distribution']) === 0): ?>
                    <div class="alert alert-info">
                        No price data available. Sync your eBay listings to see price distribution.
                    </div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="priceChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Category Performance Table -->
<?php if (!empty($analytics['categories'])): ?>
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Category Performance Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Listings</th>
                                <th>Clicks</th>
                                <th>Avg Price</th>
                                <th>Total Watchers</th>
                                <th>Click Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['categories'] as $category): 
                                $click_rate = $category['listing_count'] > 0 ? 
                                    ($category['click_count'] / $category['listing_count']) * 100 : 0;
                            ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($category['category_name']); ?></td>
                                    <td><?php echo number_format($category['listing_count']); ?></td>
                                    <td>
                                        <?php if ($category['click_count'] > 0): ?>
                                            <span class="badge bg-primary"><?php echo $category['click_count']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-success">$<?php echo number_format($category['avg_price'], 2); ?></td>
                                    <td><?php echo number_format($category['total_watchers']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress me-2" style="width: 60px; height: 8px;">
                                                <div class="progress-bar" style="width: <?php echo min(100, $click_rate * 2); ?>%"></div>
                                            </div>
                                            <small><?php echo number_format($click_rate, 1); ?>%</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Key Metrics Cards -->
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h5 class="card-title text-primary">Conversion Rate</h5>
                <div class="stat-value text-primary"><?php echo number_format($conversion_rate, 2); ?>%</div>
                <div class="stat-label">Clicks per listing</div>
                <small class="text-muted">Higher is better - shows engagement</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card border-success">
            <div class="card-body text-center">
                <h5 class="card-title text-success">Featured Listings</h5>
                <div class="stat-value text-success"><?php echo number_format($analytics['stats']['featured_listings']); ?></div>
                <div class="stat-label">Out of <?php echo number_format($analytics['stats']['total_listings']); ?> total</div>
                <small class="text-muted">Featured listings get more visibility</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h5 class="card-title text-warning">Live Listings</h5>
                <div class="stat-value text-warning"><?php echo number_format($analytics['stats']['live_listings']); ?></div>
                <div class="stat-label">Currently active auctions</div>
                <small class="text-muted">Listings that haven't ended yet</small>
            </div>
        </div>
    </div>
</div>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>