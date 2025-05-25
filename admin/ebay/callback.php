<?php
// admin/ebay/callback.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// eBay OAuth Configuration
class EbayOAuth {
    private $clientId;
    private $clientSecret;
    private $ruName;
    private $sandbox;
    
    // OAuth URLs
    private $authUrlProduction = 'https://auth.ebay.com/oauth2/authorize';
    private $authUrlSandbox = 'https://auth.sandbox.ebay.com/oauth2/authorize';
    private $tokenUrlProduction = 'https://api.ebay.com/identity/v1/oauth2/token';
    private $tokenUrlSandbox = 'https://api.sandbox.ebay.com/identity/v1/oauth2/token';
    
    public function __construct($clientId, $clientSecret, $ruName, $sandbox = false) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->ruName = $ruName;
        $this->sandbox = $sandbox;
    }
    
    // Generate the authorization URL
    public function getAuthUrl($state = null) {
        $baseUrl = $this->sandbox ? $this->authUrlSandbox : $this->authUrlProduction;
        
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->ruName,
            'scope' => implode(' ', [
                'https://api.ebay.com/oauth/api_scope/sell.inventory',
                'https://api.ebay.com/oauth/api_scope/sell.account',
                'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
                'https://api.ebay.com/oauth/api_scope/sell.marketing',
                'https://api.ebay.com/oauth/api_scope/commerce.catalog.readonly'
            ])
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return $baseUrl . '?' . http_build_query($params);
    }
    
    // Exchange authorization code for access token
    public function getAccessToken($code) {
        $url = $this->sandbox ? $this->tokenUrlSandbox : $this->tokenUrlProduction;
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)
        ];
        
        $postData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->ruName
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            error_log('eBay OAuth Token Error: ' . $response);
            return false;
        }
    }
    
    // Refresh an access token
    public function refreshAccessToken($refreshToken) {
        $url = $this->sandbox ? $this->tokenUrlSandbox : $this->tokenUrlProduction;
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)
        ];
        
        $postData = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            error_log('eBay OAuth Refresh Error: ' . $response);
            return false;
        }
    }
}

// Handle the OAuth callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $state = $_GET['state'] ?? null;
    
    // Get eBay settings
    $clientId = getSetting('ebay_app_id');
    $clientSecret = getSetting('ebay_cert_id');
    $ruName = getSetting('ebay_ru_name');
    $sandbox = (bool)getSetting('ebay_sandbox_mode');
    
    if (!$clientId || !$clientSecret || !$ruName) {
        $_SESSION['error_message'] = 'eBay OAuth configuration is incomplete. Please check your settings.';
        header('Location: settings.php');
        exit;
    }
    
    // Initialize OAuth handler
    $oauth = new EbayOAuth($clientId, $clientSecret, $ruName, $sandbox);
    
    // Exchange code for token
    $tokenData = $oauth->getAccessToken($code);
    
    if ($tokenData && isset($tokenData['access_token'])) {
        // Save tokens to database
        updateSetting('ebay_access_token', $tokenData['access_token']);
        updateSetting('ebay_refresh_token', $tokenData['refresh_token'] ?? '');
        updateSetting('ebay_token_expires', time() + ($tokenData['expires_in'] ?? 7200));
        updateSetting('ebay_oauth_connected', '1');
        
        $_SESSION['success_message'] = 'Successfully connected to eBay! You can now access your listings and account data.';
        
        // Optionally, fetch user info to verify connection
        fetchEbayUserInfo($tokenData['access_token'], $sandbox);
        
    } else {
        $_SESSION['error_message'] = 'Failed to connect to eBay. Please try again.';
    }
    
    header('Location: settings.php');
    exit;
    
} elseif (isset($_GET['error'])) {
    // Handle OAuth errors
    $error = $_GET['error'];
    $errorDescription = $_GET['error_description'] ?? 'Unknown error';
    
    $_SESSION['error_message'] = "eBay OAuth error: $error - $errorDescription";
    header('Location: settings.php');
    exit;
    
} else {
    // No code or error - redirect to settings
    header('Location: settings.php');
    exit;
}

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