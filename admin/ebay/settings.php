<?php
// admin/ebay/settings.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once 'sync_helper.php'; // Include the sync helper
require_once 'token_refresh.php'; // Include the token refresh helper

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_api_settings':
                $settings = [
                    'ebay_app_id' => trim($_POST['ebay_app_id']),
                    'ebay_cert_id' => trim($_POST['ebay_cert_id']),
                    'ebay_dev_id' => trim($_POST['ebay_dev_id']),
                    'ebay_client_id' => trim($_POST['ebay_client_id']),
                    'ebay_client_secret' => trim($_POST['ebay_client_secret']),
                    'ebay_refresh_token' => trim($_POST['ebay_refresh_token']),
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
                
            case 'refresh_token':
                // Attempt to refresh the OAuth token
                $refresh_result = refreshEbayAccessToken();
                if ($refresh_result['success']) {
                    $_SESSION['success_message'] = 'eBay OAuth token refreshed successfully! Expires in ' . 
                        formatTimeDuration($refresh_result['expires_in'] ?? 7200) . '.'; 
                } else {
                    $_SESSION['error_message'] = 'Failed to refresh token: ' . $refresh_result['message'];
                }
                break;
                
            case 'sync_listings':
                // First ensure we have a valid token
                ensureValidEbayAccessToken();
                
                $seller_id = getSetting('ebay_seller_id');
                if (empty($seller_id)) {
                    $_SESSION['error_message'] = 'eBay seller ID is required for syncing listings.';
                } else {
                    // Use the new smart sync function that preserves categories and favorites
                    $sync_result = smartEbaySync($pdo);
                    if ($sync_result['success']) {
                        $_SESSION['success_message'] = "Sync complete: {$sync_result['new_count']} new, {$sync_result['updated_count']} updated, {$sync_result['preserved_count']} preserved, {$sync_result['deleted_count']} deleted in {$sync_result['duration']} seconds";
                    } else {
                        // Create a detailed error message
                        $error_details = 'Sync failed: ' . $sync_result['error'];
                        
                        // Add error location if available
                        if (isset($sync_result['error_location'])) {
                            $error_details .= '<br><br><strong>Error Location:</strong> ' . $sync_result['error_location'];
                        }
                        
                        // Add context information if available
                        if (isset($sync_result['error_context'])) {
                            $context = $sync_result['error_context'];
                            $error_details .= '<br><br><strong>Diagnostic Information:</strong><ul>';
                            
                            if (isset($context['token_exists'])) {
                                $error_details .= '<li>Access Token: ' . ($context['token_exists'] ? 'Present' : '<span class="text-danger">Missing</span>') . '</li>';
                            }
                            
                            if (isset($context['seller_id_exists'])) {
                                $error_details .= '<li>Seller ID: ' . ($context['seller_id_exists'] ? 'Present' : '<span class="text-danger">Missing</span>') . '</li>';
                            }
                            
                            if (isset($context['db_connected'])) {
                                $error_details .= '<li>Database Connection: ' . ($context['db_connected'] ? 'Active' : '<span class="text-danger">Failed</span>') . '</li>';
                            }
                            
                            $error_details .= '</ul>';
                        }
                        
                        // Add troubleshooting tips
                        $error_details .= '<br><strong>Troubleshooting Tips:</strong><ul>';
                        $error_details .= '<li>Check your eBay API credentials</li>';
                        $error_details .= '<li>Verify your seller ID is correct</li>';
                        $error_details .= '<li>Try refreshing your access token</li>';
                        $error_details .= '<li>Check server error logs for more details</li>';
                        $error_details .= '</ul>';
                        
                        $_SESSION['error_message'] = $error_details;
                    }
                }
                break;
                
            case 'connect_ebay':
                // Check if we have the required credentials
                $client_id = getSetting('ebay_client_id');
                $client_secret = getSetting('ebay_client_secret');
                $app_id = getSetting('ebay_app_id');
                $runame = getSetting('ebay_runame', 'Paul_Perschilli-PaulPers-TSCBOT-gyqfbjjy');
                
                if (empty($client_id) || empty($client_secret) || empty($app_id)) {
                    $_SESSION['error_message'] = 'Please configure your eBay API credentials first (Client ID, Client Secret, and App ID).';
                } else {
                    // Save the RuName if not already saved
                    if (empty(getSetting('ebay_runame'))) {
                        updateSetting('ebay_runame', 'Paul_Perschilli-PaulPers-TSCBOT-gyqfbjjy');
                    }
                    
                    // Generate the eBay OAuth URL with correct scopes
                    $scopes = 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.inventory https://api.ebay.com/oauth/api_scope/sell.marketing https://api.ebay.com/oauth/api_scope/sell.account https://api.ebay.com/oauth/api_scope/sell.fulfillment';
                    $sandbox = (bool)getSetting('ebay_sandbox_mode', 0);
                    
                    // Use RuName as the redirect_uri like in the original callback
                    $auth_url = ($sandbox ? 'https://auth.sandbox.ebay.com/oauth2/authorize?' : 'https://auth.ebay.com/oauth2/authorize?') . 
                                'client_id=' . urlencode($client_id) . 
                                '&response_type=code' . 
                                '&redirect_uri=' . urlencode($runame) . 
                                '&scope=' . urlencode($scopes) . 
                                '&state=ebay_auth';
                    
                    // Redirect to eBay auth page
                    header('Location: ' . $auth_url);
                    exit;
                }
                break;
                
            case 'disconnect_ebay':
                // Call the disconnect function
                if (disconnectEbay()) {
                    $_SESSION['success_message'] = 'Successfully disconnected from eBay.';
                } else {
                    $_SESSION['error_message'] = 'Error disconnecting from eBay.';
                }
                break;
        }
        
        header('Location: settings.php');
        exit;
    }
}

/**
 * Disconnect eBay integration by clearing tokens and settings
 * 
 * @return bool Success status
 */
function disconnectEbay() {
    try {
        // Clear all eBay tokens and connection settings
        updateSetting('ebay_access_token', '');
        updateSetting('ebay_refresh_token', '');
        updateSetting('ebay_user_token', '');
        updateSetting('ebay_token_expires_at', 0);
        updateSetting('ebay_oauth_connected', 0);
        
        // Don't clear the app credentials or seller ID, just the tokens
        // This makes it easier to reconnect later
        
        // Log the disconnect action
        error_log("eBay disconnected successfully");
        
        return true;
    } catch (Exception $e) {
        error_log("Error disconnecting eBay: " . $e->getMessage());
        return false;
    }
}

// Test eBay connection function
function testEbayConnection($app_id, $sandbox = false) {
    // First, try using the OAuth token if available (more reliable)
    $access_token = getSetting('ebay_access_token');
    if (!empty($access_token)) {
        // Try the Browse API first (OAuth)
        $endpoint = $sandbox ? 'https://api.sandbox.ebay.com/buy/browse/v1/item_summary/search?q=test' : 'https://api.ebay.com/buy/browse/v1/item_summary/search?q=test';
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'X-EBAY-C-MARKETPLACE-ID: EBAY_US'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // If successful OAuth call
        if ($http_code == 200) {
            return [
                'success' => true, 
                'message' => 'OAuth connection successful (HTTP ' . $http_code . ')'
            ];
        }
        
        // If we got a specific OAuth error, report it
        if ($http_code == 401) {
            // Token might be expired
            $data = json_decode($response, true);
            $error_msg = isset($data['error_description']) ? $data['error_description'] : 'OAuth token expired or invalid';
            return ['success' => false, 'error' => $error_msg, 'needs_token_refresh' => true];
        }
    }
    
    // Fallback to traditional API if OAuth fails or isn't available
    $endpoint = $sandbox ? 'https://api.sandbox.ebay.com/ws/api.dll' : 'https://api.ebay.com/ws/api.dll';
    
    // Check if we have all required credentials
    $dev_id = getSetting('ebay_dev_id', '');
    $cert_id = getSetting('ebay_cert_id', '');
    
    if (empty($dev_id) || empty($cert_id)) {
        return ['success' => false, 'error' => 'Missing required credentials (Dev ID and/or Cert ID)'];
    }
    
    // Simple XML request to test connectivity
    $xml = '<?xml version="1.0" encoding="utf-8"?>' .
           '<GeteBayOfficialTimeRequest xmlns="urn:ebay:apis:eBLBaseComponents">' .
           '<RequesterCredentials><AppId>' . $app_id . '</AppId></RequesterCredentials>' .
           '</GeteBayOfficialTimeRequest>';
    
    $headers = [
        'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
        'X-EBAY-API-DEV-NAME: ' . $dev_id,
        'X-EBAY-API-APP-NAME: ' . $app_id,
        'X-EBAY-API-CERT-NAME: ' . $cert_id,
        'X-EBAY-API-CALL-NAME: GeteBayOfficialTime',
        'X-EBAY-API-SITEID: 0',
        'Content-Type: text/xml'
    ];
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'cURL error: ' . $error];
    }
    
    // Log the response for debugging
    error_log("eBay API test response: " . $response);
    
    if (strpos($response, '<Ack>Success</Ack>') !== false) {
        // Extract the eBay time from the response
        preg_match('/<Timestamp>(.*?)<\/Timestamp>/', $response, $matches);
        $ebay_time = isset($matches[1]) ? $matches[1] : 'unknown';
        
        return [
            'success' => true, 
            'message' => 'eBay time: ' . $ebay_time
        ];
    } else {
        // Extract error message if available
        preg_match('/<LongMessage>(.*?)<\/LongMessage>/', $response, $matches);
        $error_msg = isset($matches[1]) ? $matches[1] : 'Unknown error';
        
        if (empty($error_msg) && $http_code != 200) {
            $error_msg = 'HTTP Error ' . $http_code;
        }
        
        return ['success' => false, 'error' => $error_msg];
    }
}

// Get current settings
$ebay_settings = [
    'app_id' => getSetting('ebay_app_id', ''),
    'cert_id' => getSetting('ebay_cert_id', ''),
    'dev_id' => getSetting('ebay_dev_id', ''),
    'client_id' => getSetting('ebay_client_id', ''),
    'client_secret' => getSetting('ebay_client_secret', ''),
    'refresh_token' => getSetting('ebay_refresh_token', ''),
    'access_token' => getSetting('ebay_access_token', ''),
    'token_expires_at' => (int)getSetting('ebay_token_expires_at', 0),
    'user_token' => getSetting('ebay_user_token', ''),
    'seller_id' => getSetting('ebay_seller_id', ''),
    'sandbox_mode' => (bool)getSetting('ebay_sandbox_mode', 1),
    'auto_sync' => (bool)getSetting('ebay_auto_sync', 0),
    'sync_interval' => (int)getSetting('ebay_sync_interval', 60)
];

// Get listing stats
try {
    $listing_stats = [
        'total_listings' => $pdo->query("SELECT COUNT(*) FROM ebay_listings")->fetchColumn(),
        'active_listings' => $pdo->query("SELECT COUNT(*) FROM ebay_listings WHERE quantity > 0")->fetchColumn(),
        'avg_price' => $pdo->query("SELECT AVG(price) FROM ebay_listings")->fetchColumn(),
        'favorite_count' => $pdo->query("SELECT COUNT(*) FROM ebay_listings WHERE is_favorite = 1")->fetchColumn()
    ];
} catch (PDOException $e) {
    // Table might not exist yet
    $listing_stats = [
        'total_listings' => 0,
        'active_listings' => 0,
        'avg_price' => 0,
        'favorite_count' => 0
    ];
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
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ebay_client_id" class="form-label">OAuth Client ID</label>
                            <input type="text" class="form-control" id="ebay_client_id" name="ebay_client_id" 
                                   value="<?php echo htmlspecialchars($ebay_settings['client_id']); ?>">
                            <div class="form-text">Your eBay OAuth Client ID for the Browse API</div>
                        </div>
                        <div class="col-md-6">
                            <label for="ebay_client_secret" class="form-label">OAuth Client Secret</label>
                            <input type="password" class="form-control" id="ebay_client_secret" name="ebay_client_secret" 
                                   value="<?php echo htmlspecialchars($ebay_settings['client_secret']); ?>">
                            <div class="form-text">Your eBay OAuth Client Secret for the Browse API</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ebay_refresh_token" class="form-label">OAuth Refresh Token</label>
                        <textarea class="form-control" id="ebay_refresh_token" name="ebay_refresh_token" rows="2"><?php echo htmlspecialchars($ebay_settings['refresh_token']); ?></textarea>
                        <div class="form-text">Your eBay OAuth Refresh Token for automatically refreshing access tokens</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ebay_user_token" class="form-label">User Token (Trading API)</label>
                        <textarea class="form-control" id="ebay_user_token" name="ebay_user_token" rows="3"><?php echo htmlspecialchars($ebay_settings['user_token']); ?></textarea>
                        <div class="form-text">Your eBay user token for Trading API authentication. <span class="text-danger">Note: This token expires and must be manually renewed.</span></div>
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
                                       min="15" max="1440">
                                <div class="form-text">
                                    <small>Minimum: 15 minutes, Maximum: 24 hours (1440 minutes)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" name="action" value="save_api_settings">
                            <i class="fas fa-save me-1"></i> Save Settings
                        </button>
                        <button type="submit" class="btn btn-info" name="action" value="test_connection">
                            <i class="fas fa-plug me-1"></i> Test Connection
                        </button>
                        <button type="submit" class="btn btn-warning" name="action" value="refresh_token">
                            <i class="fas fa-sync me-1"></i> Refresh Token
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Sync Controls -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">eBay Sync Controls</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <form method="post">
                            <input type="hidden" name="action" value="sync_listings">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sync me-1"></i> Sync eBay Listings
                            </button>
                            <div class="form-text text-center mt-2">
                                Pull your latest listings from eBay
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($ebay_settings['access_token']) || !empty($ebay_settings['refresh_token']) || !empty($ebay_settings['user_token'])): ?>
                            <form method="post" onsubmit="return confirm('Are you sure you want to disconnect from eBay? This will not delete any existing listings.');">
                                <input type="hidden" name="action" value="disconnect_ebay">
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-unlink me-1"></i> Disconnect eBay
                                </button>
                                <div class="form-text text-center mt-2">
                                    Remove eBay API connection
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="action" value="connect_ebay">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fab fa-ebay me-1"></i> Connect with eBay
                                </button>
                                <div class="form-text text-center mt-2">
                                    Establish connection with eBay API
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
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
                            <i class="fas fa-key me-2"></i> OAuth Tokens
                        </div>
                        <?php if (!empty($ebay_settings['access_token']) && $ebay_settings['token_expires_at'] > time()): ?>
                            <span class="badge bg-success">Valid (Expires: <?php echo date('M j, Y g:i a', $ebay_settings['token_expires_at']); ?>)</span>
                        <?php elseif (!empty($ebay_settings['access_token'])): ?>
                            <span class="badge bg-danger">Expired</span>
                        <?php elseif (!empty($ebay_settings['refresh_token'])): ?>
                            <span class="badge bg-warning">Needs Refresh</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not Configured</span>
                        <?php endif; ?>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="fas fa-key me-2"></i> Trading API Token
                        </div>
                        <?php if (!empty($ebay_settings['user_token'])): ?>
                            <span class="badge bg-<?php echo strlen($ebay_settings['user_token']) > 100 ? 'success' : 'danger'; ?>"> 
                                <?php echo strlen($ebay_settings['user_token']) > 100 ? 'Configured' : 'Invalid'; ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning">Missing</span>
                        <?php endif; ?>
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
                            <?php echo $listing_stats['total_listings'] > 0 ? number_format($listing_stats['total_listings']) : 'None'; ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="fas fa-tag me-2"></i> Active Listings
                        </div>
                        <span class="badge bg-<?php echo $listing_stats['active_listings'] > 0 ? 'success' : 'secondary'; ?>">
                            <?php echo $listing_stats['active_listings'] > 0 ? number_format($listing_stats['active_listings']) : 'None'; ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="fas fa-star me-2"></i> Favorited Items
                        </div>
                        <span class="badge bg-<?php echo $listing_stats['favorite_count'] > 0 ? 'success' : 'secondary'; ?>">
                            <?php echo $listing_stats['favorite_count'] > 0 ? number_format($listing_stats['favorite_count']) : 'None'; ?>
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
        
        <!-- Help & Resources -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Help & Resources</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6>Getting Started</h6>
                    <ul class="small ps-3">
                        <li>Enter your eBay API credentials from the Developer Portal</li>
                        <li>Set your eBay seller ID</li>
                        <li>Click "Test Connection" to verify your setup</li>
                        <li>Click "Sync eBay Listings" to import your listings</li>
                    </ul>
                </div>
                <div class="mb-3">
                    <h6>Token Management</h6>
                    <ul class="small ps-3">
                        <li>OAuth tokens automatically refresh</li>
                        <li>Trading API tokens must be manually renewed when expired</li>
                        <li>Use the "Refresh Token" button to manually refresh OAuth tokens</li>
                    </ul>
                </div>
                <div>
                    <h6>Useful Links</h6>
                    <ul class="small ps-3">
                        <li><a href="https://developer.ebay.com/my/keys" target="_blank">eBay Developer Portal</a></li>
                        <li><a href="https://developer.ebay.com/docs" target="_blank">eBay API Documentation</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Toggle sandbox/production mode explanations
    const sandboxToggle = document.getElementById("ebay_sandbox_mode");
    const sandboxHelp = document.getElementById("sandbox-help");
    const productionHelp = document.getElementById("production-help");
    
    function toggleSandboxHelp() {
        if (sandboxToggle && sandboxHelp && productionHelp) {
            if (sandboxToggle.checked) {
                sandboxHelp.style.display = "block";
                productionHelp.style.display = "none";
            } else {
                sandboxHelp.style.display = "none";
                productionHelp.style.display = "block";
            }
        }
    }
    
    if (sandboxToggle) {
        toggleSandboxHelp();
        sandboxToggle.addEventListener("change", toggleSandboxHelp);
    }
    
    // Toggle sync interval input
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
</script>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>
