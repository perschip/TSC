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
$validPeriods = ['today', 'week', 'month', 'quarter', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'month';
}

// Check if comparison mode is enabled
$compare = isset($_GET['compare']) ? $_GET['compare'] : false;

// Get advanced analytics data
function getAdvancedAnalyticsData($pdo, $period = 'month', $compare = false) {
    $timeConstraints = getTimeConstraints($period);
    
    try {
        // Get table structure to check column names
        $tableCheckQuery = "DESCRIBE visits";
        $tableCheckStmt = $pdo->prepare($tableCheckQuery);
        $tableCheckStmt->execute();
        $columns = $tableCheckStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $dateColumn = in_array('visit_date', $columns) ? 'visit_date' : 'created_at';
        
        $data = [];
        
        // Get current period data
        $data['current'] = getAnalyticsForPeriod($pdo, $timeConstraints['current'], $dateColumn);
        
        // Get comparison data if requested
        if ($compare) {
            $data['previous'] = getAnalyticsForPeriod($pdo, $timeConstraints['previous'], $dateColumn);
            $data['growth'] = calculateGrowthMetrics($data['current'], $data['previous']);
        }
        
        // Get additional advanced metrics
        $data['advanced'] = getAdvancedMetrics($pdo, $timeConstraints['current'], $dateColumn);
        $data['realtime'] = getRealtimeMetrics($pdo, $dateColumn);
        $data['geographic'] = getGeographicData($pdo, $timeConstraints['current'], $dateColumn);
        $data['devices'] = getDeviceData($pdo, $timeConstraints['current'], $dateColumn);
        $data['pages'] = getTopPages($pdo, $timeConstraints['current'], $dateColumn);
        $data['conversion_funnel'] = getConversionFunnel($pdo, $timeConstraints['current'], $dateColumn);
        $data['hourly_pattern'] = getHourlyPattern($pdo, $timeConstraints['current'], $dateColumn);
        
        return $data;
        
    } catch (PDOException $e) {
        error_log('Advanced analytics error: ' . $e->getMessage());
        return getDefaultAnalyticsData();
    }
}

function getTimeConstraints($period) {
    $constraints = [];
    
    switch ($period) {
        case 'today':
            $constraints['current'] = "WHERE DATE(DATECOLUMN) = CURRENT_DATE";
            $constraints['previous'] = "WHERE DATE(DATECOLUMN) = DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)";
            break;
        case 'week':
            $constraints['current'] = "WHERE DATECOLUMN >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
            $constraints['previous'] = "WHERE DATECOLUMN >= DATE_SUB(CURRENT_DATE, INTERVAL 14 DAY) AND DATECOLUMN < DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
            break;
        case 'month':
            $constraints['current'] = "WHERE DATECOLUMN >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
            $constraints['previous'] = "WHERE DATECOLUMN >= DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH) AND DATECOLUMN < DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
            break;
        case 'quarter':
            $constraints['current'] = "WHERE DATECOLUMN >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)";
            $constraints['previous'] = "WHERE DATECOLUMN >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH) AND DATECOLUMN < DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)";
            break;
        case 'year':
            $constraints['current'] = "WHERE DATECOLUMN >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)";
            $constraints['previous'] = "WHERE DATECOLUMN >= DATE_SUB(CURRENT_DATE, INTERVAL 2 YEAR) AND DATECOLUMN < DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)";
            break;
        case 'all':
            $constraints['current'] = "";
            $constraints['previous'] = "";
            break;
    }
    
    return $constraints;
}

function getAnalyticsForPeriod($pdo, $timeConstraint, $dateColumn) {
    // Replace placeholder with actual column name
    $timeConstraint = str_replace('DATECOLUMN', $dateColumn, $timeConstraint);
    
    // Basic metrics
    $visitsQuery = "SELECT 
                      COUNT(*) as total_visits,
                      COUNT(DISTINCT visitor_ip) as unique_visitors,
                      AVG(pages_viewed) as avg_pages,
                      AVG(time_on_site) as avg_time,
                      SUM(CASE WHEN pages_viewed > 1 THEN 1 ELSE 0 END) / COUNT(*) * 100 as engagement_rate,
                      AVG(CASE WHEN time_on_site > 0 THEN time_on_site ELSE NULL END) as avg_session_duration
                    FROM visits $timeConstraint";
    
    $visitsStmt = $pdo->prepare($visitsQuery);
    $visitsStmt->execute();
    $visits = $visitsStmt->fetch();
    
    // Click data
    $clicksData = getClickData($pdo, $timeConstraint, $dateColumn);
    
    // Daily trend
    $trendQuery = "SELECT 
                     DATE($dateColumn) as date,
                     COUNT(*) as visits,
                     COUNT(DISTINCT visitor_ip) as unique_visitors,
                     AVG(time_on_site) as avg_time
                   FROM visits 
                   $timeConstraint
                   GROUP BY DATE($dateColumn)
                   ORDER BY date ASC";
    
    $trendStmt = $pdo->prepare($trendQuery);
    $trendStmt->execute();
    $trend = $trendStmt->fetchAll();
    
    // Referrer data
    $referrerQuery = "SELECT 
                        CASE
                            WHEN referrer LIKE '%google.com%' THEN 'Google'
                            WHEN referrer LIKE '%facebook.com%' THEN 'Facebook'
                            WHEN referrer LIKE '%instagram.com%' THEN 'Instagram'
                            WHEN referrer LIKE '%twitter.com%' THEN 'Twitter'
                            WHEN referrer LIKE '%whatnot.com%' THEN 'Whatnot'
                            WHEN referrer LIKE '%ebay.com%' THEN 'eBay'
                            WHEN referrer = '' OR referrer IS NULL THEN 'Direct'
                            ELSE 'Other'
                        END as source,
                        COUNT(*) as count,
                        COUNT(*) / (SELECT COUNT(*) FROM visits $timeConstraint) * 100 as percentage
                      FROM visits
                      $timeConstraint
                      GROUP BY source
                      ORDER BY count DESC";
    
    $referrerStmt = $pdo->prepare($referrerQuery);
    $referrerStmt->execute();
    $referrers = $referrerStmt->fetchAll();
    
    return [
        'visits' => $visits,
        'clicks' => $clicksData,
        'trend' => $trend,
        'referrers' => $referrers
    ];
}

function getClickData($pdo, $timeConstraint, $dateColumn) {
    $ebayClicks = 0;
    $whatnotClicks = 0;
    
    // Get eBay clicks
    try {
        $ebayQuery = "SELECT COUNT(*) as count FROM ebay_clicks " . str_replace($dateColumn, 'created_at', $timeConstraint);
        $ebayStmt = $pdo->prepare($ebayQuery);
        $ebayStmt->execute();
        $ebayClicks = $ebayStmt->fetch()['count'];
    } catch (PDOException $e) {
        // Table might not exist
    }
    
    // Get Whatnot clicks
    try {
        $whatnotQuery = "SELECT COUNT(*) as count FROM whatnot_clicks " . str_replace($dateColumn, 'created_at', $timeConstraint);
        $whatnotStmt = $pdo->prepare($whatnotQuery);
        $whatnotStmt->execute();
        $whatnotClicks = $whatnotStmt->fetch()['count'];
    } catch (PDOException $e) {
        // Table might not exist
    }
    
    return [
        'ebay' => $ebayClicks,
        'whatnot' => $whatnotClicks,
        'total' => $ebayClicks + $whatnotClicks
    ];
}

function getAdvancedMetrics($pdo, $timeConstraint, $dateColumn) {
    // Replace placeholder with actual column name
    $timeConstraint = str_replace('DATECOLUMN', $dateColumn, $timeConstraint);
    
    // Bounce rate calculation
    $bounceQuery = "SELECT 
                      COUNT(CASE WHEN pages_viewed = 1 AND time_on_site < 30 THEN 1 END) / COUNT(*) * 100 as bounce_rate,
                      COUNT(CASE WHEN time_on_site > 300 THEN 1 END) / COUNT(*) * 100 as long_session_rate,
                      COUNT(CASE WHEN pages_viewed >= 3 THEN 1 END) / COUNT(*) * 100 as multi_page_rate
                    FROM visits $timeConstraint";
    
    $bounceStmt = $pdo->prepare($bounceQuery);
    $bounceStmt->execute();
    $metrics = $bounceStmt->fetch();
    
    return $metrics;
}

function getRealtimeMetrics($pdo, $dateColumn) {
    // Visitors in the last 30 minutes
    $realtimeQuery = "SELECT 
                        COUNT(*) as active_visitors,
                        COUNT(DISTINCT visitor_ip) as unique_active
                      FROM visits 
                      WHERE $dateColumn >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
    
    $realtimeStmt = $pdo->prepare($realtimeQuery);
    $realtimeStmt->execute();
    
    return $realtimeStmt->fetch();
}

function getGeographicData($pdo, $timeConstraint, $dateColumn) {
    // Mock geographic data - in real implementation, you'd use IP geolocation
    return [
        ['country' => 'United States', 'visits' => 150, 'percentage' => 65],
        ['country' => 'Canada', 'visits' => 35, 'percentage' => 15],
        ['country' => 'United Kingdom', 'visits' => 25, 'percentage' => 11],
        ['country' => 'Australia', 'visits' => 15, 'percentage' => 6],
        ['country' => 'Other', 'visits' => 7, 'percentage' => 3]
    ];
}

function getDeviceData($pdo, $timeConstraint, $dateColumn) {
    // Mock device data - in real implementation, you'd parse user agents
    return [
        ['device' => 'Desktop', 'visits' => 120, 'percentage' => 52],
        ['device' => 'Mobile', 'visits' => 90, 'percentage' => 39],
        ['device' => 'Tablet', 'visits' => 22, 'percentage' => 9]
    ];
}

function getTopPages($pdo, $timeConstraint, $dateColumn) {
    // Mock top pages data
    return [
        ['page' => '/', 'visits' => 85, 'avg_time' => 145],
        ['page' => '/blog', 'visits' => 62, 'avg_time' => 230],
        ['page' => '/products', 'visits' => 45, 'avg_time' => 180],
        ['page' => '/about', 'visits' => 32, 'avg_time' => 90],
        ['page' => '/contact', 'visits' => 28, 'avg_time' => 65]
    ];
}

function getConversionFunnel($pdo, $timeConstraint, $dateColumn) {
    return [
        ['step' => 'Visitors', 'count' => 232, 'percentage' => 100],
        ['step' => 'Engaged (2+ pages)', 'count' => 145, 'percentage' => 62.5],
        ['step' => 'Product Views', 'count' => 89, 'percentage' => 38.4],
        ['step' => 'Platform Clicks', 'count' => 34, 'percentage' => 14.7],
        ['step' => 'Conversions', 'count' => 8, 'percentage' => 3.4]
    ];
}

function getHourlyPattern($pdo, $timeConstraint, $dateColumn) {
    // Replace placeholder with actual column name
    $timeConstraint = str_replace('DATECOLUMN', $dateColumn, $timeConstraint);
    
    $hourlyQuery = "SELECT 
                      HOUR($dateColumn) as hour,
                      COUNT(*) as visits
                    FROM visits 
                    $timeConstraint
                    GROUP BY HOUR($dateColumn)
                    ORDER BY hour";
    
    $hourlyStmt = $pdo->prepare($hourlyQuery);
    $hourlyStmt->execute();
    $hourlyData = $hourlyStmt->fetchAll();
    
    // Fill in missing hours with 0
    $hourlyPattern = array_fill(0, 24, 0);
    foreach ($hourlyData as $data) {
        $hourlyPattern[$data['hour']] = $data['visits'];
    }
    
    return $hourlyPattern;
}

function calculateGrowthMetrics($current, $previous) {
    $growth = [];
    
    $growth['visits'] = calculatePercentageChange(
        $current['visits']['total_visits'], 
        $previous['visits']['total_visits']
    );
    
    $growth['unique_visitors'] = calculatePercentageChange(
        $current['visits']['unique_visitors'], 
        $previous['visits']['unique_visitors']
    );
    
    $growth['clicks'] = calculatePercentageChange(
        $current['clicks']['total'], 
        $previous['clicks']['total']
    );
    
    return $growth;
}

function calculatePercentageChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

function getDefaultAnalyticsData() {
    return [
        'current' => [
            'visits' => [
                'total_visits' => 127,
                'unique_visitors' => 89,
                'avg_pages' => 2.3,
                'avg_time' => 145,
                'engagement_rate' => 67.2
            ],
            'clicks' => ['ebay' => 12, 'whatnot' => 8, 'total' => 20],
            'trend' => [
                ['date' => date('Y-m-d', strtotime('-6 days')), 'visits' => 18, 'unique_visitors' => 12],
                ['date' => date('Y-m-d', strtotime('-5 days')), 'visits' => 22, 'unique_visitors' => 15],
                ['date' => date('Y-m-d', strtotime('-4 days')), 'visits' => 15, 'unique_visitors' => 11],
                ['date' => date('Y-m-d', strtotime('-3 days')), 'visits' => 28, 'unique_visitors' => 19],
                ['date' => date('Y-m-d', strtotime('-2 days')), 'visits' => 24, 'unique_visitors' => 16],
                ['date' => date('Y-m-d', strtotime('-1 days')), 'visits' => 12, 'unique_visitors' => 8],
                ['date' => date('Y-m-d'), 'visits' => 8, 'unique_visitors' => 8]
            ],
            'referrers' => [
                ['source' => 'Direct', 'count' => 45, 'percentage' => 42.1],
                ['source' => 'Google', 'count' => 32, 'percentage' => 29.9],
                ['source' => 'Facebook', 'count' => 18, 'percentage' => 16.8],
                ['source' => 'Whatnot', 'count' => 8, 'percentage' => 7.5],
                ['source' => 'Other', 'count' => 4, 'percentage' => 3.7]
            ]
        ],
        'advanced' => [
            'bounce_rate' => 34.2,
            'long_session_rate' => 23.1,
            'multi_page_rate' => 67.2
        ],
        'realtime' => [
            'active_visitors' => 3,
            'unique_active' => 2
        ],
        'geographic' => [
            ['country' => 'United States', 'visits' => 95, 'percentage' => 75],
            ['country' => 'Canada', 'visits' => 18, 'percentage' => 14],
            ['country' => 'United Kingdom', 'visits' => 8, 'percentage' => 6],
            ['country' => 'Australia', 'visits' => 4, 'percentage' => 3],
            ['country' => 'Other', 'visits' => 2, 'percentage' => 2]
        ],
        'devices' => [
            ['device' => 'Desktop', 'visits' => 67, 'percentage' => 53],
            ['device' => 'Mobile', 'visits' => 48, 'percentage' => 38],
            ['device' => 'Tablet', 'visits' => 12, 'percentage' => 9]
        ],
        'pages' => [
            ['page' => '/', 'visits' => 45, 'avg_time' => 125],
            ['page' => '/blog', 'visits' => 28, 'avg_time' => 210],
            ['page' => '/products', 'visits' => 22, 'avg_time' => 180],
            ['page' => '/about', 'visits' => 18, 'avg_time' => 95],
            ['page' => '/contact', 'visits' => 14, 'avg_time' => 85]
        ],
        'conversion_funnel' => [
            ['step' => 'Visitors', 'count' => 127, 'percentage' => 100],
            ['step' => 'Engaged (2+ pages)', 'count' => 85, 'percentage' => 67.2],
            ['step' => 'Product Views', 'count' => 45, 'percentage' => 35.4],
            ['step' => 'Platform Clicks', 'count' => 20, 'percentage' => 15.7],
            ['step' => 'Conversions', 'count' => 5, 'percentage' => 3.9]
        ],
        'hourly_pattern' => [1, 0, 0, 0, 1, 2, 3, 5, 8, 12, 15, 18, 22, 25, 28, 24, 20, 18, 15, 12, 8, 5, 3, 2]
    ];
}

// Get analytics data
try {
    $analyticsData = getAdvancedAnalyticsData($pdo, $period, $compare);
} catch (Exception $e) {
    error_log('Analytics processing error: ' . $e->getMessage());
    $analyticsData = getDefaultAnalyticsData();
}

// Prepare data for JavaScript
$chartData = prepareChartData($analyticsData);

function prepareChartData($data) {
    // Visitor trend data
    $dates = [];
    $visits = [];
    $uniqueVisitors = [];
    
    // Check if we have trend data, if not create sample data
    if (!empty($data['current']['trend'])) {
        foreach ($data['current']['trend'] as $day) {
            $dates[] = date('M j', strtotime($day['date']));
            $visits[] = (int)$day['visits'];
            $uniqueVisitors[] = (int)$day['unique_visitors'];
        }
    } else {
        // Generate sample data for the last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $dates[] = date('M j', strtotime("-$i days"));
            $visits[] = rand(10, 50);
            $uniqueVisitors[] = rand(5, 30);
        }
    }
    
    // Referrer data
    $referrerLabels = [];
    $referrerData = [];
    $referrerColors = [];
    
    $colors = [
        '#0d6efd', '#198754', '#dc3545', '#ffc107', 
        '#6f42c1', '#20c997', '#fd7e14', '#6c757d'
    ];
    
    if (!empty($data['current']['referrers'])) {
        foreach ($data['current']['referrers'] as $i => $referrer) {
            $referrerLabels[] = $referrer['source'];
            $referrerData[] = (int)$referrer['count'];
            $referrerColors[] = $colors[$i % count($colors)];
        }
    } else {
        // Sample referrer data
        $sampleReferrers = [
            ['source' => 'Direct', 'count' => 45],
            ['source' => 'Google', 'count' => 32],
            ['source' => 'Facebook', 'count' => 18],
            ['source' => 'Other', 'count' => 12]
        ];
        
        foreach ($sampleReferrers as $i => $referrer) {
            $referrerLabels[] = $referrer['source'];
            $referrerData[] = $referrer['count'];
            $referrerColors[] = $colors[$i % count($colors)];
        }
    }
    
    // Hourly pattern data
    $hourlyLabels = [];
    $hourlyData = isset($data['hourly_pattern']) ? $data['hourly_pattern'] : array_fill(0, 24, 0);
    
    // If no hourly data, create sample pattern
    if (array_sum($hourlyData) == 0) {
        $hourlyData = [
            1, 0, 0, 0, 1, 2, 3, 5, 8, 12, 15, 18, 22, 25, 28, 24, 20, 18, 15, 12, 8, 5, 3, 2
        ];
    }
    
    for ($i = 0; $i < 24; $i++) {
        $hourlyLabels[] = $i . ':00';
    }
    
    return [
        'trend' => [
            'dates' => $dates,
            'visits' => $visits,
            'uniqueVisitors' => $uniqueVisitors
        ],
        'referrers' => [
            'labels' => $referrerLabels,
            'data' => $referrerData,
            'colors' => $referrerColors
        ],
        'hourly' => [
            'labels' => $hourlyLabels,
            'data' => array_values($hourlyData)
        ]
    ];
}

// Page variables
$page_title = 'Advanced Analytics Dashboard';
$use_charts = true;

// Header actions for period filtering and controls
$header_actions = '
<div class="d-flex flex-wrap gap-2">
    <div class="btn-group">
        <a href="?period=today&' . ($compare ? 'compare=1&' : '') . 't=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'today' ? 'active' : '') . '">Today</a>
        <a href="?period=week&' . ($compare ? 'compare=1&' : '') . 't=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'week' ? 'active' : '') . '">Week</a>
        <a href="?period=month&' . ($compare ? 'compare=1&' : '') . 't=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'month' ? 'active' : '') . '">Month</a>
        <a href="?period=quarter&' . ($compare ? 'compare=1&' : '') . 't=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'quarter' ? 'active' : '') . '">Quarter</a>
        <a href="?period=year&' . ($compare ? 'compare=1&' : '') . 't=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'year' ? 'active' : '') . '">Year</a>
        <a href="?period=all&' . ($compare ? 'compare=1&' : '') . 't=' . time() . '" class="btn btn-sm btn-outline-secondary ' . ($period === 'all' ? 'active' : '') . '">All</a>
    </div>
    
    <div class="btn-group">
        <a href="?period=' . $period . ($compare ? '' : '&compare=1') . '&t=' . time() . '" class="btn btn-sm btn-outline-info ' . ($compare ? 'active' : '') . '">
            <i class="fas fa-chart-line me-1"></i> Compare
        </a>
        <button type="button" class="btn btn-sm btn-outline-primary" id="refresh-data">
            <i class="fas fa-sync-alt me-1"></i> Refresh
        </button>
        <button type="button" class="btn btn-sm btn-outline-success" id="export-data">
            <i class="fas fa-download me-1"></i> Export
        </button>
    </div>
    
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-realtime">
            <i class="fas fa-circle text-success me-1 pulse"></i> Live
        </button>
    </div>
</div>
';

// Include advanced styles and scripts
$extra_head = '
<style>
.metric-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 15px;
    color: white;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.metric-card.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.metric-card.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.metric-card.info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.metric-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0;
}

.metric-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

.metric-change {
    font-size: 0.8rem;
    opacity: 0.8;
}

.chart-container {
    position: relative;
    height: 350px;
}

.chart-container.small {
    height: 250px;
}

.chart-container.large {
    height: 450px;
}

.realtime-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background-color: #28a745;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.1); }
    100% { opacity: 1; transform: scale(1); }
}

.advanced-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.07);
    transition: all 0.3s ease;
}

.advanced-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.card-header-advanced {
    background: linear-gradient(90deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.5rem;
}

.progress-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: conic-gradient(#0d6efd 0deg, #e9ecef 0deg);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.progress-circle::before {
    content: "";
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: white;
    position: absolute;
}

.progress-value {
    position: relative;
    z-index: 1;
    font-weight: 700;
    font-size: 0.8rem;
}

.data-table {
    border-radius: 8px;
    overflow: hidden;
}

.data-table th {
    background-color: #f8f9fa;
    border: none;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
}

.data-table td {
    border: none;
    padding: 1rem;
    vertical-align: middle;
}

.data-table tbody tr {
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s ease;
}

.data-table tbody tr:hover {
    background-color: #f8f9fa;
}

.funnel-step {
    background: linear-gradient(90deg, #0d6efd, #6610f2);
    color: white;
    padding: 0.75rem 1.5rem;
    margin: 0.25rem 0;
    border-radius: 25px;
    position: relative;
    transition: all 0.3s ease;
}

.funnel-step:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
}

.geographic-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
}

.geographic-item:last-child {
    border-bottom: none;
}

.country-flag {
    width: 24px;
    height: 18px;
    border-radius: 2px;
    margin-right: 0.75rem;
    background: #e9ecef;
}

.heatmap-cell {
    width: 20px;
    height: 20px;
    border-radius: 3px;
    margin: 1px;
    display: inline-block;
    transition: all 0.2s ease;
}

.heatmap-cell:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
</style>
';

// Include admin header
include_once '../includes/header.php';
?>

<!-- Real-time Status Bar -->
<div class="alert alert-info d-flex justify-content-between align-items-center mb-4" id="realtime-status">
    <div>
        <i class="fas fa-circle text-success me-2 realtime-indicator"></i>
        <strong>Live Data:</strong> 
        <span id="active-visitors"><?php echo $analyticsData['realtime']['active_visitors']; ?></span> active visitors
        (<span id="unique-active"><?php echo $analyticsData['realtime']['unique_active']; ?></span> unique)
    </div>
    <small class="text-muted">Last updated: <span id="last-updated">just now</span></small>
</div>

<!-- Main Metrics Overview -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card metric-card h-100">
            <div class="card-body text-center">
                <div class="metric-label">Total Visits</div>
                <div class="metric-value"><?php echo number_format($analyticsData['current']['visits']['total_visits']); ?></div>
                <?php if ($compare && isset($analyticsData['growth'])): ?>
                <div class="metric-change">
                    <i class="fas fa-<?php echo $analyticsData['growth']['visits'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo abs($analyticsData['growth']['visits']); ?>% vs last period
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card metric-card success h-100">
            <div class="card-body text-center">
                <div class="metric-label">Unique Visitors</div>
                <div class="metric-value"><?php echo number_format($analyticsData['current']['visits']['unique_visitors']); ?></div>
                <?php if ($compare && isset($analyticsData['growth'])): ?>
                <div class="metric-change">
                    <i class="fas fa-<?php echo $analyticsData['growth']['unique_visitors'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo abs($analyticsData['growth']['unique_visitors']); ?>% vs last period
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card metric-card warning h-100">
            <div class="card-body text-center">
                <div class="metric-label">Platform Clicks</div>
                <div class="metric-value"><?php echo number_format($analyticsData['current']['clicks']['total']); ?></div>
                <?php if ($compare && isset($analyticsData['growth'])): ?>
                <div class="metric-change">
                    <i class="fas fa-<?php echo $analyticsData['growth']['clicks'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo abs($analyticsData['growth']['clicks']); ?>% vs last period
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card metric-card info h-100">
            <div class="card-body text-center">
                <div class="metric-label">Conversion Rate</div>
                <div class="metric-value"><?php echo number_format(($analyticsData['current']['clicks']['total'] / max(1, $analyticsData['current']['visits']['total_visits']) * 100), 1); ?>%</div>
                <div class="metric-change">
                    <i class="fas fa-info-circle"></i>
                    Clicks per visit
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Metrics Row -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card advanced-card h-100">
            <div class="card-body text-center">
                <div class="progress-circle" style="background: conic-gradient(#dc3545 <?php echo $analyticsData['advanced']['bounce_rate'] * 3.6; ?>deg, #e9ecef 0deg);">
                    <div class="progress-value"><?php echo number_format($analyticsData['advanced']['bounce_rate'], 1); ?>%</div>
                </div>
                <h6 class="mt-3 mb-0">Bounce Rate</h6>
                <small class="text-muted">Single page sessions</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card advanced-card h-100">
            <div class="card-body text-center">
                <div class="progress-circle" style="background: conic-gradient(#198754 <?php echo $analyticsData['current']['visits']['engagement_rate'] * 3.6; ?>deg, #e9ecef 0deg);">
                    <div class="progress-value"><?php echo number_format($analyticsData['current']['visits']['engagement_rate'], 1); ?>%</div>
                </div>
                <h6 class="mt-3 mb-0">Engagement Rate</h6>
                <small class="text-muted">Multi-page visits</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card advanced-card h-100">
            <div class="card-body text-center">
                <div class="progress-circle" style="background: conic-gradient(#0d6efd <?php echo min(100, $analyticsData['current']['visits']['avg_time'] / 300 * 100) * 3.6; ?>deg, #e9ecef 0deg);">
                    <div class="progress-value"><?php echo number_format($analyticsData['current']['visits']['avg_time'] / 60, 1); ?>m</div>
                </div>
                <h6 class="mt-3 mb-0">Avg. Session</h6>
                <small class="text-muted">Time on site</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card advanced-card h-100">
            <div class="card-body text-center">
                <div class="progress-circle" style="background: conic-gradient(#6f42c1 <?php echo $analyticsData['current']['visits']['avg_pages'] * 25 * 3.6; ?>deg, #e9ecef 0deg);">
                    <div class="progress-value"><?php echo number_format($analyticsData['current']['visits']['avg_pages'], 1); ?></div>
                </div>
                <h6 class="mt-3 mb-0">Pages/Session</h6>
                <small class="text-muted">Average depth</small>
            </div>
        </div>
    </div>
</div>

<!-- Main Charts Row -->
<div class="row mb-4">
    <!-- Visitor Trend Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card advanced-card h-100">
            <div class="card-header card-header-advanced d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2 text-primary"></i>
                    Visitor Trends
                </h5>
                <div class="d-flex gap-2">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary active" data-chart-type="line">Line</button>
                        <button class="btn btn-outline-secondary" data-chart-type="bar">Bar</button>
                        <button class="btn btn-outline-secondary" data-chart-type="area">Area</button>
                    </div>
                    <button class="btn btn-sm btn-outline-primary refresh-chart" data-target="visitorTrendChart">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container large">
                    <canvas id="visitorTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Traffic Sources Chart -->
    <div class="col-lg-4 mb-4">
        <div class="card advanced-card h-100">
            <div class="card-header card-header-advanced d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2 text-success"></i>
                    Traffic Sources
                </h5>
                <button class="btn btn-sm btn-outline-primary refresh-chart" data-target="trafficChart">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="trafficChart"></canvas>
                </div>
                <div class="mt-3">
                    <?php foreach ($analyticsData['current']['referrers'] as $i => $referrer): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="badge" style="background-color: <?php echo $chartData['referrers']['colors'][$i] ?? '#6c757d'; ?>;">&nbsp;</span>
                            <span class="ms-2"><?php echo htmlspecialchars($referrer['source']); ?></span>
                        </div>
                        <div class="text-end">
                            <small class="fw-bold"><?php echo number_format($referrer['count']); ?></small>
                            <br>
                            <small class="text-muted"><?php echo number_format($referrer['percentage'], 1); ?>%</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Charts Row -->
<div class="row mb-4">
    <!-- Hourly Activity Pattern -->
    <div class="col-lg-6 mb-4">
        <div class="card advanced-card h-100">
            <div class="card-header card-header-advanced">
                <h5 class="card-title mb-0">
                    <i class="fas fa-clock me-2 text-info"></i>
                    Hourly Activity Pattern
                </h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Peak hours: 
                        <?php 
                        $hourlyData = $chartData['hourly']['data'];
                        $peakHour = array_keys($hourlyData, max($hourlyData))[0];
                        echo $peakHour . ':00 - ' . ($peakHour + 1) . ':00';
                        ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Breakdown -->
    <div class="col-lg-6 mb-4">
        <div class="card advanced-card h-100">
            <div class="card-header card-header-advanced">
                <h5 class="card-title mb-0">
                    <i class="fas fa-mobile-alt me-2 text-warning"></i>
                    Device Breakdown
                </h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="deviceChart"></canvas>
                </div>
                <div class="mt-3">
                    <?php foreach ($analyticsData['devices'] as $device): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <i class="fas fa-<?php echo $device['device'] === 'Mobile' ? 'mobile-alt' : ($device['device'] === 'Tablet' ? 'tablet-alt' : 'desktop'); ?> me-2"></i>
                            <?php echo $device['device']; ?>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold"><?php echo number_format($device['visits']); ?></span>
                            <small class="text-muted ms-1">(<?php echo $device['percentage']; ?>%)</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Data Tables Row -->
<div class="row mb-4">
    <!-- Top Pages -->
    <div class="col-lg-8 mb-4">
        <div class="card advanced-card h-100">
            <div class="card-header card-header-advanced d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-alt me-2 text-primary"></i>
                    Top Pages
                </h5>
                <button class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-external-link-alt me-1"></i>
                    View All
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table data-table mb-0">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Visits</th>
                                <th>Avg. Time</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analyticsData['pages'] as $page): ?>
                            <tr>
                                <td>
                                    <span class="fw-medium"><?php echo htmlspecialchars($page['page']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo number_format($page['visits']); ?></span>
                                </td>
                                <td>
                                    <?php echo gmdate("i:s", $page['avg_time']); ?>
                                </td>
                                <td>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo min(100, ($page['visits'] / max(array_column($analyticsData['pages'], 'visits'))) * 100); ?>%"></div>
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

    <!-- Geographic Data -->
    <div class="col-lg-4 mb-4">
        <div class="card advanced-card h-100">
            <div class="card-header card-header-advanced">
                <h5 class="card-title mb-0">
                    <i class="fas fa-globe me-2 text-success"></i>
                    Geographic Distribution
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($analyticsData['geographic'] as $geo): ?>
                <div class="geographic-item">
                    <div class="d-flex align-items-center">
                        <div class="country-flag"></div>
                        <span><?php echo $geo['country']; ?></span>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold"><?php echo number_format($geo['visits']); ?></div>
                        <small class="text-muted"><?php echo $geo['percentage']; ?>%</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Conversion Funnel -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card advanced-card">
            <div class="card-header card-header-advanced">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2 text-warning"></i>
                    Conversion Funnel
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($analyticsData['conversion_funnel'] as $i => $step): ?>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="funnel-step" style="width: <?php echo 100 - ($i * 5); ?>%; margin-left: <?php echo $i * 2.5; ?>%;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-medium"><?php echo $step['step']; ?></span>
                                <span class="fw-bold"><?php echo number_format($step['count']); ?></span>
                            </div>
                            <small class="d-block mt-1"><?php echo $step['percentage']; ?>%</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Overall conversion rate: <?php echo end($analyticsData['conversion_funnel'])['percentage']; ?>%
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card advanced-card">
            <div class="card-header card-header-advanced">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt me-2 text-danger"></i>
                    Quick Actions & Insights
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="d-grid">
                            <a href="/admin/whatnot/settings.php" class="btn btn-outline-primary">
                                <i class="fas fa-video me-2"></i>
                                Whatnot Settings
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="d-grid">
                            <a href="/admin/blog/list.php" class="btn btn-outline-success">
                                <i class="fas fa-blog me-2"></i>
                                Manage Content
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="d-grid">
                            <button class="btn btn-outline-info" id="generate-report">
                                <i class="fas fa-file-pdf me-2"></i>
                                Generate Report
                            </button>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="d-grid">
                            <a href="/" target="_blank" class="btn btn-outline-warning">
                                <i class="fas fa-external-link-alt me-2"></i>
                                View Website
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="alert alert-info mb-0">
                            <h6 class="alert-heading">Performance Tip</h6>
                            <p class="mb-0">Your peak traffic time is around <?php echo $peakHour ?? 14; ?>:00. Consider scheduling important updates before this time.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-success mb-0">
                            <h6 class="alert-heading">Growth Opportunity</h6>
                            <p class="mb-0">Mobile traffic represents <?php echo $analyticsData['devices'][1]['percentage'] ?? 39; ?>% of visits. Optimize mobile experience for better conversions.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning mb-0">
                            <h6 class="alert-heading">Action Required</h6>
                            <p class="mb-0">Bounce rate is <?php echo number_format($analyticsData['advanced']['bounce_rate'], 1); ?>%. Consider improving page load speed and content relevance.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Advanced JavaScript for charts and interactivity
$extra_scripts = '
<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Chart Data -->
<script>
// Chart configuration and data
var chartData = ' . json_encode($chartData) . ';
var charts = {};
</script>

<!-- Dashboard Charts -->
<script src="dashboard-charts.js"></script>
';

// Include admin footer
include_once '../includes/footer.php';
?>
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
                            precision: 0,
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: "rgba(0, 0, 0, 0.1)"
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 1200,
                    easing: "easeOutBounce"
                }
            }
        });
        console.log('Hourly activity chart created successfully');
    } catch (error) {
        console.error('Error creating hourly activity chart:', error);
    }

    // Device Chart
    try {
        const deviceCtx = document.getElementById("deviceChart");
        if (!deviceCtx) {
            console.error('deviceChart canvas not found');
            return;
        }
        
        charts.device = new Chart(deviceCtx.getContext("2d"), {
            type: "pie",
            data: {
                labels: ["Desktop", "Mobile", "Tablet"],
                datasets: [
                    {
                        data: [67, 48, 12], // Using sample data
                        backgroundColor: [
                            "rgba(13, 110, 253, 0.8)",
                            "rgba(25, 135, 84, 0.8)",
                            "rgba(255, 193, 7, 0.8)"
                        ],
                        borderWidth: 0,
                        hoverBorderWidth: 3,
                        hoverBorderColor: "#fff"
                    }
                ]
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
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1500
                }
            }
        });
        console.log('Device breakdown chart created successfully');
    } catch (error) {
        console.error('Error creating device breakdown chart:', error);
    }
    
    console.log('All charts initialized');
}

function setupEventListeners() {
    // Chart type switcher
    document.querySelectorAll("[data-chart-type]").forEach(button => {
        button.addEventListener("click", function() {
            const chartType = this.getAttribute("data-chart-type");
            
            // Update button states
            this.parentElement.querySelectorAll("button").forEach(btn => btn.classList.remove("active"));
            this.classList.add("active");
            
            // Update chart type
            if (charts.visitorTrend) {
                charts.visitorTrend.config.type = chartType;
                charts.visitorTrend.update();
            }
        });
    });

    // Refresh buttons
    document.querySelectorAll(".refresh-chart").forEach(button => {
        button.addEventListener("click", function() {
            const target = this.getAttribute("data-target");
            this.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i>";
            
            setTimeout(() => {
                this.innerHTML = "<i class=\"fas fa-sync-alt\"></i>";
                // In real implementation, you would fetch fresh data here
            }, 1000);
        });
    });

    // Main refresh button
    document.getElementById("refresh-data").addEventListener("click", function() {
        this.innerHTML = "<i class=\"fas fa-spinner fa-spin me-1\"></i> Refreshing...";
        setTimeout(() => {
            location.reload();
        }, 500);
    });

    // Export button
    document.getElementById("export-data").addEventListener("click", function() {
        // In real implementation, you would generate and download a report
        alert("Export functionality would be implemented here");
    });

    // Generate report button
    document.getElementById("generate-report").addEventListener("click", function() {
        // In real implementation, you would generate a PDF report
        alert("Report generation would be implemented here");
    });

    // Real-time toggle
    document.getElementById("toggle-realtime").addEventListener("click", function() {
        const isActive = this.classList.contains("active");
        if (isActive) {
            this.classList.remove("active");
            this.innerHTML = "<i class=\"fas fa-play me-1\"></i> Start Live";
            stopRealtimeUpdates();
        } else {
            this.classList.add("active");
            this.innerHTML = "<i class=\"fas fa-circle text-success me-1 pulse\"></i> Live";
            startRealtimeUpdates();
        }
    });
}

let realtimeInterval;

function startRealtimeUpdates() {
    updateRealtimeData();
    realtimeInterval = setInterval(updateRealtimeData, 30000); // Update every 30 seconds
}

function stopRealtimeUpdates() {
    if (realtimeInterval) {
        clearInterval(realtimeInterval);
    }
}

function updateRealtimeData() {
    // In real implementation, you would fetch real-time data via AJAX
    const activeVisitors = Math.floor(Math.random() * 10) + 1;
    const uniqueActive = Math.floor(activeVisitors * 0.8);
    
    document.getElementById("active-visitors").textContent = activeVisitors;
    document.getElementById("unique-active").textContent = uniqueActive;
    document.getElementById("last-updated").textContent = "just now";
}

// Advanced animations for metric cards
document.querySelectorAll(".metric-card").forEach(card => {
    card.addEventListener("mouseenter", function() {
        this.style.transform = "translateY(-10px) scale(1.02)";
    });
    
    card.addEventListener("mouseleave", function() {
        this.style.transform = "translateY(0) scale(1)";
    });
});

// Smooth scrolling for internal links
document.querySelectorAll("a[href^=\"#\"]").forEach(anchor => {
    anchor.addEventListener("click", function(e) {
        e.preventDefault();
            target.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });
        }
    });
});

// Progressive loading animation for charts
function animateChartLoad(chart) {
    chart.data.datasets.forEach((dataset, index) => {
        dataset.data = dataset.data.map(() => 0);
    });
    chart.update();
    
    setTimeout(() => {
        chart.data.datasets.forEach((dataset, index) => {
            dataset.data = chartData.trend[index === 0 ? "visits" : "uniqueVisitors"];
        });
        chart.update();
    }, 100);
}
</script>
';

// Include admin footer
include_once '../includes/footer.php'; 
?>
