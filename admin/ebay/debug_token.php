<?php
// Debug script for eBay token refresh
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'token_refresh.php';

// Set error reporting to maximum
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'ebay_token_debug.log');

// HTML header
echo '<!DOCTYPE html>
<html>
<head>
    <title>eBay Token Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; max-height: 400px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .code { font-family: monospace; }
    </style>
</head>
<body>
    <h1>eBay OAuth Token Debug Tool</h1>';

// Check OAuth credentials
echo '<div class="section">
    <h2>OAuth Credentials</h2>';

$client_id = getSetting('ebay_client_id');
$client_secret = getSetting('ebay_client_secret');
$refresh_token = getSetting('ebay_refresh_token');
$access_token = getSetting('ebay_access_token');
$token_expires_at = (int)getSetting('ebay_token_expires_at', 0);

if (empty($client_id)) {
    echo '<p class="error">No OAuth Client ID found!</p>';
} else {
    echo '<p class="success">OAuth Client ID found: ' . substr($client_id, 0, 10) . '...</p>';
}

if (empty($client_secret)) {
    echo '<p class="error">No OAuth Client Secret found!</p>';
} else {
    echo '<p class="success">OAuth Client Secret found: ' . substr($client_secret, 0, 5) . '...</p>';
}

if (empty($refresh_token)) {
    echo '<p class="error">No Refresh Token found!</p>';
} else {
    echo '<p class="success">Refresh Token found: ' . substr($refresh_token, 0, 10) . '...</p>';
}

if (empty($access_token)) {
    echo '<p class="error">No Access Token found!</p>';
} else {
    echo '<p class="success">Access Token found: ' . substr($access_token, 0, 10) . '...</p>';
    
    if ($token_expires_at > time()) {
        echo '<p class="success">Token is valid until: ' . date('Y-m-d H:i:s', $token_expires_at) . '</p>';
    } else {
        echo '<p class="error">Token is expired since: ' . date('Y-m-d H:i:s', $token_expires_at) . '</p>';
    }
}

echo '</div>';

// Manual token refresh form
echo '<div class="section">
    <h2>Manual Token Refresh</h2>
    <p>Use this form to manually attempt a token refresh with custom parameters.</p>
    <form method="post">
        <div style="margin-bottom: 15px;">
            <label for="client_id" style="display: block; margin-bottom: 5px;">Client ID:</label>
            <input type="text" id="client_id" name="client_id" value="' . htmlspecialchars($client_id) . '" style="width: 100%; padding: 8px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="client_secret" style="display: block; margin-bottom: 5px;">Client Secret:</label>
            <input type="text" id="client_secret" name="client_secret" value="' . htmlspecialchars($client_secret) . '" style="width: 100%; padding: 8px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="refresh_token" style="display: block; margin-bottom: 5px;">Refresh Token:</label>
            <textarea id="refresh_token" name="refresh_token" style="width: 100%; padding: 8px; height: 100px;">' . htmlspecialchars($refresh_token) . '</textarea>
        </div>
        <div style="margin-bottom: 15px;">
            <label for="scope" style="display: block; margin-bottom: 5px;">OAuth Scope:</label>
            <textarea id="scope" name="scope" style="width: 100%; padding: 8px; height: 100px;">https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/buy.item.feed https://api.ebay.com/oauth/api_scope/buy.marketing https://api.ebay.com/oauth/api_scope/buy.product.feed https://api.ebay.com/oauth/api_scope/buy.marketplace.insights https://api.ebay.com/oauth/api_scope/buy.item.bulk</textarea>
        </div>
        <button type="submit" name="action" value="manual_refresh" style="padding: 10px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer;">
            Attempt Token Refresh
        </button>
    </form>
</div>';

// Process manual refresh
if (isset($_POST['action']) && $_POST['action'] === 'manual_refresh') {
    echo '<div class="section">
        <h2>Manual Refresh Results</h2>';
    
    $manual_client_id = trim($_POST['client_id']);
    $manual_client_secret = trim($_POST['client_secret']);
    $manual_refresh_token = trim($_POST['refresh_token']);
    $manual_scope = trim($_POST['scope']);
    
    if (empty($manual_client_id) || empty($manual_client_secret) || empty($manual_refresh_token)) {
        echo '<p class="error">All fields are required!</p>';
    } else {
        echo '<p>Attempting token refresh with provided credentials...</p>';
        
        // Set up the request to the eBay OAuth API
        $ch = curl_init('https://api.ebay.com/identity/v1/oauth2/token');
        
        // Set the authorization header with Base64 encoded client credentials
        $credentials = base64_encode($manual_client_id . ':' . $manual_client_secret);
        
        // Set up the request parameters
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $credentials
        ]);
        
        // Set the request body
        $postFields = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $manual_refresh_token,
            'scope' => $manual_scope
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        
        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo '<p>HTTP Response Code: ' . $http_code . '</p>';
        
        if (!empty($curl_error)) {
            echo '<p class="error">cURL Error: ' . $curl_error . '</p>';
        }
        
        if ($response) {
            echo '<p>Response received (length: ' . strlen($response) . ' bytes)</p>';
            
            $data = json_decode($response, true);
            if ($data) {
                echo '<p class="success">JSON parsed successfully</p>';
                
                if (isset($data['access_token'])) {
                    echo '<p class="success">Successfully obtained access token!</p>';
                    echo '<p>Token Type: ' . ($data['token_type'] ?? 'unknown') . '</p>';
                    echo '<p>Expires In: ' . ($data['expires_in'] ?? 'unknown') . ' seconds</p>';
                    
                    // Ask if user wants to save this token
                    echo '<form method="post">
                        <input type="hidden" name="access_token" value="' . htmlspecialchars($data['access_token']) . '">
                        <input type="hidden" name="expires_in" value="' . (int)($data['expires_in'] ?? 0) . '">
                        <button type="submit" name="action" value="save_token" style="padding: 10px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer; margin-top: 10px;">
                            Save This Token
                        </button>
                    </form>';
                } else {
                    echo '<p class="error">No access token in the response!</p>';
                    if (isset($data['error'])) {
                        echo '<p class="error">Error: ' . $data['error'] . '</p>';
                        echo '<p class="error">Description: ' . ($data['error_description'] ?? 'No description') . '</p>';
                    }
                }
                
                echo '<h3>Full Response:</h3>';
                echo '<pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
            } else {
                echo '<p class="error">Failed to parse JSON response</p>';
                echo '<pre>' . htmlspecialchars($response) . '</pre>';
            }
        } else {
            echo '<p class="error">No response received from the API</p>';
        }
    }
    
    echo '</div>';
}

// Save token if requested
if (isset($_POST['action']) && $_POST['action'] === 'save_token') {
    echo '<div class="section">
        <h2>Save Token Results</h2>';
    
    $access_token = $_POST['access_token'];
    $expires_in = (int)$_POST['expires_in'];
    
    if (empty($access_token)) {
        echo '<p class="error">No access token provided!</p>';
    } else {
        // Update the access token in the database
        updateSetting('ebay_access_token', $access_token);
        
        // Also update the token expiry time
        $expires_at = time() + $expires_in;
        updateSetting('ebay_token_expires_at', $expires_at);
        
        echo '<p class="success">Access token saved successfully!</p>';
        echo '<p>Token will expire at: ' . date('Y-m-d H:i:s', $expires_at) . '</p>';
    }
    
    echo '</div>';
}

// Test standard refresh
echo '<div class="section">
    <h2>Test Standard Refresh</h2>
    <p>Click the button below to test the standard token refresh function:</p>
    <form method="post">
        <button type="submit" name="action" value="test_refresh" style="padding: 10px 15px; background-color: #2196F3; color: white; border: none; cursor: pointer;">
            Test Standard Refresh
        </button>
    </form>
</div>';

// Process standard refresh test
if (isset($_POST['action']) && $_POST['action'] === 'test_refresh') {
    echo '<div class="section">
        <h2>Standard Refresh Results</h2>';
    
    $result = refreshEbayAccessToken();
    
    if ($result['success']) {
        echo '<p class="success">Token refreshed successfully!</p>';
        echo '<p>Message: ' . $result['message'] . '</p>';
        echo '<p>Expires In: ' . $result['expires_in'] . ' seconds</p>';
        echo '<p>Token Type: ' . $result['token_type'] . '</p>';
    } else {
        echo '<p class="error">Token refresh failed!</p>';
        echo '<p>Error: ' . $result['message'] . '</p>';
        
        if (isset($result['response'])) {
            echo '<h3>Response Details:</h3>';
            if (is_array($result['response'])) {
                echo '<pre>' . htmlspecialchars(json_encode($result['response'], JSON_PRETTY_PRINT)) . '</pre>';
            } else {
                echo '<pre>' . htmlspecialchars($result['response']) . '</pre>';
            }
        }
    }
    
    echo '</div>';
}

// Raw curl test
echo '<div class="section">
    <h2>Raw cURL Test</h2>
    <p>This test performs a basic cURL request to the eBay OAuth endpoint to check connectivity:</p>
    <form method="post">
        <button type="submit" name="action" value="curl_test" style="padding: 10px 15px; background-color: #9C27B0; color: white; border: none; cursor: pointer;">
            Run cURL Test
        </button>
    </form>
</div>';

// Process curl test
if (isset($_POST['action']) && $_POST['action'] === 'curl_test') {
    echo '<div class="section">
        <h2>cURL Test Results</h2>';
    
    $ch = curl_init('https://api.ebay.com/identity/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo '<p>HTTP Response Code: ' . $http_code . '</p>';
    
    if (!empty($curl_error)) {
        echo '<p class="error">cURL Error: ' . $curl_error . '</p>';
    } else {
        echo '<p class="success">Connection to eBay OAuth endpoint successful!</p>';
    }
    
    echo '<h3>Response Headers:</h3>';
    echo '<pre>' . htmlspecialchars($response) . '</pre>';
    
    echo '</div>';
}

// Check error log
echo '<div class="section">
    <h2>Error Log</h2>';

$log_file = 'ebay_token_debug.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    if (!empty($log_content)) {
        echo '<pre>' . htmlspecialchars($log_content) . '</pre>';
    } else {
        echo '<p>No errors logged.</p>';
    }
} else {
    echo '<p>Log file does not exist yet.</p>';
}

echo '</div>';

// HTML footer
echo '</body>
</html>';
?>
