<?php
/**
 * eBay OAuth Authentication Callback Handler
 * 
 * This script handles the callback from eBay's OAuth authorization process.
 * It exchanges the authorization code for an access token and refresh token.
 */

// Include necessary files
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
session_start();

// Initialize variables
$error = '';
$success = false;

// Check if we received an authorization code from eBay
if (isset($_GET['code']) && isset($_GET['state']) && $_GET['state'] === 'ebay_auth') {
    $auth_code = $_GET['code'];
    
    // Get the OAuth credentials
    $client_id = getSetting('ebay_client_id');
    $client_secret = getSetting('ebay_client_secret');
    $runame = getSetting('ebay_runame', 'Paul_Perschilli-PaulPers-TSCBOT-gyqfbjjy');
    $sandbox = (bool)getSetting('ebay_sandbox_mode', 0);
    
    // Verify we have the required credentials
    if (empty($client_id) || empty($client_secret)) {
        $error = 'Missing eBay OAuth credentials. Please configure them in settings.';
    } else {
        // Set up the token request
        $token_url = 'https://api.ebay.com/identity/v1/oauth2/token';
        if ($sandbox) {
            $token_url = 'https://api.sandbox.ebay.com/identity/v1/oauth2/token';
        }
        
        // Set up cURL request to exchange auth code for tokens
        $ch = curl_init($token_url);
        
        // Set the authorization header with Base64 encoded client credentials
        $credentials = base64_encode($client_id . ':' . $client_secret);
        
        // Set up the request parameters
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $credentials
        ]);
        
        // Set the request body - using RuName as the redirect_uri like in the original callback
        $postFields = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $auth_code,
            'redirect_uri' => $runame  // Use RuName directly as the redirect_uri
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        
        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Log the request details for debugging
        error_log("eBay OAuth Token Request - HTTP Code: $http_code");
        
        // Check for errors
        if (!$response) {
            $error = 'cURL error: ' . $curl_error;
            error_log("eBay OAuth Error - No response received: $curl_error");
        } else {
            // Parse the response
            $token_data = json_decode($response, true);
            
            // Check if we got a valid response
            if (isset($token_data['error'])) {
                $error = 'eBay API Error: ' . $token_data['error'] . ' - ' . ($token_data['error_description'] ?? 'Unknown error');
                error_log("eBay OAuth Error: " . json_encode($token_data));
            } else if (isset($token_data['access_token']) && isset($token_data['refresh_token'])) {
                // Save the tokens
                updateSetting('ebay_access_token', $token_data['access_token']);
                updateSetting('ebay_refresh_token', $token_data['refresh_token']);
                
                // Calculate and save the expiration time
                $expires_in = $token_data['expires_in'] ?? 7200; // Default to 2 hours
                $expires_at = time() + $expires_in;
                updateSetting('ebay_token_expires_at', $expires_at);
                
                // Set the OAuth connected flag
                updateSetting('ebay_oauth_connected', 1);
                
                // Success!
                $success = true;
                $_SESSION['success_message'] = 'Successfully connected to eBay! Your access token will expire on ' . date('M j, Y g:i a', $expires_at);
                
                // Log success
                error_log("eBay OAuth Success - Token expires at: " . date('Y-m-d H:i:s', $expires_at));
            } else {
                $error = 'Invalid response from eBay. Missing access_token or refresh_token.';
                error_log("eBay OAuth Error - Invalid response: " . json_encode($token_data));
            }
        }
    }
} else if (isset($_GET['error'])) {
    // eBay returned an error
    $error = 'eBay authorization error: ' . $_GET['error'] . ' - ' . ($_GET['error_description'] ?? 'Unknown error');
    error_log("eBay OAuth Error from eBay: " . $_GET['error']);
} else {
    // No code or error received
    $error = 'Invalid callback. Missing authorization code.';
    error_log("eBay OAuth Error - Invalid callback: " . json_encode($_GET));
}

// Redirect back to settings page with appropriate message
if (!$success && !empty($error)) {
    $_SESSION['error_message'] = $error;
}

// Redirect back to settings page
header('Location: settings.php');
exit;
?>
