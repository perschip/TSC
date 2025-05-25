<?php
// Create a URL-friendly slug from a string
function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/\s+/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Generate excerpt from content
function generateExcerpt($content, $length = 160) {
    // Strip HTML tags
    $text = strip_tags($content);
    
    // Trim the string to the maximum length
    $text = trim(substr($text, 0, $length));
    
    // Check if the trimmed text ends in the middle of a word
    if (substr($text, -1) !== '.') {
        // Find the last space before the limit
        $lastSpace = strrpos($text, ' ');
        if ($lastSpace !== false) {
            $text = substr($text, 0, $lastSpace);
        }
        
        // Add ellipsis
        $text .= '...';
    }
    
    return $text;
}

// Get site settings
function getSetting($key, $default = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Update site settings
function updateSetting($key, $value) {
    global $pdo;
    
    try {
        // Check if setting exists
        $check = $pdo->prepare("SELECT COUNT(*) as count FROM site_settings WHERE setting_key = :key");
        $check->bindParam(':key', $key);
        $check->execute();
        $exists = $check->fetch()['count'] > 0;
        
        if ($exists) {
            // Update existing setting
            $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = :value WHERE setting_key = :key");
        } else {
            // Insert new setting
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)");
        }
        
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        return $stmt->execute();
    } catch (PDOException $e) {
        return false;
    }
}

// Log site visit
function logVisit() {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO visits (visitor_ip, visitor_ua, referrer) VALUES (:ip, :ua, :referrer)");
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':ua', $ua);
        $stmt->bindParam(':referrer', $referrer);
        $stmt->execute();
        
        // Store visit ID in session for tracking page views and time on site
        $_SESSION['visit_id'] = $pdo->lastInsertId();
        $_SESSION['visit_start'] = time();
        $_SESSION['pages_viewed'] = 1;
    } catch (PDOException $e) {
        // Silent fail
    }
}

// Update visit statistics
function updateVisitStats() {
    global $pdo;
    
    if (isset($_SESSION['visit_id']) && isset($_SESSION['visit_start']) && isset($_SESSION['pages_viewed'])) {
        $visit_id = $_SESSION['visit_id'];
        $time_on_site = time() - $_SESSION['visit_start'];
        $pages_viewed = $_SESSION['pages_viewed'];
        
        try {
            $stmt = $pdo->prepare("UPDATE visits SET time_on_site = :time, pages_viewed = :pages WHERE id = :id");
            $stmt->bindParam(':time', $time_on_site);
            $stmt->bindParam(':pages', $pages_viewed);
            $stmt->bindParam(':id', $visit_id);
            $stmt->execute();
        } catch (PDOException $e) {
            // Silent fail
        }
    }
}

// Track page view
function trackPageView() {
    if (isset($_SESSION['pages_viewed'])) {
        $_SESSION['pages_viewed']++;
    }
}

// Check and update Whatnot status
function checkWhatnotStatus() {
    global $pdo;
    
    $api_key = getSetting('whatnot_api_key');
    $api_secret = getSetting('whatnot_api_secret');
    $username = getSetting('whatnot_username');
    
    if (empty($username)) {
        return false;
    }
    
    // If API credentials are provided, fetch status from API
    if (!empty($api_key) && !empty($api_secret)) {
        // This would be replaced with actual API call
        // For demo purposes, just return a placeholder result
        $is_live = false;
        $stream_title = '';
        $stream_url = '';
        $scheduled_time = null;
        
        // Insert status into database
        try {
            $stmt = $pdo->prepare("INSERT INTO whatnot_status (is_live, stream_title, stream_url, scheduled_time, last_checked) 
                                   VALUES (:is_live, :stream_title, :stream_url, :scheduled_time, NOW())");
            $stmt->bindParam(':is_live', $is_live, PDO::PARAM_INT);
            $stmt->bindParam(':stream_title', $stream_title);
            $stmt->bindParam(':stream_url', $stream_url);
            $stmt->bindParam(':scheduled_time', $scheduled_time);
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    return false;
}

// Format date
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

// Format number with commas
function formatNumber($number) {
    return number_format($number);
}

// Format time duration from seconds
function formatTimeDuration($seconds) {
    if ($seconds < 60) {
        return round($seconds) . " seconds";
    } elseif ($seconds < 3600) {
        return round($seconds / 60, 1) . " minutes";
    } else {
        return round($seconds / 3600, 1) . " hours";
    }
}

// Get month names for chart data
function getLastSixMonths() {
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date("M", strtotime("-$i months"));
    }
    return $months;
}

// FIX FOR ADMIN DASHBOARD ERROR
// Provide empty data if there's an error with these functions
function safeGetMonthlyVisitorData($pdo) {
    try {
        $query = "SELECT 
                    DATE_FORMAT(visit_date, '%b') as month,
                    COUNT(*) as visits,
                    COUNT(DISTINCT visitor_ip) as unique_visitors
                  FROM visits
                  WHERE visit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                  GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
                  ORDER BY visit_date";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Return empty data on error
        return [];
    }
}

// FIX FOR VISITOR STATS ERROR
function safeGetVisitorStats($pdo, $period = 'week') {
    try {
        // Original function code
        $timeConstraint = '';
        
        switch ($period) {
            case 'day':
                $timeConstraint = "WHERE visit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)";
                break;
            case 'week':
                $timeConstraint = "WHERE visit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
                break;
            case 'month':
                $timeConstraint = "WHERE visit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
                break;
            case 'year':
                $timeConstraint = "WHERE visit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)";
                break;
            default:
                $timeConstraint = "WHERE visit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
        }
        
        $query = "SELECT 
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT visitor_ip) as unique_visitors,
                    AVG(pages_viewed) as avg_pages,
                    AVG(time_on_site) as avg_time,
                    (SELECT COUNT(*) FROM whatnot_clicks $timeConstraint) as whatnot_clicks,
                    (SELECT COUNT(*) FROM ebay_clicks $timeConstraint) as ebay_clicks
                  FROM visits $timeConstraint";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        // Return default values on error
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

// Initialize visit tracking
if (!isset($_SESSION['visit_id'])) {
    // Try-catch to prevent errors if tables don't exist
    try {
        logVisit();
    } catch (Exception $e) {
        // Silently fail
    }
} else {
    try {
        trackPageView();
    } catch (Exception $e) {
        // Silently fail
    }
}

// Register shutdown function to update visit stats
register_shutdown_function(function() {
    try {
        updateVisitStats();
    } catch (Exception $e) {
        // Silently fail
    }
});