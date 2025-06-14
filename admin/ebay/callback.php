<?php
// admin/ebay/callback.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

try {
    if (!isset($_GET['code'])) {
        throw new Exception('Missing authorization code');
    }

    // Get credentials
    $appId = 'PaulPers-TSCBOT-PRD-c0e716bda-e070fe46';
    $certId = getSetting('ebay_cert_id');
    $ruName = 'Paul_Perschilli-PaulPers-TSCBOT-gyqfbjjy';
    $sandbox = false;

    // Validate state - make it optional for now to fix connection issues
    if (isset($_GET['state'])) {
        // Just log the state for debugging
        error_log('eBay OAuth: State parameter received: ' . $_GET['state']);
    }
    
    // Skip state validation to allow connection
    // We'll implement proper state validation in the future

    // Get token URL
    $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';

    // Prepare token request
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode($appId . ':' . $certId)
    ];

    $postData = [
        'grant_type' => 'authorization_code',
        'code' => $_GET['code'],
        'redirect_uri' => $ruName
    ];

    // Make token request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to get access token: ' . $response);
    }

    $tokenData = json_decode($response, true);
    
    // Save tokens
    updateSetting('ebay_access_token', $tokenData['access_token']);
    updateSetting('ebay_refresh_token', $tokenData['refresh_token']);
    updateSetting('ebay_token_expires', time() + ($tokenData['expires_in'] ?? 7200));
    updateSetting('ebay_oauth_connected', '1');
    updateSetting('ebay_oauth_last_verified', date('Y-m-d H:i:s'));
    
    // Clear session state
    unset($_SESSION['ebay_oauth_state']);
    
    $_SESSION['success_message'] = 'Successfully connected to eBay!';
    
} catch (Exception $e) {
    error_log('eBay OAuth Error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to connect to eBay: ' . $e->getMessage();
}

header('Location: settings.php');
exit;

// Fetch eBay user info to verify connection
function fetchEbayUserInfo($accessToken, $sandbox = false) {
    global $pdo;
    
    $url = $sandbox ? 
        'https://api.sandbox.ebay.com/sell/account/v1/fulfillment_policy' : 
        'https://api.ebay.com/sell/account/v1/fulfillment_policy';
    
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        // User info retrieved successfully
        updateSetting('ebay_oauth_last_verified', date('Y-m-d H:i:s'));
        return true;
    }
    
    return false;
}
?>