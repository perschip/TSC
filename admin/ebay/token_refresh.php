<?php
/**
 * eBay Token Refresh Helper
 * 
 * This file handles automatic refreshing of eBay OAuth tokens
 * to ensure continuous API access.
 */
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

/**
 * Refresh eBay OAuth access token using the refresh token
 * 
 * @param bool $retry Whether this is a retry attempt
 * @return array Result of the refresh attempt
 */
function refreshEbayAccessToken($retry = false) {
    // Get the necessary credentials
    $client_id = getSetting('ebay_client_id');
    $client_secret = getSetting('ebay_client_secret');
    $refresh_token = getSetting('ebay_refresh_token');
    
    // Check if we have the required credentials
    if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
        error_log("eBay OAuth Error - Missing credentials: " . 
                 "client_id empty: " . (empty($client_id) ? 'yes' : 'no') . ", " .
                 "client_secret empty: " . (empty($client_secret) ? 'yes' : 'no') . ", " .
                 "refresh_token empty: " . (empty($refresh_token) ? 'yes' : 'no'));
        return [
            'success' => false,
            'message' => 'Missing required credentials (client ID, client secret, or refresh token)'
        ];
    }
    
    // Set up the request to the eBay OAuth API
    $ch = curl_init('https://api.ebay.com/identity/v1/oauth2/token');
    
    // Set the authorization header with Base64 encoded client credentials
    $credentials = base64_encode($client_id . ':' . $client_secret);
    
    // Set up the request parameters with improved error handling
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . $credentials
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set timeout to 30 seconds
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verify SSL
    curl_setopt($ch, CURLOPT_FAILONERROR, false); // Don't fail on HTTP error codes
    
    // Set the request body - omit scope parameter entirely
    $postFields = http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token
        // No scope parameter - will use the scopes originally granted
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    
    // Execute the request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    
    // Log the request details for debugging
    error_log("eBay OAuth Request - URL: https://api.ebay.com/identity/v1/oauth2/token");
    error_log("eBay OAuth Request - HTTP Code: $http_code");
    
    // Check for connection errors
    if (!$response) {
        error_log("eBay OAuth Error - No response received: $curl_error (Error #$curl_errno)");
        
        // If this is a connection error and not a retry, try once more
        if (!$retry && ($curl_errno == CURLE_OPERATION_TIMEOUTED || 
                        $curl_errno == CURLE_COULDNT_CONNECT || 
                        $curl_errno == CURLE_COULDNT_RESOLVE_HOST)) {
            error_log("eBay OAuth - Retrying after connection error");
            sleep(2); // Wait 2 seconds before retry
            return refreshEbayAccessToken(true);
        }
        
        return [
            'success' => false,
            'message' => 'cURL error: ' . $curl_error . ' (Error #' . $curl_errno . ')',
            'http_code' => $http_code
        ];
    }
    
    // Parse the response
    $data = json_decode($response, true);
    
    // Log the response for debugging
    error_log("eBay OAuth Response: " . substr($response, 0, 500));
    
    // Check if we got a valid response
    if (!$data) {
        error_log("eBay OAuth Error - Invalid JSON response: " . $response);
        return [
            'success' => false,
            'message' => 'Invalid JSON response from eBay OAuth API',
            'response' => $response,
            'http_code' => $http_code
        ];
    }
    
    // Check for error in the response
    if (isset($data['error']) || !isset($data['access_token'])) {
        $error_message = isset($data['error_description']) ? $data['error_description'] : (isset($data['error']) ? $data['error'] : 'Unknown error');
        error_log("eBay OAuth Error - API returned error: $error_message");
        
        // If this is a server error and not a retry, try once more
        if (!$retry && $http_code >= 500 && $http_code < 600) {
            error_log("eBay OAuth - Retrying after server error");
            sleep(2); // Wait 2 seconds before retry
            return refreshEbayAccessToken(true);
        }
        
        return [
            'success' => false,
            'message' => 'eBay API error: ' . $error_message,
            'response' => $data,
            'http_code' => $http_code
        ];
    }
    
    // Update the access token in the database
    updateSetting('ebay_access_token', $data['access_token']);
    
    // Also update the token expiry time if provided
    if (isset($data['expires_in'])) {
        $expires_at = time() + $data['expires_in'];
        updateSetting('ebay_token_expires_at', $expires_at);
        
        // Log when the token will expire
        error_log("eBay OAuth - New token will expire at: " . date('Y-m-d H:i:s', $expires_at));
    }
    
    // Update the last refresh time
    updateSetting('ebay_last_token_refresh', date('Y-m-d H:i:s'));
    
    return [
        'success' => true,
        'message' => 'Access token refreshed successfully',
        'expires_in' => $data['expires_in'] ?? 'unknown',
        'token_type' => $data['token_type'] ?? 'unknown'
    ];
}

/**
 * Check if the access token needs to be refreshed and refresh it if needed
 * 
 * @param bool $force Force refresh even if token is not expired
 * @return bool True if token is valid (either already valid or successfully refreshed)
 */
function ensureValidEbayAccessToken($force = false) {
    // Check if we have an access token and when it expires
    $access_token = getSetting('ebay_access_token', '');
    $expires_at = (int)getSetting('ebay_token_expires_at', 0);
    
    // Check when the token was last refreshed
    $last_refresh = getSetting('ebay_last_token_refresh', '');
    $last_refresh_time = !empty($last_refresh) ? strtotime($last_refresh) : 0;
    
    // If the token is still valid for more than 10 minutes and not forcing refresh, no need to refresh
    if (!$force && $expires_at > time() + 600) {
        // Log that we're using the existing token
        error_log("eBay OAuth - Using existing token valid until " . date('Y-m-d H:i:s', $expires_at));
        return true;
    }
    
    // If we've tried to refresh in the last 5 minutes and failed, don't try again to avoid API rate limits
    if (!$force && !empty($last_refresh) && time() - $last_refresh_time < 300 && empty($access_token)) {
        error_log("eBay OAuth - Skipping refresh, last attempt was at " . $last_refresh);
        return false;
    }
    
    // Token is expired or about to expire, refresh it
    error_log("eBay OAuth - Token needs refresh, current expiry: " . 
             ($expires_at > 0 ? date('Y-m-d H:i:s', $expires_at) : 'unknown'));
    
    $result = refreshEbayAccessToken();
    
    // Log the result for debugging
    error_log('eBay token refresh attempt: ' . ($result['success'] ? 'Success' : 'Failed - ' . $result['message']));
    
    // If refresh failed, try one more time with a delay
    if (!$result['success'] && !$force) {
        error_log('eBay OAuth - First refresh attempt failed, trying again after delay');
        sleep(3); // Wait 3 seconds before retry
        $result = refreshEbayAccessToken(true); // True indicates this is a retry
        error_log('eBay token second refresh attempt: ' . ($result['success'] ? 'Success' : 'Failed - ' . $result['message']));
    }
    
    return $result['success'];
}

/**
 * Check if we need to notify the admin about token issues
 */
function checkEbayTokenStatus() {
    $access_token = getSetting('ebay_access_token');
    $refresh_token = getSetting('ebay_refresh_token');
    $user_token = getSetting('ebay_user_token');
    $last_notification = (int)getSetting('ebay_token_notification', 0);
    
    $issues = [];
    
    // Check for missing tokens
    if (empty($access_token)) {
        $issues[] = 'Missing OAuth access token';
    }
    
    if (empty($refresh_token)) {
        $issues[] = 'Missing OAuth refresh token';
    }
    
    if (empty($user_token)) {
        $issues[] = 'Missing user token for Trading API';
    }
    
    // Check if user token is about to expire (if we have expiry info)
    $user_token_expires = (int)getSetting('ebay_user_token_expires_at', 0);
    if ($user_token_expires > 0 && $user_token_expires < time() + (7 * 24 * 60 * 60)) {
        $issues[] = 'User token will expire in less than 7 days';
    }
    
    // If we have issues and haven't notified in the last 24 hours
    if (!empty($issues) && $last_notification < time() - (24 * 60 * 60)) {
        // Update the last notification time
        updateSetting('ebay_token_notification', time());
        
        // Create a notification in the database if we have a notifications table
        try {
            global $pdo;
            $pdo->prepare("
                INSERT INTO admin_notifications (title, message, type, is_read, created_at)
                VALUES (:title, :message, 'warning', 0, NOW())
            ")->execute([
                'title' => 'eBay Token Issues',
                'message' => 'The following issues were detected with your eBay API tokens: ' . implode(', ', $issues) . '. Please update your tokens in the eBay settings.'
            ]);
        } catch (Exception $e) {
            // Silently fail if the table doesn't exist
        }
    }
}

// If this file is called directly, run the token refresh
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Refresh the token
    $result = refreshEbayAccessToken();
    
    // If this is an AJAX request, return JSON
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        // Otherwise, display a simple HTML page
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>eBay Token Refresh</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .success { color: green; }
                .error { color: red; }
                pre { background: #f5f5f5; padding: 10px; }
            </style>
        </head>
        <body>
            <h1>eBay Token Refresh</h1>';
        
        if ($result['success']) {
            echo '<p class="success">Token refreshed successfully!</p>';
            echo '<p>The token will expire in ' . ($result['expires_in'] ?? 'unknown') . ' seconds.</p>';
        } else {
            echo '<p class="error">Token refresh failed: ' . $result['message'] . '</p>';
            if (isset($result['response'])) {
                echo '<pre>' . htmlspecialchars($result['response']) . '</pre>';
            }
        }
        
        echo '<p><a href="../ebay/index.php">Return to eBay Dashboard</a></p>
        </body>
        </html>';
    }
    exit;
}
?>
