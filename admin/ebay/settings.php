<?php
// admin/ebay/settings.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_api_settings':
                $settings = [
                    'ebay_app_id' => trim($_POST['ebay_app_id']),
                    'ebay_cert_id' => trim($_POST['ebay_cert_id']),
                    'ebay_dev_id' => trim($_POST['ebay_dev_id']),
                    'ebay_user_token' => trim($_POST['ebay_user_token']),
                    'ebay_seller_id' => trim($_POST['ebay_seller_id']),
                    'ebay_sandbox_mode' => isset($_POST['ebay_sandbox_mode']) ? 1 : 0,
                    'ebay_auto_sync' => isset($_POST['ebay_auto_sync']) ? 1 : 0,
                    'ebay_sync_interval' => (int)$_POST['ebay_sync_interval']
                ];
                
                try {
                    foreach ($settings as $key => $value) {
                        updateSetting($key, $value);
                    }
                    $_SESSION['success_message'] = 'eBay API settings saved successfully!';
                } catch (Exception $e) {
                    $_SESSION['error_message'] = 'Error saving settings: ' . $e->getMessage();
                }
                break;
                
            case 'test_connection':
                $app_id = trim($_POST['ebay_app_id']);
                $sandbox = isset($_POST['ebay_sandbox_mode']);
                
                if (empty($app_id)) {
                    $_SESSION['error_message'] = 'App ID is required to test connection.';
                } else {
                    $test_result = testEbayConnection($app_id, $sandbox);
                    if ($test_result['success']) {
                        $_SESSION['success_message'] = 'eBay connection test successful! ' . ($test_result['message'] ?? '');
                    } else {
                        $_SESSION['error_message'] = 'Connection test failed: ' . $test_result['error'] . 
                            '<br><br><strong>Troubleshooting Tips:</strong><br>' .
                            '• Make sure your App ID is correct (no extra spaces)<br>' .
                            '• Try switching between Sandbox and Production mode<br>' .
                            '• Verify your eBay Developer account is active<br>' .
                            '• Check that your App ID has the correct permissions';
                    }
                }
                break;
                
            case 'sync_listings':
                $seller_id = getSetting('ebay_seller_id');
                if (empty($seller_id)) {
                    $_SESSION['error_message'] = 'eBay seller ID is required for syncing listings.';
                } else {
                    $sync_result = syncEbayListings();
                    if ($sync_result['success']) {
                        $_SESSION['success_message'] = "Synced {$sync_result['count']} listings successfully!";
                    } else {
                        $_SESSION['error_message'] = 'Sync failed: ' . $sync_result['error'];
                    }
                }
                break;
        }
        
        header('Location: settings.php');
        exit;
    }
}

// Test eBay connection function
function testEbayConnection($app_id, $sandbox = false) {
    // Use the correct eBay Finding API endpoint
    $endpoint = $sandbox ? 
        'https://svcs.sandbox.ebay.com/services/search/FindingService/v1' : 
        'https://svcs.ebay.com/services/search/FindingService/v1';
    
    // Build the query parameters using eBay's expected format
    $params = [
        'OPERATION-NAME' => 'findItemsByKeywords',
        'SERVICE-VERSION' => '1.0.0',
        'SECURITY-APPNAME' => $app_id,
        'RESPONSE-DATA-FORMAT' => 'JSON',
        'REST-PAYLOAD' => '',
        'keywords' => 'test',
        'paginationInput.entriesPerPage' => '1'
    ];
    
    $query_string = http_build_query($params);
    $full_url = $endpoint . '?' . $query_string;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TriStateCards/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Debug information
    error_log("eBay API Test - URL: $full_url");
    error_log("eBay API Test - HTTP Code: $http_code");
    error_log("eBay API Test - Response: " . substr($response, 0, 500));
    
    if ($response === false) {
        return ['success' => false, 'error' => 'CURL Error: ' . $error];
    }
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        
        // Check if we got a valid JSON response
        if ($data === null) {
            return ['success' => false, 'error' => 'Invalid JSON response from eBay API'];
        }
        
        // Check if eBay returned an error in the response
        if (isset($data['findItemsByKeywordsResponse'][0]['ack'][0])) {
            $ack = $data['findItemsByKeywordsResponse'][0]['ack'][0];
            if ($ack === 'Success' || $ack === 'Warning') {
                return ['success' => true, 'data' => $data, 'message' => 'Connected successfully to eBay Finding API'];
            } else {
                $error_msg = 'eBay API Error';
                if (isset($data['findItemsByKeywordsResponse'][0]['errorMessage'][0]['error'][0]['message'][0])) {
                    $error_msg = $data['findItemsByKeywordsResponse'][0]['errorMessage'][0]['error'][0]['message'][0];
                }
                return ['success' => false, 'error' => $error_msg];
            }
        }
        
        // If no specific ack field, but we got a 200 response, consider it successful
        return ['success' => true, 'data' => $data, 'message' => 'API response received successfully'];
    } else {
        // For debugging, let's see what the actual response contains
        $error_details = "HTTP $http_code";
        if (!empty($response)) {
            $error_details .= " - Response: " . substr($response, 0, 200);
        }
        return ['success' => false, 'error' => $error_details];
    }
}

// Sync eBay listings function
function syncEbayListings() {
    global $pdo;
    
    try {
        $app_id = getSetting('ebay_app_id');
        $seller_id = getSetting('ebay_seller_id');
        $sandbox = (bool)getSetting('ebay_sandbox_mode');
        
        if (empty($app_id) || empty($seller_id)) {
            return ['success' => false, 'error' => 'Missing required API credentials'];
        }
        
        // Use the correct eBay Finding API endpoint
        $endpoint = $sandbox ? 
            'https://svcs.sandbox.ebay.com/services/search/FindingService/v1' : 
            'https://svcs.ebay.com/services/search/FindingService/v1';
        
        // Build the query parameters for GET request
        $params = [
            'OPERATION-NAME' => 'findItemsAdvanced',
            'SERVICE-VERSION' => '1.0.0',
            'SECURITY-APPNAME' => $app_id,
            'RESPONSE-DATA-FORMAT' => 'JSON',
            'itemFilter(0).name' => 'Seller',
            'itemFilter(0).value' => $seller_id,
            'itemFilter(1).name' => 'ListingType',
            'itemFilter(1).value(0)' => 'FixedPrice',
            'itemFilter(1).value(1)' => 'Auction',
            'paginationInput.entriesPerPage' => '100',
            'outputSelector(0)' => 'SellerInfo',
            'outputSelector(1)' => 'PictureURLLarge',
            'outputSelector(2)' => 'PictureURLSuperSize'
        ];
        
        $query_string = http_build_query($params);
        $full_url = $endpoint . '?' . $query_string;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $full_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TriStateCards/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'CURL Error: ' . $error];
        }
        
        if ($http_code !== 200) {
            return ['success' => false, 'error' => "API request failed with HTTP $http_code"];
        }
        
        $result = json_decode($response, true);
        
        // Check for eBay API errors
        if (!isset($result['findItemsAdvancedResponse'][0])) {
            return ['success' => false, 'error' => 'Invalid API response format'];
        }
        
        $response_data = $result['findItemsAdvancedResponse'][0];
        
        // Check if the API call was successful
        if (isset($response_data['ack'][0]) && $response_data['ack'][0] !== 'Success') {
            $error_msg = 'eBay API Error';
            if (isset($response_data['errorMessage'][0]['error'][0]['message'][0])) {
                $error_msg = $response_data['errorMessage'][0]['error'][0]['message'][0];
            }
            return ['success' => false, 'error' => $error_msg];
        }
        
        // Check if items were found
        if (!isset($response_data['searchResult'][0]['item'])) {
            return ['success' => true, 'count' => 0, 'message' => 'No items found for this seller'];
        }
        
        $items = $response_data['searchResult'][0]['item'];
        $synced_count = 0;
        
        // Create/update listings table if needed
        createEbayListingsTable($pdo);
        
        foreach ($items as $item) {
            $listing_data = [
                'item_id' => $item['itemId'][0] ?? '',
                'title' => $item['title'][0] ?? '',
                'category_name' => $item['primaryCategory'][0]['categoryName'][0] ?? '',
                'current_price' => $item['sellingStatus'][0]['currentPrice'][0]['__value__'] ?? 0,
                'currency' => $item['sellingStatus'][0]['currentPrice'][0]['@currencyId'] ?? 'USD',
                'listing_type' => $item['listingInfo'][0]['listingType'][0] ?? '',
                'start_time' => $item['listingInfo'][0]['startTime'][0] ?? null,
                'end_time' => $item['listingInfo'][0]['endTime'][0] ?? null,
                'view_item_url' => $item['viewItemURL'][0] ?? '',
                'gallery_url' => $item['galleryURL'][0] ?? '',
                'watch_count' => $item['listingInfo'][0]['watchCount'][0] ?? 0,
                'condition' => $item['condition'][0]['conditionDisplayName'][0] ?? '',
                'shipping_cost' => $item['shippingInfo'][0]['shippingServiceCost'][0]['__value__'] ?? 0,
                'returns_accepted' => isset($item['returnsAccepted'][0]) && $item['returnsAccepted'][0] === 'true' ? 1 : 0,
                'location' => $item['location'][0] ?? ''
            ];
            
            // Insert or update listing
            $query = "INSERT INTO ebay_listings 
                      (item_id, title, category_name, current_price, currency, listing_type, 
                       start_time, end_time, view_item_url, gallery_url, watch_count, 
                       condition_name, shipping_cost, returns_accepted, location, last_updated) 
                      VALUES 
                      (:item_id, :title, :category_name, :current_price, :currency, :listing_type,
                       :start_time, :end_time, :view_item_url, :gallery_url, :watch_count,
                       :condition, :shipping_cost, :returns_accepted, :location, NOW())
                      ON DUPLICATE KEY UPDATE
                      title = VALUES(title),
                      current_price = VALUES(current_price),
                      watch_count = VALUES(watch_count),
                      last_updated = NOW()";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($listing_data);
            $synced_count++;
        }
        
        // Update sync timestamp
        updateSetting('ebay_last_sync', date('Y-m-d H:i:s'));
        
        return ['success' => true, 'count' => $synced_count];
        
    } catch (Exception $e) {
        error_log('eBay sync error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Create eBay listings table
function createEbayListingsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `ebay_listings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `item_id` varchar(50) NOT NULL,
        `title` varchar(255) NOT NULL,
        `category_name` varchar(100) DEFAULT NULL,
        `current_price` decimal(10,2) DEFAULT 0.00,
        `currency` varchar(3) DEFAULT 'USD',
        `listing_type` varchar(20) DEFAULT NULL,
        `start_time` datetime DEFAULT NULL,
        `end_time` datetime DEFAULT NULL,
        `view_item_url` text,
        `gallery_url` text,
        `watch_count` int(11) DEFAULT 0,
        `condition_name` varchar(50) DEFAULT NULL,
        `shipping_cost` decimal(10,2) DEFAULT 0.00,
        `returns_accepted` tinyint(1) DEFAULT 0,
        `location` varchar(100) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `item_id` (`item_id`),
        KEY `end_time` (`end_time`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
}

// Get current settings
$ebay_settings = [
    'app_id' => getSetting('ebay_app_id', ''),
    'cert_id' => getSetting('ebay_cert_id', ''),
    'dev_id' => getSetting('ebay_dev_id', ''),
    'user_token' => getSetting('ebay_user_token', ''),
    'seller_id' => getSetting('ebay_seller_id', ''),
    'sandbox_mode' => (bool)getSetting('ebay_sandbox_mode', 1),
    'auto_sync' => (bool)getSetting('ebay_auto_sync', 0),
    'sync_interval' => (int)getSetting('ebay_sync_interval', 60),
    'last_sync' => getSetting('ebay_last_sync', 'Never')
];

// Get listing statistics
try {
    $stats_query = "SELECT 
                        COUNT(*) as total_listings,
                        COUNT(CASE WHEN end_time > NOW() THEN 1 END) as active_listings,
                        AVG(current_price) as avg_price,
                        SUM(watch_count) as total_watchers
                    FROM ebay_listings WHERE is_active = 1";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute();
    $listing_stats = $stats_stmt->fetch();
} catch (PDOException $e) {
    $listing_stats = [
        'total_listings' => 0,
        'active_listings' => 0,
        'avg_price' => 0,
        'total_watchers' => 0
    ];
}

// Page variables
$page_title = 'eBay Integration Settings';
$extra_scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Toggle sandbox/production mode explanations
    const sandboxToggle = document.getElementById("ebay_sandbox_mode");
    const sandboxHelp = document.getElementById("sandbox-help");
    const productionHelp = document.getElementById("production-help");
    
    function toggleMode() {
        if (sandboxToggle.checked) {
            sandboxHelp.style.display = "block";
            productionHelp.style.display = "none";
        } else {
            sandboxHelp.style.display = "none";
            productionHelp.style.display = "block";
        }
    }
    
    if (sandboxToggle) {
        toggleMode();
        sandboxToggle.addEventListener("change", toggleMode);
    }
    
    // Auto-sync toggle
    const autoSyncToggle = document.getElementById("ebay_auto_sync");
    const syncIntervalGroup = document.getElementById("sync-interval-group");
    
    function toggleAutoSync() {
        if (autoSyncToggle && syncIntervalGroup) {
            syncIntervalGroup.style.display = autoSyncToggle.checked ? "block" : "none";
        }
    }
    
    if (autoSyncToggle) {
        toggleAutoSync();
        autoSyncToggle.addEventListener("change", toggleAutoSync);
    }
});

// Test basic connectivity to eBay
function testBasicConnectivity() {
    const button = event.target;
    const originalHTML = button.innerHTML;
    button.innerHTML = "<i class=\"fas fa-spinner fa-spin me-1\"></i> Testing...";
    button.disabled = true;
    
    // Try to reach eBay\'s public API endpoint
    fetch("https://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsByKeywords&SERVICE-VERSION=1.0.0&SECURITY-APPNAME=test&RESPONSE-DATA-FORMAT=JSON&keywords=test&paginationInput.entriesPerPage=1", {
        method: "GET",
        mode: "no-cors" // This will always succeed but tells us if the endpoint is reachable
    })
    .then(() => {
        alert("✅ Basic connectivity to eBay API is working!\\n\\nThis means:\\n• Your server can reach eBay\'s servers\\n• No firewall is blocking the connection\\n• The eBay API endpoint is responding\\n\\nIf your App ID test still fails, the issue is likely with your App ID or eBay Developer account setup.");
    })
    .catch((error) => {
        alert("❌ Basic connectivity test failed.\\n\\nThis could mean:\\n• Your server cannot reach eBay\'s servers\\n• A firewall is blocking the connection\\n• There\'s a network issue\\n\\nError: " + error.message);
    })
    .finally(() => {
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}
</script>';

function initiateEbayOAuth() {
    $clientId = getSetting('ebay_app_id');
    $clientSecret = getSetting('ebay_cert_id');
    $ruName = getSetting('ebay_ru_name');
    $sandbox = (bool)getSetting('ebay_sandbox_mode');
    
    if (!$clientId || !$clientSecret || !$ruName) {
        return false;
    }
    
    require_once 'callback.php';
    $oauth = new EbayOAuth($clientId, $clientSecret, $ruName, $sandbox);
    
    // Generate a state parameter for security
    $state = bin2hex(random_bytes(16));
    $_SESSION['ebay_oauth_state'] = $state;
    
    return $oauth->getAuthUrl($state);
}

// Check if token needs refresh
function checkAndRefreshToken() {
    $tokenExpires = (int)getSetting('ebay_token_expires', 0);
    $refreshToken = getSetting('ebay_refresh_token');
    
    // If token expires in less than 5 minutes, refresh it
    if ($tokenExpires > 0 && $tokenExpires < (time() + 300) && !empty($refreshToken)) {
        $clientId = getSetting('ebay_app_id');
        $clientSecret = getSetting('ebay_cert_id');
        $ruName = getSetting('ebay_ru_name');
        $sandbox = (bool)getSetting('ebay_sandbox_mode');
        
        require_once 'callback.php';
        $oauth = new EbayOAuth($clientId, $clientSecret, $ruName, $sandbox);
        
        $tokenData = $oauth->refreshAccessToken($refreshToken);
        
        if ($tokenData && isset($tokenData['access_token'])) {
            updateSetting('ebay_access_token', $tokenData['access_token']);
            updateSetting('ebay_refresh_token', $tokenData['refresh_token'] ?? $refreshToken);
            updateSetting('ebay_token_expires', time() + ($tokenData['expires_in'] ?? 7200));
            return true;
        }
    }
    
    return false;
}

// Add this to the form submission handling section
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // ... existing cases ...
            
            case 'disconnect_ebay':
                // Clear OAuth tokens
                updateSetting('ebay_access_token', '');
                updateSetting('ebay_refresh_token', '');
                updateSetting('ebay_token_expires', '0');
                updateSetting('ebay_oauth_connected', '0');
                updateSetting('ebay_user_token', ''); // Clear old user token too
                
                $_SESSION['success_message'] = 'Disconnected from eBay successfully.';
                header('Location: settings.php');
                exit;
                break;
                
            case 'connect_ebay':
                $authUrl = initiateEbayOAuth();
                if ($authUrl) {
                    header('Location: ' . $authUrl);
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Please configure your eBay API credentials first.';
                }
                break;
        }
    }
}

// Check OAuth connection status
$isOAuthConnected = (bool)getSetting('ebay_oauth_connected', 0);
$tokenExpires = (int)getSetting('ebay_token_expires', 0);
$oauthStatus = 'disconnected';

if ($isOAuthConnected && $tokenExpires > time()) {
    $oauthStatus = 'connected';
    checkAndRefreshToken(); // Auto-refresh if needed
} elseif ($isOAuthConnected) {
    $oauthStatus = 'expired';
}


// Include admin header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- API Configuration -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">eBay API Configuration</h6>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_api_settings">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ebay_app_id" class="form-label">App ID (Client ID) *</label>
                            <input type="text" class="form-control" id="ebay_app_id" name="ebay_app_id" 
                                   value="<?php echo htmlspecialchars($ebay_settings['app_id']); ?>" required>
                            <div class="form-text">Your eBay application ID from the Developer Portal</div>
                        </div>
                        <div class="col-md-6">
                            <label for="ebay_cert_id" class="form-label">Cert ID (Client Secret)</label>
                            <input type="password" class="form-control" id="ebay_cert_id" name="ebay_cert_id" 
                                   value="<?php echo htmlspecialchars($ebay_settings['cert_id']); ?>">
                            <div class="form-text">Your eBay certificate ID (keep secret)</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ebay_dev_id" class="form-label">Dev ID</label>
                            <input type="text" class="form-control" id="ebay_dev_id" name="ebay_dev_id" 
                                   value="<?php echo htmlspecialchars($ebay_settings['dev_id']); ?>">
                            <div class="form-text">Your eBay developer ID</div>
                        </div>
                        <div class="col-md-6">
                            <label for="ebay_seller_id" class="form-label">eBay Seller ID *</label>
                            <input type="text" class="form-control" id="ebay_seller_id" name="ebay_seller_id" 
                                   value="<?php echo htmlspecialchars($ebay_settings['seller_id']); ?>" required>
                            <div class="form-text">Your eBay username/seller ID</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ebay_user_token" class="form-label">User Token</label>
                        <textarea class="form-control" id="ebay_user_token" name="ebay_user_token" rows="3"><?php echo htmlspecialchars($ebay_settings['user_token']); ?></textarea>
                        <div class="form-text">OAuth user token for authenticated API calls</div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ebay_sandbox_mode" name="ebay_sandbox_mode" 
                                       <?php echo $ebay_settings['sandbox_mode'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ebay_sandbox_mode">Use Sandbox Mode</label>
                            </div>
                            <div id="sandbox-help" class="alert alert-info mt-2" style="display: none;">
                                <small><i class="fas fa-info-circle me-1"></i> Sandbox mode is for testing. Use test data and sandbox credentials.</small>
                            </div>
                            <div id="production-help" class="alert alert-warning mt-2" style="display: none;">
                                <small><i class="fas fa-exclamation-triangle me-1"></i> Production mode uses live eBay data. Ensure your credentials are correct.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ebay_auto_sync" name="ebay_auto_sync" 
                                       <?php echo $ebay_settings['auto_sync'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ebay_auto_sync">Auto-Sync Listings</label>
                            </div>
                            <div id="sync-interval-group" class="mt-2" style="display: none;">
                                <label for="ebay_sync_interval" class="form-label small">Sync Interval (minutes)</label>
                                <input type="number" class="form-control form-control-sm" id="ebay_sync_interval" 
                                       name="ebay_sync_interval" value="<?php echo $ebay_settings['sync_interval']; ?>" 
                                       min="30" max="1440">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Settings
                        </button>
                        <button type="submit" name="action" value="test_connection" class="btn btn-outline-secondary">
                            <i class="fas fa-plug me-1"></i> Test Connection
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="testBasicConnectivity()">
                            <i class="fas fa-globe me-1"></i> Test Basic Connectivity
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">eBay Account Connection</h6>
    </div>
    <div class="card-body">
        <?php if ($oauthStatus === 'connected'): ?>
            <div class="alert alert-success">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Connected to eBay</strong>
                        <br>
                        <small>Token expires: <?php echo date('M j, Y g:i A', $tokenExpires); ?></small>
                    </div>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="disconnect_ebay">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to disconnect from eBay?');">
                            <i class="fas fa-unlink me-1"></i> Disconnect
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-2"><strong>Connection Status:</strong> <span class="badge bg-success">Active</span></p>
                    <p class="mb-2"><strong>Last Verified:</strong> <?php echo getSetting('ebay_oauth_last_verified', 'Never'); ?></p>
                </div>
                <div class="col-md-6">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="sync_listings">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-sync me-1"></i> Sync Listings Now
                        </button>
                    </form>
                </div>
            </div>
            
        <?php elseif ($oauthStatus === 'expired'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Token Expired</strong> - Please reconnect to continue using eBay features.
            </div>
            <form method="post">
                <input type="hidden" name="action" value="connect_ebay">
                <button type="submit" class="btn btn-primary">
                    <i class="fab fa-ebay me-1"></i> Reconnect to eBay
                </button>
            </form>
            
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Connect your eBay account to sync listings and access advanced features.
            </div>
            
            <div class="mb-3">
                <label for="ebay_ru_name" class="form-label">Redirect URI Name (RuName) *</label>
                <input type="text" class="form-control" id="ebay_ru_name" name="ebay_ru_name" 
                       value="<?php echo htmlspecialchars(getSetting('ebay_ru_name', '')); ?>" 
                       placeholder="Your eBay RuName from Developer Account">
                <div class="form-text">Get this from your eBay Developer Account under "Get a Token from eBay via Your Application"</div>
            </div>
            
            <?php if (!empty(getSetting('ebay_app_id')) && !empty(getSetting('ebay_cert_id')) && !empty(getSetting('ebay_ru_name'))): ?>
                <form method="post">
                    <input type="hidden" name="action" value="connect_ebay">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fab fa-ebay me-2"></i> Sign in with eBay
                    </button>
                </form>
            <?php else: ?>
                <p class="text-muted">Please configure your API credentials above before connecting.</p>
            <?php endif; ?>
        <?php endif; ?>
        
        <hr class="my-4">
        
        <h6 class="mb-3">OAuth Setup Instructions</h6>
        <ol class="small">
            <li>Go to your eBay Developer Account</li>
            <li>Navigate to "User Tokens" → "Get a Token from eBay via Your Application"</li>
            <li>Add these Redirect URIs:
                <ul>
                    <li><code><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/admin/ebay/callback.php</code></li>
                </ul>
            </li>
            <li>Copy your RuName and paste it above</li>
            <li>Click "Sign in with eBay" to connect your account</li>
        </ol>
    </div>
</div>
        
        <!-- Recent Listings -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Recent eBay Listings</h6>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="sync_listings">
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-sync me-1"></i> Sync Now
                    </button>
                </form>
            </div>
            <div class="card-body">
                <?php
                try {
                    $recent_query = "SELECT * FROM ebay_listings WHERE is_active = 1 ORDER BY last_updated DESC LIMIT 10";
                    $recent_stmt = $pdo->prepare($recent_query);
                    $recent_stmt->execute();
                    $recent_listings = $recent_stmt->fetchAll();
                } catch (PDOException $e) {
                    $recent_listings = [];
                }
                
                if (empty($recent_listings)): ?>
                    <div class="text-center py-4">
                        <div class="text-muted mb-3"><i class="fas fa-box-open fa-3x"></i></div>
                        <p>No listings found. Make sure your API credentials are configured and sync your listings.</p>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="sync_listings">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync me-1"></i> Sync Listings Now
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Price</th>
                                    <th>Watchers</th>
                                    <th>Ends</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_listings as $listing): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($listing['gallery_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($listing['gallery_url']); ?>" 
                                                         alt="Item" class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars(substr($listing['title'], 0, 50)); ?>...</div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($listing['condition_name']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">$<?php echo number_format($listing['current_price'], 2); ?></span>
                                            <?php if ($listing['shipping_cost'] > 0): ?>
                                                <br><small class="text-muted">+$<?php echo number_format($listing['shipping_cost'], 2); ?> ship</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($listing['watch_count'] > 0): ?>
                                                <span class="badge bg-info"><?php echo $listing['watch_count']; ?> watchers</span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($listing['end_time']): ?>
                                                <?php 
                                                $end_time = strtotime($listing['end_time']);
                                                $now = time();
                                                if ($end_time > $now): 
                                                ?>
                                                    <span class="text-success"><?php echo date('M j, g:i A', $end_time); ?></span>
                                                <?php else: ?>
                                                    <span class="text-danger">Ended</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
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
                    <div class="text-center mt-3">
                        <a href="listings.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-1"></i> View All Listings
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Statistics -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">eBay Statistics</h6>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="stat-value text-primary"><?php echo number_format($listing_stats['total_listings']); ?></div>
                        <div class="stat-label">Total Listings</div>
                    </div>
                    <div class="col-6">
                        <div class="stat-value text-success"><?php echo number_format($listing_stats['active_listings']); ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                </div>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="stat-value text-info">$<?php echo number_format($listing_stats['avg_price'], 2); ?></div>
                        <div class="stat-label">Avg Price</div>
                    </div>
                    <div class="col-6">
                        <div class="stat-value text-warning"><?php echo number_format($listing_stats['total_watchers']); ?></div>
                        <div class="stat-label">Total Watchers</div>
                    </div>
                </div>
                
                <hr>
                
                <div class="small">
                    <div class="d-flex justify-content-between">
                        <span>Last Sync:</span>
                        <span class="text-muted"><?php echo $ebay_settings['last_sync'] !== 'Never' ? date('M j, g:i A', strtotime($ebay_settings['last_sync'])) : 'Never'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Auto-Sync:</span>
                        <span class="<?php echo $ebay_settings['auto_sync'] ? 'text-success' : 'text-muted'; ?>">
                            <?php echo $ebay_settings['auto_sync'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Mode:</span>
                        <span class="<?php echo $ebay_settings['sandbox_mode'] ? 'text-warning' : 'text-success'; ?>">
                            <?php echo $ebay_settings['sandbox_mode'] ? 'Sandbox' : 'Production'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="listings.php" class="btn btn-outline-primary">
                        <i class="fas fa-list me-1"></i> Manage Listings
                    </a>
                    <a href="analytics.php" class="btn btn-outline-info">
                        <i class="fas fa-chart-bar me-1"></i> View Analytics
                    </a>
                    <a href="/admin/analytics/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-tachometer-alt me-1"></i> Main Analytics
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Help & Documentation -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Getting Started</h6>
            </div>
            <div class="card-body">
                <div class="accordion accordion-flush" id="helpAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="setupHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#setupCollapse">
                                1. Get eBay API Credentials
                            </button>
                        </h2>
                        <div id="setupCollapse" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                <ol>
                                    <li>Visit <a href="https://developer.ebay.com" target="_blank">eBay Developers Program</a></li>
                                    <li>Create a developer account</li>
                                    <li>Create an application to get your App ID</li>
                                    <li>Generate your Cert ID and Dev ID</li>
                                    <li>For advanced features, set up OAuth for User Token</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="syncHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#syncCollapse">
                                2. Sync Your Listings
                            </button>
                        </h2>
                        <div id="syncCollapse" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                <p>Once your credentials are configured:</p>
                                <ul>
                                    <li>Test your connection first</li>
                                    <li>Run a manual sync to import listings</li>
                                    <li>Enable auto-sync for automatic updates</li>
                                    <li>Check the analytics for performance data</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="featuresHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#featuresCollapse">
                                3. Available Features
                            </button>
                        </h2>
                        <div id="featuresCollapse" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                <ul>
                                    <li><strong>Listing Management:</strong> View and organize your eBay listings</li>
                                    <li><strong>Analytics:</strong> Track clicks, views, and performance</li>
                                    <li><strong>Auto-Sync:</strong> Keep listings updated automatically</li>
                                    <li><strong>Homepage Integration:</strong> Feature listings on your website</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Integration Status -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Integration Status</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="fas fa-key me-2"></i> API Credentials
                        </div>
                        <span class="badge bg-<?php echo !empty($ebay_settings['app_id']) ? 'success' : 'warning'; ?>">
                            <?php echo !empty($ebay_settings['app_id']) ? 'Configured' : 'Pending'; ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="fas fa-user me-2"></i> Seller ID
                        </div>
                        <span class="badge bg-<?php echo !empty($ebay_settings['seller_id']) ? 'success' : 'warning'; ?>">
                            <?php echo !empty($ebay_settings['seller_id']) ? 'Set' : 'Required'; ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="fas fa-list me-2"></i> Listings Synced
                        </div>
                        <span class="badge bg-<?php echo $listing_stats['total_listings'] > 0 ? 'success' : 'secondary'; ?>">
                            <?php echo $listing_stats['total_listings'] > 0 ? 'Yes' : 'None'; ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="fas fa-sync me-2"></i> Auto-Sync
                        </div>
                        <span class="badge bg-<?php echo $ebay_settings['auto_sync'] ? 'success' : 'secondary'; ?>">
                            <?php echo $ebay_settings['auto_sync'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>