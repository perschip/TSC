<?php
// admin/ebay/diagnose_ebay.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once 'token_refresh.php';

// Start HTML output
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eBay Integration Diagnostic</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-ok { color: green; }
        .status-warning { color: orange; }
        .status-error { color: red; }
        .code-block { 
            background-color: #f5f5f5; 
            padding: 10px; 
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>eBay Integration Diagnostic</h1>';

// Check for required credentials
$credentials = [
    'ebay_app_id' => getSetting('ebay_app_id', ''),
    'ebay_cert_id' => getSetting('ebay_cert_id', ''),
    'ebay_dev_id' => getSetting('ebay_dev_id', ''),
    'ebay_client_id' => getSetting('ebay_client_id', ''),
    'ebay_client_secret' => getSetting('ebay_client_secret', ''),
    'ebay_refresh_token' => getSetting('ebay_refresh_token', ''),
    'ebay_access_token' => getSetting('ebay_access_token', ''),
    'ebay_seller_id' => getSetting('ebay_seller_id', ''),
    'ebay_sandbox_mode' => getSetting('ebay_sandbox_mode', '0'),
    'ebay_token_expires_at' => getSetting('ebay_token_expires_at', '0')
];

echo '<div class="card mb-4">
    <div class="card-header">
        <h5>eBay Credentials Status</h5>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Credential</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

foreach ($credentials as $key => $value) {
    $status = !empty($value) ? 'OK' : 'Missing';
    $status_class = !empty($value) ? 'status-ok' : 'status-error';
    
    // Special handling for token expiry
    if ($key === 'ebay_token_expires_at' && !empty($value)) {
        $expires_at = (int)$value;
        if ($expires_at < time()) {
            $status = 'Expired (' . date('Y-m-d H:i:s', $expires_at) . ')';
            $status_class = 'status-error';
        } else {
            $status = 'Valid until ' . date('Y-m-d H:i:s', $expires_at);
            $status_class = 'status-ok';
        }
    }
    
    // Mask sensitive values
    $display_value = $value;
    if (in_array($key, ['ebay_client_secret', 'ebay_refresh_token', 'ebay_access_token'])) {
        $display_value = !empty($value) ? substr($value, 0, 5) . '...' . substr($value, -5) : '';
    }
    
    echo "<tr>
            <td>{$key}</td>
            <td class=\"{$status_class}\">{$status}</td>
          </tr>";
}

echo '</tbody>
        </table>
    </div>
</div>';

// Check token status and try to refresh if needed
echo '<div class="card mb-4">
    <div class="card-header">
        <h5>OAuth Token Status</h5>
    </div>
    <div class="card-body">';

$token_expired = empty($credentials['ebay_access_token']) || 
                ((int)$credentials['ebay_token_expires_at'] < time());

if ($token_expired) {
    echo '<p class="status-warning">Token is expired or missing. Attempting to refresh...</p>';
    
    // Try to refresh the token
    $refresh_result = refreshEbayAccessToken();
    
    if ($refresh_result['success']) {
        echo '<p class="status-ok">Token refreshed successfully!</p>';
        $credentials['ebay_access_token'] = getSetting('ebay_access_token', '');
        $credentials['ebay_token_expires_at'] = getSetting('ebay_token_expires_at', '0');
    } else {
        echo '<p class="status-error">Token refresh failed: ' . htmlspecialchars($refresh_result['message']) . '</p>';
        
        // Show more detailed error information
        if (isset($refresh_result['response'])) {
            echo '<div class="code-block">' . htmlspecialchars(print_r($refresh_result['response'], true)) . '</div>';
        }
    }
} else {
    echo '<p class="status-ok">Token is valid until ' . date('Y-m-d H:i:s', (int)$credentials['ebay_token_expires_at']) . '</p>';
}

echo '</div>
</div>';

// Test API connection
echo '<div class="card mb-4">
    <div class="card-header">
        <h5>API Connection Test</h5>
    </div>
    <div class="card-body">';

if (empty($credentials['ebay_access_token'])) {
    echo '<p class="status-error">Cannot test API connection: No access token available</p>';
} else if (empty($credentials['ebay_seller_id'])) {
    echo '<p class="status-error">Cannot test API connection: No seller ID available</p>';
} else {
    echo '<p>Testing Browse API connection...</p>';
    
    // Test the Browse API
    $url = 'https://api.ebay.com/buy/browse/v1/item_summary/search?q=seller:' . 
           urlencode($credentials['ebay_seller_id']) . '&limit=1';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $credentials['ebay_access_token'],
        'X-EBAY-C-MARKETPLACE-ID: EBAY_US',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo '<p class="status-error">Connection error: ' . htmlspecialchars($curl_error) . '</p>';
    } else if ($http_code == 200) {
        echo '<p class="status-ok">API connection successful (HTTP 200)</p>';
        
        // Parse the response to see if we got any items
        $data = json_decode($response, true);
        if (isset($data['itemSummaries']) && !empty($data['itemSummaries'])) {
            echo '<p class="status-ok">Found ' . count($data['itemSummaries']) . ' item(s) for seller ID: ' . 
                 htmlspecialchars($credentials['ebay_seller_id']) . '</p>';
        } else {
            echo '<p class="status-warning">No items found for seller ID: ' . 
                 htmlspecialchars($credentials['ebay_seller_id']) . '</p>';
        }
    } else {
        echo '<p class="status-error">API error: HTTP ' . $http_code . '</p>';
        
        // Try to extract error details
        $error_data = json_decode($response, true);
        if (isset($error_data['errors']) && is_array($error_data['errors']) && !empty($error_data['errors'])) {
            echo '<p class="status-error">Error message: ' . 
                 htmlspecialchars($error_data['errors'][0]['message'] ?? 'Unknown error') . '</p>';
        }
        
        // Show the raw response for debugging
        echo '<h6>Raw API Response:</h6>';
        echo '<div class="code-block">' . htmlspecialchars($response) . '</div>';
    }
}

echo '</div>
</div>';

// Database table check
echo '<div class="card mb-4">
    <div class="card-header">
        <h5>Database Table Check</h5>
    </div>
    <div class="card-body">';

try {
    // Use the global $pdo connection that was already established in includes/db.php
    global $pdo;
    
    if (!$pdo) {
        echo '<p class="status-error">No database connection available</p>';
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'ebay_listings'");
        
        if ($stmt && $stmt->rowCount() > 0) {
            echo '<p class="status-ok">ebay_listings table exists</p>';
            
            // Check the table structure
            $stmt = $pdo->query("DESCRIBE ebay_listings");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo '<p>Table columns: ' . implode(', ', $columns) . '</p>';
            
            // Check if we have any listings
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM ebay_listings");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo '<p>Total listings in database: ' . $count . '</p>';
            
            // Check listings by status
            $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM ebay_listings GROUP BY status");
            if ($stmt && $stmt->rowCount() > 0) {
                $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>';
                
                foreach ($status_counts as $row) {
                    echo "<tr>
                            <td>{$row['status']}</td>
                            <td>{$row['count']}</td>
                          </tr>";
                }
                
                echo '</tbody>
                      </table>';
            } else {
                echo '<p class="status-warning">No listings found in the database</p>';
            }
        } else {
            echo '<p class="status-error">ebay_listings table does not exist</p>';
        }
    }
} catch (PDOException $e) {
    echo '<p class="status-error">Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</div>
</div>';

// Seller ID verification
echo '<div class="card mb-4">
    <div class="card-header">
        <h5>Seller ID Verification</h5>
    </div>
    <div class="card-body">';    

if (!empty($credentials['ebay_seller_id'])) {
    $seller_id = $credentials['ebay_seller_id'];
    echo '<p>Checking seller ID: <strong>' . htmlspecialchars($seller_id) . '</strong></p>';
    
    // Try to verify the seller ID by checking eBay's website
    echo '<p>You can verify this seller ID by checking the following URL:</p>';
    echo '<p><a href="https://www.ebay.com/usr/' . urlencode($seller_id) . '" target="_blank" class="btn btn-sm btn-info">View eBay Seller Profile</a></p>';
    
    // Try both API query formats
    if (!empty($credentials['ebay_access_token'])) {
        echo '<h6 class="mt-4">Testing alternative API query formats:</h6>';
        
        // Format 1: q=seller:ID
        $url1 = 'https://api.ebay.com/buy/browse/v1/item_summary/search?q=seller:' . urlencode($seller_id) . '&limit=1';
        echo '<p>Testing format 1: <code>' . htmlspecialchars($url1) . '</code></p>';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $credentials['ebay_access_token'],
            'X-EBAY-C-MARKETPLACE-ID: EBAY_US',
            'Content-Type: application/json'
        ]);
        $response1 = curl_exec($ch);
        $http_code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data1 = json_decode($response1, true);
        $count1 = isset($data1['itemSummaries']) ? count($data1['itemSummaries']) : 0;
        
        echo '<p>Result: HTTP ' . $http_code1 . ', Items found: ' . $count1 . '</p>';
        
        // Format 2: filter=sellers:{ID}
        $url2 = 'https://api.ebay.com/buy/browse/v1/item_summary/search?filter=sellers:{' . urlencode($seller_id) . '}&limit=1';
        echo '<p>Testing format 2: <code>' . htmlspecialchars($url2) . '</code></p>';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $credentials['ebay_access_token'],
            'X-EBAY-C-MARKETPLACE-ID: EBAY_US',
            'Content-Type: application/json'
        ]);
        $response2 = curl_exec($ch);
        $http_code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data2 = json_decode($response2, true);
        $count2 = isset($data2['itemSummaries']) ? count($data2['itemSummaries']) : 0;
        
        echo '<p>Result: HTTP ' . $http_code2 . ', Items found: ' . $count2 . '</p>';
        
        if ($count1 == 0 && $count2 == 0) {
            echo '<div class="alert alert-warning">No items found with either query format. This could indicate:<br>
                  1. The seller ID is incorrect<br>
                  2. The seller has no active listings<br>
                  3. The seller account is using a different marketplace than EBAY_US</div>';
        } else {
            echo '<div class="alert alert-success">Found ' . max($count1, $count2) . ' items! The sync should work now.</div>';
        }
    }
    
    // Common issues with seller IDs
    echo '<div class="alert alert-info">
            <h6>Common Seller ID Issues:</h6>
            <ul>
                <li>Make sure you are using your seller ID, not your username or email</li>
                <li>Seller IDs are case-sensitive, check capitalization</li>
                <li>Verify you do not have any spaces before or after your seller ID</li>
                <li>If you have multiple eBay accounts, make sure you are using the correct one</li>
                <li>If you recently changed your seller ID, it may take time to update</li>
            </ul>
          </div>';
    
    // Alternative approach suggestion
    echo '<div class="mt-3">
            <h6>Alternative Approach:</h6>
            <p>If you continue having issues with the API, you can use the CSV Creator tool to manage your eBay listings:</p>
            <ol>
                <li>Use the Image Uploader in the admin panel under Integrations > eBay to upload images</li>
                <li>Use the CSV Creator to generate properly formatted CSV files for eBay bulk uploads</li>
                <li>The CSV Creator has specialized templates for sports cards and Pokémon cards</li>
            </ol>
            <p><a href="../ebay_csv_creator.php" class="btn btn-sm btn-secondary">Go to CSV Creator</a></p>
          </div>';
} else {
    echo '<p class="status-error">No seller ID configured</p>';
}

echo '</div>
</div>';

// Next steps and recommendations
echo '<div class="card mb-4">
    <div class="card-header">
        <h5>Recommendations</h5>
    </div>
    <div class="card-body">
        <ol>';

if (empty($credentials['ebay_client_id']) || empty($credentials['ebay_client_secret'])) {
    echo '<li class="status-error">Enter your eBay Client ID and Client Secret</li>';
}

if (empty($credentials['ebay_refresh_token'])) {
    echo '<li class="status-error">Connect to eBay to obtain a refresh token</li>';
} else if (empty($credentials['ebay_access_token']) || $token_expired) {
    echo '<li class="status-error">Refresh your OAuth token</li>';
}

if (empty($credentials['ebay_seller_id'])) {
    echo '<li class="status-error">Enter your eBay Seller ID</li>';
}

echo '</ol>
        
        <div class="mt-4">
            <a href="settings.php" class="btn btn-primary">Return to eBay Settings</a>
        </div>
    </div>
</div>';

echo '</div>
</body>
</html>';
?>
