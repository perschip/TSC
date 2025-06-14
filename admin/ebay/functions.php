<?php

// Function to sync eBay listings
function syncEbayListings() {
    try {
        // Get access token
        $access_token = getSetting('ebay_access_token');
        if (empty($access_token)) {
            throw new Exception('No valid access token found');
        }

        // Get seller ID
        $seller_id = getSetting('ebay_seller_id');
        if (empty($seller_id)) {
            throw new Exception('No seller ID configured');
        }

        // Initialize curl
        $ch = curl_init();
        
        // Get active listings
        $url = 'https://api.ebay.com/sell/inventory/v1/inventory_item';
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
            'X-EBAY-C-MARKETPLACE-ID: EBAY-US'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            throw new Exception('API request failed with HTTP ' . $httpCode . ': ' . curl_error($ch));
        }

        $listings = json_decode($response, true);
        
        // Process listings
        $count = 0;
        foreach ($listings['inventory_items'] as $listing) {
            // Save listing data to database
            $sku = $listing['sku'];
            $title = $listing['title'];
            $price = $listing['price'];
            $quantity = $listing['quantity'];
            
            // Update or insert listing
            $sql = "INSERT INTO ebay_listings (sku, title, price, quantity, seller_id, last_updated) 
                    VALUES (?, ?, ?, ?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), 
                    price = VALUES(price), 
                    quantity = VALUES(quantity), 
                    last_updated = VALUES(last_updated)";
            
            $stmt = $GLOBALS['pdo']->prepare($sql);
            $stmt->execute([$sku, $title, $price, $quantity, $seller_id]);
            
            $count++;
        }

        curl_close($ch);
        
        // Update last sync time
        updateSetting('ebay_last_sync', date('Y-m-d H:i:s'));
        
        return [
            'success' => true,
            'count' => $count,
            'message' => "Successfully synced $count listings"
        ];
        
    } catch (Exception $e) {
        error_log('eBay Sync Error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Function to test eBay connection
function testEbayConnection($app_id, $sandbox = false) {
    try {
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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['ack']) && $result['ack'] === 'Success') {
                return [
                    'success' => true,
                    'message' => 'Connection successful'
                ];
            }
        }

        throw new Exception('Invalid response from eBay API');

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Function to get seller ID from eBay
function getEbaySellerId($access_token) {
    try {
        $ch = curl_init();
        $url = 'https://api.ebay.com/sell/account/v1/user';
        
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
            'X-EBAY-C-MARKETPLACE-ID: EBAY-US'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['seller_id'] ?? null;
        }

        throw new Exception('Failed to get seller ID: HTTP ' . $httpCode);

    } catch (Exception $e) {
        error_log('eBay Seller ID Error: ' . $e->getMessage());
        return null;
    }
}

// Function to refresh eBay token
function refreshEbayToken() {
    try {
        $refresh_token = getSetting('ebay_refresh_token');
        if (empty($refresh_token)) {
            throw new Exception('No refresh token available');
        }

        $appId = 'PaulPers-TSCBOT-PRD-c0e716bda-e070fe46';
        $certId = getSetting('ebay_cert_id');
        
        if (empty($certId)) {
            throw new Exception('No client secret available');
        }

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($appId . ':' . $certId)
        ];

        $postData = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'scope' => implode(' ', [
                'https://api.ebay.com/oauth/api_scope',
                'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly',
                'https://api.ebay.com/oauth/api_scope/sell.marketing',
                'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
                'https://api.ebay.com/oauth/api_scope/sell.inventory',
                'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
                'https://api.ebay.com/oauth/api_scope/sell.account',
                'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
                'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
                'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
                'https://api.ebay.com/oauth/api_scope/sell.finances',
                'https://api.ebay.com/oauth/api_scope/sell.payment.dispute',
                'https://api.ebay.com/oauth/api_scope/commerce.identity.readonly',
                'https://api.ebay.com/oauth/api_scope/sell.reputation',
                'https://api.ebay.com/oauth/api_scope/sell.reputation.readonly',
                'https://api.ebay.com/oauth/api_scope/commerce.notification.subscription',
                'https://api.ebay.com/oauth/api_scope/commerce.notification.subscription.readonly',
                'https://api.ebay.com/oauth/api_scope/sell.stores',
                'https://api.ebay.com/oauth/api_scope/sell.stores.readonly',
                'https://api.ebay.com/oauth/scope/sell.edelivery'
            ])
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.ebay.com/identity/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Token refresh failed: HTTP ' . $httpCode);
        }

        $tokenData = json_decode($response, true);
        
        // Save new tokens
        updateSetting('ebay_access_token', $tokenData['access_token']);
        updateSetting('ebay_refresh_token', $tokenData['refresh_token']);
        updateSetting('ebay_token_expires', time() + ($tokenData['expires_in'] ?? 7200));
        updateSetting('ebay_oauth_last_verified', date('Y-m-d H:i:s'));

        return true;

    } catch (Exception $e) {
        error_log('eBay Token Refresh Error: ' . $e->getMessage());
        return false;
    }
}

// Function to check if token needs refreshing
function checkEbayToken() {
    $token_expires = (int)getSetting('ebay_token_expires');
    if ($token_expires < time() + 300) { // Refresh if token expires in less than 5 minutes
        return refreshEbayToken();
    }
    return true;
}
