<?php
// sync_helper.php - Improved eBay sync process
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

/**
 * Check the status of a specific eBay listing to see if it has sold or ended
 */
function checkEbayListingStatus($item_id, $access_token = null) {
    $result = [
        'is_sold' => false,
        'is_ended' => false,
        'status' => 'unknown',
        'available_quantity' => 0
    ];
    
    // Try Trading API first for most accurate status
    $app_id = getSetting('ebay_app_id');
    $dev_id = getSetting('ebay_dev_id');
    $cert_id = getSetting('ebay_cert_id');
    $user_token = getSetting('ebay_user_token');
    
    if (!empty($app_id) && !empty($user_token)) {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
            <RequesterCredentials>
                <eBayAuthToken>' . htmlspecialchars($user_token) . '</eBayAuthToken>
            </RequesterCredentials>
            <ItemID>' . htmlspecialchars($item_id) . '</ItemID>
            <DetailLevel>ReturnAll</DetailLevel>
        </GetItemRequest>';
        
        $headers = [
            'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
            'X-EBAY-API-DEV-NAME: ' . $dev_id,
            'X-EBAY-API-APP-NAME: ' . $app_id,
            'X-EBAY-API-CERT-NAME: ' . $cert_id,
            'X-EBAY-API-CALL-NAME: GetItem',
            'X-EBAY-API-SITEID: 0',
            'Content-Type: text/xml'
        ];
        
        $ch = curl_init('https://api.ebay.com/ws/api.dll');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $xml_response = simplexml_load_string($response);
            
            if ($xml_response && isset($xml_response->Item)) {
                $item = $xml_response->Item;
                
                // Check listing status
                if (isset($item->SellingStatus->ListingStatus)) {
                    $status = (string)$item->SellingStatus->ListingStatus;
                    $result['status'] = $status;
                    
                    if (in_array($status, ['Completed', 'Ended'])) {
                        $result['is_ended'] = true;
                        
                        // Check if it ended with sales
                        $quantity_sold = isset($item->SellingStatus->QuantitySold) ? 
                                        (int)$item->SellingStatus->QuantitySold : 0;
                        if ($quantity_sold > 0) {
                            $result['is_sold'] = true;
                        }
                    }
                }
                
                // Check available quantity
                if (isset($item->Quantity)) {
                    $total_quantity = (int)$item->Quantity;
                    $quantity_sold = isset($item->SellingStatus->QuantitySold) ? 
                                    (int)$item->SellingStatus->QuantitySold : 0;
                    $result['available_quantity'] = max(0, $total_quantity - $quantity_sold);
                    
                    // If all quantity is sold, mark as sold
                    if ($total_quantity > 0 && $quantity_sold >= $total_quantity) {
                        $result['is_sold'] = true;
                    }
                }
                
                return $result;
            }
        }
    }
    
    // Fallback: Try Browse API
    if (!empty($access_token)) {
        $url = 'https://api.ebay.com/buy/browse/v1/item/v1|' . $item_id . '|0';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'X-EBAY-C-MARKETPLACE-ID: EBAY_US'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $data = json_decode($response, true);
            
            // Check if item is still available
            if (isset($data['availableQuantity'])) {
                $result['available_quantity'] = intval($data['availableQuantity']);
                if ($result['available_quantity'] <= 0) {
                    $result['is_sold'] = true;
                }
            }
            
            // Check buying options
            if (!isset($data['buyingOptions']) || empty($data['buyingOptions'])) {
                $result['is_ended'] = true;
            }
            
            return $result;
        } else if ($http_code == 404) {
            // Item not found - likely ended or removed
            $result['is_ended'] = true;
            return $result;
        }
    }
    
    return $result;
}

/**
 * Comprehensive eBay Sync Function
 * - Fetches ALL listings from eBay store using pagination
 * - Only updates fields that have actually changed
 * - Removes listings that no longer exist on eBay
 * - Preserves user-defined categories and favorites
 * 
 * @param PDO $pdo Database connection
 * @param bool $force_token_refresh Whether to force token refresh
 * @return array Result of the sync operation
 */
function smartEbaySync($pdo, $force_token_refresh = false) {
    try {
        $sync_start_time = microtime(true);
        error_log("eBay comprehensive sync started at " . date('Y-m-d H:i:s'));
        
        // Include token refresh helper
        require_once 'token_refresh.php';
        
        // Ensure valid access token
        if (!ensureValidEbayAccessToken($force_token_refresh)) {
            if (!$force_token_refresh && !ensureValidEbayAccessToken(true)) {
                throw new Exception('Failed to refresh eBay access token after multiple attempts');
            }
        }
        
        $access_token = getSetting('ebay_access_token');
        if (empty($access_token)) {
            throw new Exception('No valid access token found after refresh attempts');
        }
        
        checkEbayTokenStatus();
        
        $seller_id = getSetting('ebay_seller_id');
        if (empty($seller_id)) {
            throw new Exception('No seller ID configured');
        }
        
        error_log("eBay sync: Starting comprehensive sync for seller: $seller_id");
        
        // Ensure table exists with proper structure
        createEbayListingsTableHelper($pdo);
        
        // Get all existing listings with their metadata
        $existing_listings = [];
        $existing_stmt = $pdo->query("
            SELECT id, sku, title, description, price, currency, quantity, 
                   seller_id, image_url, category, is_favorite, last_updated 
            FROM ebay_listings
        ");
        while ($row = $existing_stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing_listings[$row['sku']] = $row;
        }
        
        error_log("Found " . count($existing_listings) . " existing listings in database");
        
        // Fetch ALL current listings from eBay
        $current_ebay_listings = fetchAllEbayListings($access_token, $seller_id);
        error_log("Fetched " . count($current_ebay_listings) . " listings from eBay");
        
        // Track sync statistics
        $stats = [
            'updated' => 0,
            'new' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'preserved' => 0,
            'errors' => 0
        ];
        
        // Process current eBay listings
        $processed_skus = [];
        foreach ($current_ebay_listings as $listing) {
            $sku = 'EBAY-' . $listing['item_id'];
            $processed_skus[] = $sku;
            
            try {
                if (isset($existing_listings[$sku])) {
                    // Update existing listing with selective field updates
                    $update_result = updateListingSelectively($pdo, $existing_listings[$sku], $listing, $seller_id);
                    if ($update_result === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['unchanged']++;
                    }
                } else {
                    // Insert new listing
                    insertNewListing($pdo, $listing, $seller_id, $sku);
                    $stats['new']++;
                }
            } catch (Exception $e) {
                error_log("Error processing listing {$sku}: " . $e->getMessage());
                $stats['errors']++;
            }
            
            // Progress logging
            if (($stats['updated'] + $stats['new']) % 25 == 0) {
                error_log("Sync progress: " . ($stats['updated'] + $stats['new']) . " listings processed");
            }
        }
        
        // Handle listings that no longer exist on eBay or have sold
        $removed_listings = array_diff(array_keys($existing_listings), $processed_skus);
        foreach ($removed_listings as $sku) {
            $listing = $existing_listings[$sku];
            $item_id = str_replace('EBAY-', '', $sku);
            
            // Check if listing has sold by querying eBay directly
            $listing_status = checkEbayListingStatus($item_id, $access_token);
            
            if ($listing_status['is_sold'] || $listing_status['is_ended']) {
                // Listing has sold or ended - decide whether to preserve or remove
                if (!empty($listing['category']) || $listing['is_favorite']) {
                    // Mark as sold but preserve for reference
                    $pdo->prepare("UPDATE ebay_listings SET quantity = 0, last_updated = ? WHERE sku = ?")
                       ->execute([date('Y-m-d H:i:s'), $sku]);
                    $stats['preserved']++;
                    error_log("Marked sold listing as preserved (quantity=0): {$sku}");
                } else {
                    // Remove sold listing that has no user data
                    $pdo->prepare("DELETE FROM ebay_listings WHERE sku = ?")->execute([$sku]);
                    $stats['removed']++;
                    error_log("Removed sold listing: {$sku}");
                }
            } else {
                // Listing still exists but wasn't in our fetch - preserve if categorized/favorited
                if (!empty($listing['category']) || $listing['is_favorite']) {
                    $stats['preserved']++;
                    error_log("Preserving listing with category/favorite: {$sku}");
                } else {
                    // Remove listing that's no longer accessible
                    $pdo->prepare("DELETE FROM ebay_listings WHERE sku = ?")->execute([$sku]);
                    $stats['removed']++;
                    error_log("Removed inaccessible listing: {$sku}");
                }
            }
        }
        
        // Update last sync time
        updateSetting('ebay_last_sync', date('Y-m-d H:i:s'));
        
        $sync_duration = round(microtime(true) - $sync_start_time, 2);
        error_log("eBay comprehensive sync completed in {$sync_duration} seconds");
        
        $message = sprintf(
            "Comprehensive sync completed: %d updated, %d new, %d removed, %d unchanged, %d preserved, %d errors",
            $stats['updated'], $stats['new'], $stats['removed'], 
            $stats['unchanged'], $stats['preserved'], $stats['errors']
        );
        
        return array_merge(['success' => true, 'message' => $message, 'duration' => $sync_duration], $stats);
        
    } catch (Exception $e) {
        error_log('eBay comprehensive sync error: ' . $e->getMessage());
        
        if (strpos($e->getMessage(), 'token') !== false) {
            updateSetting('ebay_last_token_refresh', date('Y-m-d H:i:s'));
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Fetch ALL listings from eBay using comprehensive pagination
 */
function fetchAllEbayListings($access_token, $seller_id) {
    $all_listings = [];
    $page = 1;
    $max_pages = 50; // Safety limit
    
    // Try Trading API first (most comprehensive)
    $trading_listings = fetchListingsViaTrading($seller_id);
    if (!empty($trading_listings)) {
        error_log("Successfully fetched " . count($trading_listings) . " listings via Trading API");
        return $trading_listings;
    }
    
    // Fallback to Browse API with pagination - filter for active listings only
    error_log("Trading API unavailable, using Browse API with pagination");
    
    do {
        $url = sprintf(
            'https://api.ebay.com/buy/browse/v1/item_summary/search?q=seller:%s&filter=buyingOptions:{FIXED_PRICE|AUCTION}&filter=conditions:{NEW|USED|REFURBISHED}&limit=200&offset=%d',
            urlencode($seller_id),
            ($page - 1) * 200
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'X-EBAY-C-MARKETPLACE-ID: EBAY_US'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            error_log("Browse API request failed with HTTP code: $http_code");
            break;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['itemSummaries'])) {
            break;
        }
        
        $page_listings = [];
        foreach ($data['itemSummaries'] as $item) {
            if (isset($item['itemId']) && isset($item['title'])) {
                // Check if item is still available for purchase (not sold out)
                $available_quantity = isset($item['availableQuantity']) ? intval($item['availableQuantity']) : 0;
                
                // Skip items with no available quantity
                if ($available_quantity <= 0) {
                    continue;
                }
                
                // Additional check for buying options to ensure it's still purchasable
                $buying_options = isset($item['buyingOptions']) ? $item['buyingOptions'] : [];
                if (empty($buying_options)) {
                    continue;
                }
                
                $page_listings[] = [
                    'item_id' => $item['itemId'],
                    'title' => $item['title'],
                    'price' => isset($item['price']['value']) ? floatval($item['price']['value']) : 0,
                    'currency' => isset($item['price']['currency']) ? $item['price']['currency'] : 'USD',
                    'quantity' => $available_quantity,
                    'image_url' => isset($item['image']['imageUrl']) ? $item['image']['imageUrl'] : '',
                    'url' => isset($item['itemWebUrl']) ? $item['itemWebUrl'] : "https://www.ebay.com/itm/{$item['itemId']}",
                    'description' => '' // Will fetch separately if needed
                ];
            }
        }
        
        $all_listings = array_merge($all_listings, $page_listings);
        error_log("Fetched page $page: " . count($page_listings) . " listings (total: " . count($all_listings) . ")");
        
        // Check if we have more pages
        $has_more = isset($data['next']) || count($page_listings) == 200;
        $page++;
        
    } while ($has_more && $page <= $max_pages);
    
    // If Browse API also failed, try Finding API as final fallback
    if (empty($all_listings)) {
        error_log("Browse API failed, trying Finding API as final fallback");
        $all_listings = fetchListingsViaFinding($seller_id);
    }
    
    return $all_listings;
}

/**
 * Fetch listings using Trading API (most comprehensive)
 */
function fetchListingsViaTrading($seller_id) {
    $app_id = getSetting('ebay_app_id');
    $dev_id = getSetting('ebay_dev_id');
    $cert_id = getSetting('ebay_cert_id');
    $user_token = getSetting('ebay_user_token');
    
    if (empty($app_id) || empty($user_token)) {
        return [];
    }
    
    $all_listings = [];
    $page = 1;
    $max_pages = 50;
    
    do {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
            <RequesterCredentials>
                <eBayAuthToken>' . htmlspecialchars($user_token) . '</eBayAuthToken>
            </RequesterCredentials>
            <UserID>' . htmlspecialchars($seller_id) . '</UserID>
            <DetailLevel>ReturnAll</DetailLevel>
            <GranularityLevel>Fine</GranularityLevel>
            <StartTimeFrom>' . gmdate('Y-m-d\TH:i:s.\0\0\0\Z', strtotime('-90 days')) . '</StartTimeFrom>
            <StartTimeTo>' . gmdate('Y-m-d\TH:i:s.\0\0\0\Z') . '</StartTimeTo>
            <ListingType>FixedPriceItem</ListingType>
            <ListingType>Auction</ListingType>
            <ListingType>StoreInventory</ListingType>
            <SKUArray>
                <SKU></SKU>
            </SKUArray>
            <IncludeWatchCount>true</IncludeWatchCount>
            <Pagination>
                <EntriesPerPage>200</EntriesPerPage>
                <PageNumber>' . $page . '</PageNumber>
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (!$response) {
            break;
        }
        
        $xml_response = simplexml_load_string($response);
        if (!$xml_response || !isset($xml_response->ItemArray->Item)) {
            break;
        }
        
        $page_listings = [];
        foreach ($xml_response->ItemArray->Item as $item) {
            if (isset($item->ItemID) && isset($item->Title)) {
                // Check listing status - only include active listings
                $listing_status = isset($item->SellingStatus->ListingStatus) ? 
                                 (string)$item->SellingStatus->ListingStatus : '';
                
                // Skip sold, completed, or ended listings
                if (in_array($listing_status, ['Completed', 'Ended', 'Sold'])) {
                    continue;
                }
                
                // Additional check for quantity - skip if 0 and sold
                $quantity = isset($item->Quantity) ? (int)$item->Quantity : 1;
                $quantity_sold = isset($item->SellingStatus->QuantitySold) ? (int)$item->SellingStatus->QuantitySold : 0;
                
                // For fixed price items, if all quantity is sold, skip it
                if ($quantity <= $quantity_sold && $quantity_sold > 0) {
                    continue;
                }
                
                $price = 0;
                if (isset($item->StartPrice)) {
                    $price = (float)$item->StartPrice;
                } elseif (isset($item->CurrentPrice)) {
                    $price = (float)$item->CurrentPrice;
                }
                
                $page_listings[] = [
                    'item_id' => (string)$item->ItemID,
                    'title' => (string)$item->Title,
                    'description' => isset($item->Description) ? (string)$item->Description : '',
                    'price' => $price,
                    'currency' => 'USD',
                    'quantity' => max(0, $quantity - $quantity_sold), // Available quantity
                    'image_url' => isset($item->PictureDetails->PictureURL[0]) ? (string)$item->PictureDetails->PictureURL[0] : '',
                    'url' => 'https://www.ebay.com/itm/' . (string)$item->ItemID,
                    'status' => $listing_status
                ];
            }
        }
        
        $all_listings = array_merge($all_listings, $page_listings);
        error_log("Trading API page $page: " . count($page_listings) . " listings");
        
        // Check if there are more pages
        $has_more = isset($xml_response->PaginationResult->HasMoreItems) && 
                   (string)$xml_response->PaginationResult->HasMoreItems === 'true';
        $page++;
        
    } while ($has_more && $page <= $max_pages);
    
    return $all_listings;
}

/**
 * Fetch listings using Finding API (fallback)
 */
function fetchListingsViaFinding($seller_id) {
    $app_id = getSetting('ebay_app_id');
    if (empty($app_id)) {
        return [];
    }
    
    $all_listings = [];
    $page = 1;
    $max_pages = 10; // Finding API has stricter limits
    
    do {
        $url = sprintf(
            'https://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsAdvanced&SERVICE-VERSION=1.0.0&SECURITY-APPNAME=%s&RESPONSE-DATA-FORMAT=JSON&REST-PAYLOAD&itemFilter(0).name=Seller&itemFilter(0).value=%s&itemFilter(1).name=ListingType&itemFilter(1).value(0)=FixedPrice&itemFilter(1).value(1)=Auction&itemFilter(1).value(2)=StoreInventory&itemFilter(2).name=AvailableTo&itemFilter(2).value=US&paginationInput.entriesPerPage=100&paginationInput.pageNumber=%d',
            urlencode($app_id),
            urlencode($seller_id),
            $page
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (!$response) {
            break;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['findItemsAdvancedResponse'][0]['searchResult'][0]['item'])) {
            break;
        }
        
        $items = $data['findItemsAdvancedResponse'][0]['searchResult'][0]['item'];
        $page_listings = [];
        
        foreach ($items as $item) {
            if (isset($item['itemId'][0]) && isset($item['title'][0])) {
                // Check listing status and availability
                $listing_info = isset($item['listingInfo'][0]) ? $item['listingInfo'][0] : [];
                $listing_type = isset($listing_info['listingType'][0]) ? $listing_info['listingType'][0] : '';
                $end_time = isset($listing_info['endTime'][0]) ? $listing_info['endTime'][0] : '';
                
                // Skip if listing has ended
                if (!empty($end_time) && strtotime($end_time) < time()) {
                    continue;
                }
                
                // Check selling status
                $selling_status = isset($item['sellingStatus'][0]) ? $item['sellingStatus'][0] : [];
                $selling_state = isset($selling_status['sellingState'][0]) ? $selling_status['sellingState'][0] : '';
                
                // Skip completed/ended auctions
                if (in_array($selling_state, ['EndedWithSales', 'Ended'])) {
                    continue;
                }
                
                // For fixed price items, check if still available
                $quantity = isset($item['quantity'][0]) ? intval($item['quantity'][0]) : 1;
                if ($quantity <= 0) {
                    continue;
                }
                
                $page_listings[] = [
                    'item_id' => $item['itemId'][0],
                    'title' => $item['title'][0],
                    'description' => '',
                    'price' => isset($item['sellingStatus'][0]['currentPrice'][0]['__value__']) ? 
                              floatval($item['sellingStatus'][0]['currentPrice'][0]['__value__']) : 0,
                    'currency' => 'USD',
                    'quantity' => $quantity,
                    'image_url' => isset($item['galleryURL'][0]) ? $item['galleryURL'][0] : '',
                    'url' => isset($item['viewItemURL'][0]) ? $item['viewItemURL'][0] : 
                            "https://www.ebay.com/itm/{$item['itemId'][0]}"
                ];
            }
        }
        
        $all_listings = array_merge($all_listings, $page_listings);
        error_log("Finding API page $page: " . count($page_listings) . " listings");
        
        // Check pagination info
        $pagination = $data['findItemsAdvancedResponse'][0]['paginationOutput'][0] ?? [];
        $total_pages = isset($pagination['totalPages'][0]) ? intval($pagination['totalPages'][0]) : 1;
        $has_more = $page < $total_pages && count($page_listings) > 0;
        $page++;
        
    } while ($has_more && $page <= $max_pages);
    
    return $all_listings;
}

/**
 * Update existing listing only if fields have changed
 */
function updateListingSelectively($pdo, $existing_listing, $new_listing, $seller_id) {
    $updates = [];
    $params = ['sku' => $existing_listing['sku']];
    
    // Check each field for changes
    if ($existing_listing['title'] !== $new_listing['title']) {
        $updates[] = 'title = :title';
        $params['title'] = $new_listing['title'];
    }
    
    if ($existing_listing['price'] != $new_listing['price']) {
        $updates[] = 'price = :price';
        $params['price'] = $new_listing['price'];
    }
    
    if ($existing_listing['quantity'] != $new_listing['quantity']) {
        $updates[] = 'quantity = :quantity';
        $params['quantity'] = $new_listing['quantity'];
    }
    
    if ($existing_listing['image_url'] !== ($new_listing['image_url'] ?? '')) {
        $updates[] = 'image_url = :image_url';
        $params['image_url'] = $new_listing['image_url'] ?? '';
    }
    
    // Handle description separately (might be empty from API)
    $new_description = $new_listing['description'] ?? '';
    if (!empty($new_description) && $existing_listing['description'] !== $new_description) {
        $updates[] = 'description = :description';
        $params['description'] = $new_description;
    }
    
    // Always update last_updated if there are changes
    if (!empty($updates)) {
        $updates[] = 'last_updated = :last_updated';
        $params['last_updated'] = date('Y-m-d H:i:s');
        
        $sql = "UPDATE ebay_listings SET " . implode(', ', $updates) . " WHERE sku = :sku";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        error_log("Updated listing {$existing_listing['sku']}: " . implode(', ', array_keys($params)));
        return 'updated';
    }
    
    return 'unchanged';
}

/**
 * Insert new listing
 */
function insertNewListing($pdo, $listing, $seller_id, $sku) {
    $sql = "INSERT INTO ebay_listings 
            (sku, title, description, price, currency, quantity, seller_id, image_url, last_updated) 
            VALUES 
            (:sku, :title, :description, :price, :currency, :quantity, :seller_id, :image_url, :last_updated)";
    
    $params = [
        'sku' => $sku,
        'title' => $listing['title'],
        'description' => $listing['description'] ?? '',
        'price' => $listing['price'],
        'currency' => $listing['currency'] ?? 'USD',
        'quantity' => $listing['quantity'],
        'seller_id' => $seller_id,
        'image_url' => $listing['image_url'] ?? '',
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    error_log("Inserted new listing: $sku - {$listing['title']}");
}

/**
 * Create eBay listings table if it doesn't exist
 */
function createEbayListingsTableHelper($pdo) {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'ebay_listings'")->rowCount() > 0;
    
    if (!$tableExists) {
        $pdo->exec("CREATE TABLE ebay_listings (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `sku` VARCHAR(255) NOT NULL UNIQUE,
            `title` TEXT,
            `description` TEXT,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
            `quantity` INT NOT NULL DEFAULT 0,
            `seller_id` VARCHAR(255) NOT NULL,
            `image_url` TEXT,
            `category` VARCHAR(255) DEFAULT NULL,
            `is_favorite` TINYINT(1) NOT NULL DEFAULT 0,
            `click_count` INT NOT NULL DEFAULT 0,
            `last_updated` TIMESTAMP NOT NULL,
            INDEX `idx_sku` (`sku`),
            INDEX `idx_category` (`category`),
            INDEX `idx_favorite` (`is_favorite`),
            INDEX `idx_last_updated` (`last_updated`)
        )");
        error_log("Created ebay_listings table");
    } else {
        // Ensure description column exists
        $columns = [];
        $columnsQuery = $pdo->query("SHOW COLUMNS FROM ebay_listings");
        while ($column = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $column['Field'];
        }
        
        if (!in_array('description', $columns)) {
            $pdo->exec("ALTER TABLE ebay_listings ADD COLUMN description TEXT AFTER title");
            error_log("Added description column to ebay_listings table");
        }
    }
}
?>