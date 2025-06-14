<?php
/**
 * eBay Listings Frontend Display
 * This file handles the display of eBay listings on the frontend
 */

// Function to get eBay listings for the frontend
function getFrontendEbayListings($pdo, $options = []) {
    // Default options
    $defaults = [
        'category' => '',
        'featured_only' => false,
        'limit' => 12,
        'offset' => 0,
        'sort' => 'last_updated',
        'order' => 'DESC'
    ];
    
    // Merge options with defaults
    $options = array_merge($defaults, $options);
    
    // Build query
    $where_clauses = ["quantity > 0"]; // Only show active listings
    $params = [];
    
    // Add category filter if specified
    if (!empty($options['category'])) {
        $where_clauses[] = "category = :category";
        $params[':category'] = $options['category'];
    }
    
    // Add featured filter if specified
    if ($options['featured_only']) {
        $where_clauses[] = "is_favorite = 1";
    }
    
    // Combine where clauses
    $where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Build query
    $query = "SELECT * FROM ebay_listings $where_clause ORDER BY {$options['sort']} {$options['order']} LIMIT :offset, :limit";
    
    try {
        $stmt = $pdo->prepare($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':offset', $options['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $options['limit'], PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error fetching frontend eBay listings: ' . $e->getMessage());
        return [];
    }
}

// Function to get categories for the frontend filter
function getFrontendCategories($pdo) {
    try {
        // First try to get categories from the ebay_categories table
        $stmt = $pdo->query("SELECT name FROM ebay_categories ORDER BY name");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If no categories in the table, fall back to distinct values from listings
        if (empty($categories)) {
            $stmt = $pdo->query("SELECT DISTINCT category FROM ebay_listings WHERE category IS NOT NULL AND category != '' ORDER BY category");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        return $categories;
    } catch (PDOException $e) {
        error_log('Error fetching frontend categories: ' . $e->getMessage());
        return [];
    }
}

// Function to count listings by category
function countListingsByCategory($pdo) {
    try {
        $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM ebay_listings WHERE category IS NOT NULL AND category != '' AND quantity > 0 GROUP BY category ORDER BY count DESC");
        $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $result;
    } catch (PDOException $e) {
        error_log('Error counting listings by category: ' . $e->getMessage());
        return [];
    }
}

// Function to get total listings count (for pagination)
function getTotalListingsCount($pdo, $category = '') {
    $where_clause = "WHERE quantity > 0"; // Only count active listings
    $params = [];
    
    // Add category filter if specified
    if (!empty($category)) {
        $where_clause .= " AND category = :category";
        $params[':category'] = $category;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ebay_listings $where_clause");
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Error counting total listings: ' . $e->getMessage());
        return 0;
    }
}
?>
