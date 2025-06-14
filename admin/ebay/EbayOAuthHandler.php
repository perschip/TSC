<?php
namespace TristateCards\Ebay;

class EbayOAuthHandler {
    private $clientId;
    private $clientSecret;
    private $ruName;
    private $sandbox;
    private $pdo;
    
    private $authUrlProduction = 'https://auth.ebay.com/oauth2/authorize';
    private $authUrlSandbox = 'https://auth.sandbox.ebay.com/oauth2/authorize';
    private $tokenUrlProduction = 'https://api.ebay.com/identity/v1/oauth2/token';
    private $tokenUrlSandbox = 'https://api.sandbox.ebay.com/identity/v1/oauth2/token';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->clientId = $this->getSetting('ebay_app_id');
        $this->clientSecret = $this->getSetting('ebay_cert_id');
        $this->ruName = $this->getSetting('ebay_ru_name');
        $this->sandbox = (bool)$this->getSetting('ebay_sandbox_mode');
    }
    
    private function getSetting($key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE name = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['value'] : $default;
    }
    
    private function setSetting($key, $value) {
        $stmt = $this->pdo->prepare("
            INSERT INTO settings (name, value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    }
    
    public function initiateAuth() {
        if (!$this->validateCredentials()) {
            throw new \Exception('Missing required eBay API credentials');
        }
        
        $state = bin2hex(random_bytes(16));
        $_SESSION['ebay_oauth_state'] = $state;
        
        $authUrl = $this->getAuthUrl($state);
        header('Location: ' . $authUrl);
        exit;
    }
    
    private function validateCredentials() {
        return !empty($this->clientId) && !empty($this->clientSecret) && !empty($this->ruName);
    }
    
    private function getAuthUrl($state) {
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
            ]),
            'state' => $state
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }
    
    public function handleCallback($code, $state) {
        if (!isset($_SESSION['ebay_oauth_state']) || $_SESSION['ebay_oauth_state'] !== $state) {
            throw new \Exception('Invalid or expired OAuth state');
        }
        
        $tokenData = $this->getAccessToken($code);
        
        if (!$tokenData) {
            throw new \Exception('Failed to get access token');
        }
        
        $this->saveTokens($tokenData);
        return true;
    }
    
    private function getAccessToken($code) {
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
        }
        
        error_log('eBay OAuth Token Error: ' . $response);
        return false;
    }
    
    private function saveTokens($tokenData) {
        $this->setSetting('ebay_access_token', $tokenData['access_token']);
        $this->setSetting('ebay_refresh_token', $tokenData['refresh_token']);
        $this->setSetting('ebay_token_expires', time() + ($tokenData['expires_in'] ?? 7200));
        $this->setSetting('ebay_oauth_connected', '1');
        $this->setSetting('ebay_oauth_last_verified', date('Y-m-d H:i:s'));
        
        // Clear session state
        unset($_SESSION['ebay_oauth_state']);
    }
    
    public function refreshAccessToken() {
        $refreshToken = $this->getSetting('ebay_refresh_token');
        if (empty($refreshToken)) {
            return false;
        }
        
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
            $tokenData = json_decode($response, true);
            $this->saveTokens($tokenData);
            return true;
        }
        
        error_log('eBay OAuth Refresh Error: ' . $response);
        return false;
    }
    
    public function disconnect() {
        $this->setSetting('ebay_access_token', '');
        $this->setSetting('ebay_refresh_token', '');
        $this->setSetting('ebay_token_expires', '0');
        $this->setSetting('ebay_oauth_connected', '0');
        $this->setSetting('ebay_user_token', '');
        
        unset($_SESSION['ebay_oauth_state']);
        return true;
    }
}
