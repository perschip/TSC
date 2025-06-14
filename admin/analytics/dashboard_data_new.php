<?php
// dashboard_data.php: Returns analytics data as JSON for AJAX dashboard
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

// Date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$comparison = isset($_GET['comparison']) && $_GET['comparison'] == 'true';

// If comparison is enabled, calculate previous period dates
$prev_start_date = null;
$prev_end_date = null;
if ($comparison) {
    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    $days_diff = round(($end_ts - $start_ts) / (60 * 60 * 24));
    $prev_start_ts = $start_ts - ($days_diff * 86400);
    $prev_end_ts = $start_ts - 86400; // Day before current start
    $prev_start_date = date('Y-m-d', $prev_start_ts);
    $prev_end_date = date('Y-m-d', $prev_end_ts);
}

/**
 * Get main analytics data
 */
function getAdvancedAnalyticsData($pdo, $start_date, $end_date, $prev_start_date = null, $prev_end_date = null) {
    // Dynamically detect the date column in the visits table
    try {
        $tableCheckQuery = "DESCRIBE visits";
        $tableCheckStmt = $pdo->prepare($tableCheckQuery);
        $tableCheckStmt->execute();
        $columns = $tableCheckStmt->fetchAll(PDO::FETCH_COLUMN);
        $dateColumn = 'created_at'; // Default
        if (in_array('visit_date', $columns)) {
            $dateColumn = 'visit_date';
        }
    } catch (PDOException $e) {
        $dateColumn = 'created_at';
    }
    
    // Daily stats
    $dailyStatsQuery = "SELECT DATE($dateColumn) as date, COUNT(*) as visits, COUNT(DISTINCT visitor_ip) as unique_visitors, 
                        AVG(pages_viewed) as avg_pages, AVG(time_on_site) as avg_time 
                        FROM visits 
                        WHERE $dateColumn BETWEEN :start AND :end 
                        GROUP BY DATE($dateColumn) 
                        ORDER BY date ASC";
    $stmt = $pdo->prepare($dailyStatsQuery);
    $stmt->execute(['start' => $start_date, 'end' => $end_date]);
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Referrer breakdown
    $referrerQuery = "SELECT IF(referrer = '' OR referrer IS NULL, 'Direct', referrer) as source, COUNT(*) as count 
                     FROM visits 
                     WHERE $dateColumn BETWEEN :start AND :end 
                     GROUP BY source 
                     ORDER BY count DESC";
    $stmt2 = $pdo->prepare($referrerQuery);
    $stmt2->execute(['start' => $start_date, 'end' => $end_date]);
    $referrers = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Overall stats
    $overallQuery = "SELECT COUNT(*) as total_visits, COUNT(DISTINCT visitor_ip) as unique_visitors, 
                    AVG(pages_viewed) as avg_pages, AVG(time_on_site) as avg_time 
                    FROM visits 
                    WHERE $dateColumn BETWEEN :start AND :end";
    $stmt3 = $pdo->prepare($overallQuery);
    $stmt3->execute(['start' => $start_date, 'end' => $end_date]);
    $overall = $stmt3->fetch(PDO::FETCH_ASSOC);

    // eBay clicks (remains on created_at, as this is for ebay_clicks table)
    $ebayQuery = "SELECT COUNT(*) as total_clicks FROM ebay_clicks WHERE created_at BETWEEN :start AND :end";
    $stmt4 = $pdo->prepare($ebayQuery);
    $stmt4->execute(['start' => $start_date, 'end' => $end_date]);
    $ebay_clicks = $stmt4->fetch(PDO::FETCH_ASSOC)['total_clicks'];

    // Whatnot clicks (remains on created_at, as this is for whatnot_clicks table)
    $whatnotQuery = "SELECT COUNT(*) as total_clicks FROM whatnot_clicks WHERE created_at BETWEEN :start AND :end";
    $stmt5 = $pdo->prepare($whatnotQuery);
    $stmt5->execute(['start' => $start_date, 'end' => $end_date]);
    $whatnot_clicks = $stmt5->fetch(PDO::FETCH_ASSOC)['total_clicks'];

    // Previous period data for comparison if requested
    $previous = null;
    if ($prev_start_date && $prev_end_date) {
        // Overall stats for previous period
        $prevOverallQuery = "SELECT COUNT(*) as total_visits, COUNT(DISTINCT visitor_ip) as unique_visitors, 
                            AVG(pages_viewed) as avg_pages, AVG(time_on_site) as avg_time 
                            FROM visits 
                            WHERE $dateColumn BETWEEN :start AND :end";
        $prevStmt = $pdo->prepare($prevOverallQuery);
        $prevStmt->execute(['start' => $prev_start_date, 'end' => $prev_end_date]);
        $prevOverall = $prevStmt->fetch(PDO::FETCH_ASSOC);
        
        // eBay clicks for previous period
        $prevEbayQuery = "SELECT COUNT(*) as total_clicks FROM ebay_clicks WHERE created_at BETWEEN :start AND :end";
        $prevEbayStmt = $pdo->prepare($prevEbayQuery);
        $prevEbayStmt->execute(['start' => $prev_start_date, 'end' => $prev_end_date]);
        $prevEbayClicks = $prevEbayStmt->fetch(PDO::FETCH_ASSOC)['total_clicks'];
        
        // Whatnot clicks for previous period
        $prevWhatnotQuery = "SELECT COUNT(*) as total_clicks FROM whatnot_clicks WHERE created_at BETWEEN :start AND :end";
        $prevWhatnotStmt = $pdo->prepare($prevWhatnotQuery);
        $prevWhatnotStmt->execute(['start' => $prev_start_date, 'end' => $prev_end_date]);
        $prevWhatnotClicks = $prevWhatnotStmt->fetch(PDO::FETCH_ASSOC)['total_clicks'];
        
        $previous = [
            'overall' => $prevOverall,
            'ebay_clicks' => $prevEbayClicks,
            'whatnot_clicks' => $prevWhatnotClicks,
            'period' => [
                'start' => $prev_start_date,
                'end' => $prev_end_date
            ]
        ];
    }

    return [
        'daily_stats' => $daily_stats,
        'referrers' => $referrers,
        'overall' => $overall,
        'ebay_clicks' => $ebay_clicks,
        'whatnot_clicks' => $whatnot_clicks,
        'previous' => $previous,
        'period' => [
            'start' => $start_date,
            'end' => $end_date
        ],
        'last_updated' => date('Y-m-d H:i:s'),
        'date_column_used' => $dateColumn
    ];
}

// Get the data
try {
    $data = getAdvancedAnalyticsData($pdo, $start_date, $end_date, $prev_start_date, $prev_end_date);
    echo json_encode($data);
} catch (Exception $e) {
    // Return error information
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
