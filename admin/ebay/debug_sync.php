<?php
// Debug script for eBay sync
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'sync_helper.php';

// Set error reporting to maximum
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'ebay_sync_debug.log');

// Get PDO connection from the included db.php file
// The $pdo variable is already defined in the included db.php file

// Get eBay credentials
$access_token = getSetting('ebay_access_token');
$seller_id = getSetting('ebay_seller_id');
$app_id = getSetting('ebay_app_id');
$dev_id = getSetting('ebay_dev_id');
$cert_id = getSetting('ebay_cert_id');
$user_token = getSetting('ebay_user_token');

// HTML header
echo '<!DOCTYPE html>
<html>
<head>
    <title>eBay Sync Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>eBay Sync Debug Tool</h1>';

// Check credentials
echo '<div class="section">
    <h2>eBay API Credentials</h2>';

if (empty($access_token)) {
    echo '<p class="error">No access token found!</p>';
} else {
    echo '<p class="success">Access token found: ' . substr($access_token, 0, 10) . '...</p>';
}

if (empty($seller_id)) {
    echo '<p class="error">No seller ID configured!</p>';
} else {
    echo '<p class="success">Seller ID: ' . $seller_id . '</p>';
}

if (empty($app_id) || empty($dev_id) || empty($cert_id)) {
    echo '<p class="error">Missing one or more API credentials (App ID, Dev ID, or Cert ID)</p>';
} else {
    echo '<p class="success">API credentials found</p>';
}

if (empty($user_token)) {
    echo '<p class="error">No user token found!</p>';
} else {
    echo '<p class="success">User token found: ' . substr($user_token, 0, 10) . '...</p>';
}

echo '</div>';

// Test Trading API
echo '<div class="section">
    <h2>Testing Trading API</h2>';

if (!empty($app_id) && !empty($user_token) && !empty($seller_id)) {
    // Use GetSellerList call to get active listings
    $xml = '<?xml version="1.0" encoding="utf-8"?>
    <GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <RequesterCredentials>
            <eBayAuthToken>' . $user_token . '</eBayAuthToken>
        </RequesterCredentials>
        <UserID>' . $seller_id . '</UserID>
        <DetailLevel>ReturnAll</DetailLevel>
        <GranularityLevel>Fine</GranularityLevel>
        <StartTimeFrom>' . gmdate('Y-m-d\TH:i:s.\0\0\0\Z', strtotime('-30 days')) . '</StartTimeFrom>
        <StartTimeTo>' . gmdate('Y-m-d\TH:i:s.\0\0\0\Z') . '</StartTimeTo>
        <IncludeWatchCount>true</IncludeWatchCount>
        <Pagination>
            <EntriesPerPage>10</EntriesPerPage>
            <PageNumber>1</PageNumber>
        </Pagination>
    </GetSellerListRequest>';
    
    $headers = [
        'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
        'X-EBAY-API-DEV-NAME: ' . $dev_id,
        'X-EBAY-API-APP-NAME: ' . $app_id,
        'X-EBAY-API-CERT-NAME: ' . $cert_id,
        'X-EBAY-API-CALL-NAME: GetSellerList',
        'X-EBAY-API-SITEID: 0',
        'Content-Type: text/xml'
    ];
    
    $ch = curl_init('https://api.ebay.com/ws/api.dll');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo '<p>HTTP Response Code: ' . $http_code . '</p>';
    
    if (!empty($curl_error)) {
        echo '<p class="error">cURL Error: ' . $curl_error . '</p>';
    }
    
    if ($response) {
        echo '<p class="success">Response received (length: ' . strlen($response) . ' bytes)</p>';
        
        $xml_response = simplexml_load_string($response);
        if ($xml_response) {
            echo '<p class="success">XML parsed successfully</p>';
            
            if (isset($xml_response->Errors) && count($xml_response->Errors) > 0) {
                echo '<p class="error">API Errors:</p>';
                echo '<pre>';
                foreach ($xml_response->Errors as $error) {
                    echo 'Error ' . $error->ErrorCode . ': ' . $error->ShortMessage . ' - ' . $error->LongMessage . "\n";
                }
                echo '</pre>';
            } else {
                echo '<p class="success">No API errors reported</p>';
            }
            
            if (isset($xml_response->ItemArray) && isset($xml_response->ItemArray->Item)) {
                $items_count = count($xml_response->ItemArray->Item);
                echo '<p class="success">Found ' . $items_count . ' items</p>';
                
                if ($items_count > 0) {
                    echo '<table>';
                    echo '<tr><th>Item ID</th><th>Title</th><th>Price</th><th>Quantity</th></tr>';
                    
                    $count = 0;
                    foreach ($xml_response->ItemArray->Item as $item) {
                        if ($count >= 5) break; // Show only first 5 items
                        
                        $price = 0;
                        if (isset($item->StartPrice)) {
                            $price = (float)$item->StartPrice;
                        } elseif (isset($item->CurrentPrice)) {
                            $price = (float)$item->CurrentPrice;
                        }
                        
                        $quantity = 1;
                        if (isset($item->Quantity)) {
                            $quantity = (int)$item->Quantity;
                        }
                        
                        echo '<tr>';
                        echo '<td>' . (string)$item->ItemID . '</td>';
                        echo '<td>' . (string)$item->Title . '</td>';
                        echo '<td>$' . $price . '</td>';
                        echo '<td>' . $quantity . '</td>';
                        echo '</tr>';
                        
                        $count++;
                    }
                    
                    echo '</table>';
                }
            } else {
                echo '<p class="error">No items found in the response</p>';
            }
        } else {
            echo '<p class="error">Failed to parse XML response</p>';
            echo '<pre>' . htmlspecialchars(substr($response, 0, 1000)) . '...</pre>';
        }
    } else {
        echo '<p class="error">No response received from the API</p>';
    }
} else {
    echo '<p class="error">Cannot test Trading API due to missing credentials</p>';
}

echo '</div>';

// Test Browse API
echo '<div class="section">
    <h2>Testing Browse API</h2>';

if (!empty($access_token) && !empty($seller_id)) {
    $url = 'https://api.ebay.com/buy/browse/v1/item_summary/search?q=seller:' . urlencode($seller_id) . '&limit=10';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'X-EBAY-C-MARKETPLACE-ID: EBAY_US'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo '<p>HTTP Response Code: ' . $http_code . '</p>';
    
    if (!empty($curl_error)) {
        echo '<p class="error">cURL Error: ' . $curl_error . '</p>';
    }
    
    if ($response) {
        echo '<p class="success">Response received (length: ' . strlen($response) . ' bytes)</p>';
        
        $data = json_decode($response, true);
        if ($data) {
            echo '<p class="success">JSON parsed successfully</p>';
            
            if (isset($data['errors']) && count($data['errors']) > 0) {
                echo '<p class="error">API Errors:</p>';
                echo '<pre>';
                foreach ($data['errors'] as $error) {
                    echo 'Error ' . $error['errorId'] . ': ' . $error['message'] . "\n";
                }
                echo '</pre>';
            } else {
                echo '<p class="success">No API errors reported</p>';
            }
            
            if (isset($data['itemSummaries']) && is_array($data['itemSummaries'])) {
                $items_count = count($data['itemSummaries']);
                echo '<p class="success">Found ' . $items_count . ' items</p>';
                
                if ($items_count > 0) {
                    echo '<table>';
                    echo '<tr><th>Item ID</th><th>Title</th><th>Price</th></tr>';
                    
                    $count = 0;
                    foreach ($data['itemSummaries'] as $item) {
                        if ($count >= 5) break; // Show only first 5 items
                        
                        $price = 0;
                        if (isset($item['price']) && isset($item['price']['value'])) {
                            $price = floatval($item['price']['value']);
                        }
                        
                        echo '<tr>';
                        echo '<td>' . $item['itemId'] . '</td>';
                        echo '<td>' . $item['title'] . '</td>';
                        echo '<td>$' . $price . '</td>';
                        echo '</tr>';
                        
                        $count++;
                    }
                    
                    echo '</table>';
                }
            } else {
                echo '<p class="error">No items found in the response</p>';
            }
        } else {
            echo '<p class="error">Failed to parse JSON response</p>';
            echo '<pre>' . htmlspecialchars(substr($response, 0, 1000)) . '...</pre>';
        }
    } else {
        echo '<p class="error">No response received from the API</p>';
    }
} else {
    echo '<p class="error">Cannot test Browse API due to missing credentials</p>';
}

echo '</div>';

// Check database
echo '<div class="section">
    <h2>Database Check</h2>';

try {
    // Check if table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'ebay_listings'")->rowCount() > 0;
    
    if ($tableExists) {
        echo '<p class="success">ebay_listings table exists</p>';
        
        // Check columns
        $columns = [];
        $columnsQuery = $pdo->query("SHOW COLUMNS FROM ebay_listings");
        while ($column = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $column['Field'];
        }
        
        echo '<p>Columns found: ' . implode(', ', $columns) . '</p>';
        
        if (in_array('description', $columns)) {
            echo '<p class="success">description column exists</p>';
        } else {
            echo '<p class="error">description column is missing!</p>';
        }
        
        // Count existing listings
        $count = $pdo->query("SELECT COUNT(*) FROM ebay_listings")->fetchColumn();
        echo '<p>Found ' . $count . ' existing listings in database</p>';
        
        if ($count > 0) {
            // Show sample listings
            $listings = $pdo->query("SELECT id, sku, title, price, last_updated FROM ebay_listings LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<table>';
            echo '<tr><th>ID</th><th>SKU</th><th>Title</th><th>Price</th><th>Last Updated</th></tr>';
            
            foreach ($listings as $listing) {
                echo '<tr>';
                echo '<td>' . $listing['id'] . '</td>';
                echo '<td>' . $listing['sku'] . '</td>';
                echo '<td>' . $listing['title'] . '</td>';
                echo '<td>$' . $listing['price'] . '</td>';
                echo '<td>' . $listing['last_updated'] . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        }
    } else {
        echo '<p class="error">ebay_listings table does not exist!</p>';
    }
} catch (Exception $e) {
    echo '<p class="error">Database error: ' . $e->getMessage() . '</p>';
}

echo '</div>';

// Run test sync
echo '<div class="section">
    <h2>Test Sync</h2>
    <p>Click the button below to run a test sync with detailed logging:</p>
    <form method="post">
        <input type="submit" name="run_sync" value="Run Test Sync">
    </form>';

if (isset($_POST['run_sync'])) {
    echo '<h3>Sync Results:</h3>';
    
    try {
        // Start output buffering to capture all output
        ob_start();
        
        // Run the sync
        $result = smartEbaySync($pdo);
        
        // Get the output
        $output = ob_get_clean();
        
        // Display the result
        if ($result['success']) {
            echo '<p class="success">Sync completed successfully</p>';
            echo '<p>Updated: ' . $result['updated'] . ' listings</p>';
            echo '<p>New: ' . $result['new'] . ' listings</p>';
            echo '<p>Unchanged: ' . $result['unchanged'] . ' listings</p>';
            echo '<p>Preserved: ' . $result['preserved'] . ' listings</p>';
        } else {
            echo '<p class="error">Sync failed: ' . $result['error'] . '</p>';
        }
        
        // Display any output
        if (!empty($output)) {
            echo '<h4>Output:</h4>';
            echo '<pre>' . htmlspecialchars($output) . '</pre>';
        }
        
        // Check error log
        $log_file = 'ebay_sync_debug.log';
        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            if (!empty($log_content)) {
                echo '<h4>Error Log:</h4>';
                echo '<pre>' . htmlspecialchars($log_content) . '</pre>';
            }
        }
    } catch (Exception $e) {
        echo '<p class="error">Exception: ' . $e->getMessage() . '</p>';
    }
}

echo '</div>';

// HTML footer
echo '</body>
</html>';
?>
