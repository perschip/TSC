<?php
// store/index.php - Complete rewrite to improve user experience and reduce bounce rate
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/store/config.php';
require_once '../includes/store/functions.php';

// Start the session
session_start();

// Include necessary files
require_once '../includes/db.php';
require_once '../includes/store/functions.php';
require_once '../includes/store/config.php';

// Initialize cart if not already done
initialize_cart();

// Get the cart from session
$cart = get_cart();

// Calculate cart count
$cart_count = 0;
if (isset($cart['items']) && is_array($cart['items'])) {
    foreach ($cart['items'] as $item) {
        $cart_count += $item['quantity'];
    }
}

// Set page title
$page_title = "Shop Our Collection";

// Get search query if any
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get category filter if any
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Get sort option if any
$sort_option = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Pagination settings
$products_per_page = 12;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $products_per_page;

// Prepare database connection
$db = new PDO("mysql:host={$db_host};dbname={$db_name}", $db_user, $db_pass);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if categories table exists
$check_table_query = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'categories'";
$check_table_stmt = $db->prepare($check_table_query);
$check_table_stmt->execute();
$categories_table_exists = (bool)$check_table_stmt->fetchColumn();

// Get distinct categories from products if categories table doesn't exist
if (!$categories_table_exists) {
    try {
        $categories_query = "SELECT DISTINCT category FROM products ORDER BY category ASC";
        $categories_stmt = $db->prepare($categories_query);
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Format categories
        $categories = [];
        foreach ($categories as $index => $category) {
            if (!empty($category)) {
                $categories[] = [
                    'id' => $index + 1,
                    'name' => $category,
                    'slug' => strtolower(str_replace(' ', '-', $category))
                ];
            }
        }
    } catch (PDOException $e) {
        // Log error but continue execution
        error_log('Error fetching categories: ' . $e->getMessage());
        $categories = [];
    }
} else {
    try {
        $categories_query = "SELECT id, name, slug FROM categories WHERE status = 'active' ORDER BY name ASC";
        $categories_stmt = $db->prepare($categories_query);
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error but continue execution
        error_log('Error fetching categories: ' . $e->getMessage());
        $categories = [];
    }
}

// Check if 'status' column exists in products table
$check_status_column_query = "SELECT COUNT(*) FROM information_schema.columns 
                            WHERE table_schema = DATABASE() 
                            AND table_name = 'products' 
                            AND column_name = 'status'";
$check_status_column_stmt = $db->prepare($check_status_column_query);
$check_status_column_stmt->execute();
$status_column_exists = (bool)$check_status_column_stmt->fetchColumn();

// Build the product query based on table structure
if ($categories_table_exists) {
    $query = "SELECT p.*, p.title as name, c.name as category_name, c.slug as category_slug 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.id";
} else {
    // Simplified query without categories join
    $query = "SELECT p.*, p.title as name, p.category as category_name, 
             LOWER(REPLACE(p.category, ' ', '-')) as category_slug 
             FROM products p";
}

// Add WHERE clause to only show active products
$query .= " WHERE p.status = 'active'";
$params = [];

// Check if title and description columns exist
$check_title_column_query = "SELECT COUNT(*) FROM information_schema.columns 
                          WHERE table_schema = DATABASE() 
                          AND table_name = 'products' 
                          AND column_name = 'title'";
$check_title_column_stmt = $db->prepare($check_title_column_query);
$check_title_column_stmt->execute();
$title_column_exists = (bool)$check_title_column_stmt->fetchColumn();

$check_description_column_query = "SELECT COUNT(*) FROM information_schema.columns 
                                 WHERE table_schema = DATABASE() 
                                 AND table_name = 'products' 
                                 AND column_name = 'description'";
$check_description_column_stmt = $db->prepare($check_description_column_query);
$check_description_column_stmt->execute();
$description_column_exists = (bool)$check_description_column_stmt->fetchColumn();

// Add search filter if provided
if (!empty($search_query)) {
    $search_conditions = [];
    
    if ($title_column_exists) {
        $search_conditions[] = "p.title LIKE :search";
    }
    
    if ($description_column_exists) {
        $search_conditions[] = "p.description LIKE :search";
    }
    
    // If no specific columns found, search in all text columns
    if (empty($search_conditions)) {
        // Get all column names from products table
        $columns_query = "SHOW COLUMNS FROM products";
        $columns_stmt = $db->prepare($columns_query);
        $columns_stmt->execute();
        $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($columns as $column) {
            if (in_array(strtolower($column), ['id', 'price', 'inventory', 'featured', 'created_at', 'updated_at'])) {
                continue; // Skip numeric/date columns
            }
            $search_conditions[] = "p.{$column} LIKE :search";
        }
    }
    
    if (!empty($search_conditions)) {
        $query .= " AND (" . implode(" OR ", $search_conditions) . ")";
        $params['search'] = "%{$search_query}%";
    }
}

// Check if category_id and category columns exist
$check_category_id_column_query = "SELECT COUNT(*) FROM information_schema.columns 
                                WHERE table_schema = DATABASE() 
                                AND table_name = 'products' 
                                AND column_name = 'category_id'";
$check_category_id_column_stmt = $db->prepare($check_category_id_column_query);
$check_category_id_column_stmt->execute();
$category_id_column_exists = (bool)$check_category_id_column_stmt->fetchColumn();

$check_category_column_query = "SELECT COUNT(*) FROM information_schema.columns 
                              WHERE table_schema = DATABASE() 
                              AND table_name = 'products' 
                              AND column_name = 'category'";
$check_category_column_stmt = $db->prepare($check_category_column_query);
$check_category_column_stmt->execute();
$category_column_exists = (bool)$check_category_column_stmt->fetchColumn();

// Add category filter if provided
if ($category_id > 0) {
    if ($categories_table_exists && $category_id_column_exists) {
        // If categories table exists and products has category_id column, filter by category_id
        $query .= " AND p.category_id = :category_id";
        $params['category_id'] = $category_id;
    } else if ($category_column_exists) {
        // Otherwise filter by category name
        $category_name = '';
        foreach ($categories as $cat) {
            if ($cat['id'] == $category_id) {
                $category_name = $cat['name'];
                break;
            }
        }
        if (!empty($category_name)) {
            $query .= " AND p.category = :category_name";
            $params['category_name'] = $category_name;
        }
    }
}

// Check if price, inventory and created_at columns exist
$check_price_column_query = "SELECT COUNT(*) FROM information_schema.columns 
                           WHERE table_schema = DATABASE() 
                           AND table_name = 'products' 
                           AND column_name = 'price'";
$check_price_column_stmt = $db->prepare($check_price_column_query);
$check_price_column_stmt->execute();
$price_column_exists = (bool)$check_price_column_stmt->fetchColumn();

$check_inventory_column_query = "SELECT COUNT(*) FROM information_schema.columns 
                           WHERE table_schema = DATABASE() 
                           AND table_name = 'products' 
                           AND column_name = 'inventory'";
$check_inventory_column_stmt = $db->prepare($check_inventory_column_query);
$check_inventory_column_stmt->execute();
$inventory_column_exists = (bool)$check_inventory_column_stmt->fetchColumn();

$check_created_at_column_query = "SELECT COUNT(*) FROM information_schema.columns 
                                WHERE table_schema = DATABASE() 
                                AND table_name = 'products' 
                                AND column_name = 'created_at'";
$check_created_at_column_stmt = $db->prepare($check_created_at_column_query);
$check_created_at_column_stmt->execute();
$created_at_column_exists = (bool)$check_created_at_column_stmt->fetchColumn();

// Add sorting
$sort_column = 'p.created_at'; // Default sort
$sort_direction = 'DESC';

if (!empty($sort_option)) {
    switch ($sort_option) {
        case 'price_low':
            if ($price_column_exists) {
                $sort_column = 'p.price';
                $sort_direction = 'ASC';
            }
            break;
        case 'price_high':
            if ($price_column_exists) {
                $sort_column = 'p.price';
                $sort_direction = 'DESC';
            }
            break;
        case 'name_asc':
            $sort_column = 'p.title';
            $sort_direction = 'ASC';
            break;
        case 'name_desc':
            $sort_column = 'p.title';
            $sort_direction = 'DESC';
            break;
        case 'newest':
            if ($created_at_column_exists) {
                $sort_column = 'p.created_at';
                $sort_direction = 'DESC';
            }
            break;
        case 'oldest':
            if ($created_at_column_exists) {
                $sort_column = 'p.created_at';
                $sort_direction = 'ASC';
            }
            break;
    }
}

$query .= " ORDER BY {$sort_column} {$sort_direction}";

// Create a count query that preserves all WHERE conditions but removes ORDER BY
$count_query = preg_replace('/ORDER BY.*$/i', '', $query);
$count_query = preg_replace('/SELECT.*?FROM/is', 'SELECT COUNT(*) as total FROM', $count_query);

try {
    $count_stmt = $db->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue(":$key", $value);
    }
    $count_stmt->execute();
    $total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    // If count query fails, set a default value and log error
    error_log('Error in count query: ' . $e->getMessage());
    $total_products = 0;
}

$total_pages = ceil($total_products / $products_per_page);

// Add pagination limits
$query .= " LIMIT :offset, :limit";

// Prepare and execute the query
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $products_per_page, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured products
$featured_query = "SELECT p.*, p.title as name 
                 FROM products p 
                 WHERE p.featured = 1 AND p.status = 'active' 
                 ORDER BY RAND() 
                 LIMIT 4";
$featured_stmt = $db->prepare($featured_query);
$featured_stmt->execute();
$featured_products = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recently viewed products
if (isset($_SESSION['recently_viewed']) && !empty($_SESSION['recently_viewed'])) {
    $recently_viewed_ids = array_slice($_SESSION['recently_viewed'], 0, 4); // Get up to 4 recently viewed
    
    if (!empty($recently_viewed_ids)) {
        $placeholders = implode(',', array_fill(0, count($recently_viewed_ids), '?'));
        
        $recently_viewed_query = "SELECT p.*, p.title as name 
                            FROM products p 
                            WHERE p.id IN ($placeholders) AND p.status = 'active'";
        $recently_viewed_stmt = $db->prepare($recently_viewed_query);
        
        // Bind each ID as a parameter
        foreach ($recently_viewed_ids as $index => $id) {
            $recently_viewed_stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        
        $recently_viewed_stmt->execute();
        $recently_viewed_products = $recently_viewed_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Sort results to match the order in recently_viewed_ids
        $sorted_recently_viewed = [];
        foreach ($recently_viewed_ids as $id) {
            foreach ($recently_viewed_products as $product) {
                if ($product['id'] == $id) {
                    $sorted_recently_viewed[] = $product;
                    break;
                }
            }
        }
        $recently_viewed_products = $sorted_recently_viewed;
    }
} else {
    $recently_viewed_products = [];
}

// Check if products table exists
$products_table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    $products_table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    error_log('Error checking products table: ' . $e->getMessage());
}

// Get PayPal settings
$paypal_settings = [
    'client_id' => '',
    'mode' => 'sandbox',
    'currency' => 'USD',
    'business_name' => 'Tristate Cards'
];

if ($products_table_exists) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'paypal_settings'");
        $settings_table_exists = $stmt->rowCount() > 0;
        
        if ($settings_table_exists) {
            $stmt = $pdo->query("SELECT setting_name, setting_value FROM paypal_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $paypal_settings[$row['setting_name']] = $row['setting_value'];
            }
        }
    } catch (PDOException $e) {
        error_log('Error loading PayPal settings: ' . $e->getMessage());
    }
}

// Get featured products
$featured_products = [];
if ($products_table_exists) {
    try {
        $stmt = $pdo->query("SELECT * FROM products WHERE status = 'active' ORDER BY featured DESC, id DESC LIMIT 8");
        $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error loading products: ' . $e->getMessage());
    }
}

// Add current product to recently viewed
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    // Track this product view in session
    if (!isset($_SESSION['recently_viewed'])) {
        $_SESSION['recently_viewed'] = [];
    }
    
    // Add current product ID to the front of the array
    if ($product_id > 0) {
        // Remove if already exists
        $_SESSION['recently_viewed'] = array_diff($_SESSION['recently_viewed'], [$product_id]);
        // Add to front
        array_unshift($_SESSION['recently_viewed'], $product_id);
        // Keep only the most recent 10
        $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 10);
    }
}

// AJAX handler for adding to cart
if (isset($_POST['action']) && ($_POST['action'] === 'add_to_cart' || $_POST['action'] === 'add')) {
    header('Content-Type: application/json');
    
    if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }
    
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) && is_numeric($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    // Get product details
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Debug info
        error_log("Adding to cart - Product ID: {$product_id}, Quantity: {$quantity}");
        
        $product_query = "SELECT id, title, price, image_url, inventory FROM products WHERE id = :id AND status = 'active'";
        $product_stmt = $db->prepare($product_query);
        $product_stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        $product_stmt->execute();
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug product data
        error_log("Product data: " . json_encode($product));
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found or not active']);
            exit;
        }
        
        // Ensure price is a valid number
        if (!isset($product['price']) || !is_numeric($product['price']) || (float)$product['price'] <= 0) {
            error_log("Invalid price: " . (isset($product['price']) ? $product['price'] : 'not set'));
            $product['price'] = 0; // Set a default price to prevent errors
        }
        
        // Only check inventory if it's explicitly set to 0
        if (isset($product['inventory']) && $product['inventory'] !== null) {
            error_log("Inventory check: Value={$product['inventory']}, Type=" . gettype($product['inventory']));
            
            if ((int)$product['inventory'] === 0) {
                echo json_encode(['success' => false, 'message' => 'This product is out of stock']);
                exit;
            }
        } else {
            error_log("No inventory field found in product data");
        }
        
        // Initialize cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Check if product already in cart
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $product_id) {
                // Only block if inventory is explicitly 0
                if (isset($product['inventory']) && $product['inventory'] !== null && (int)$product['inventory'] === 0) {
                    echo json_encode(['success' => false, 'message' => 'This product is out of stock']);
                    exit;
                }
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        unset($item); // Unset reference to prevent accidental modification
        
        // If not found, add to cart
        if (!$found) {
            // Ensure price is properly formatted as a float
            $price = isset($product['price']) ? (float)$product['price'] : 0;
            
            $_SESSION['cart'][] = [
                'id' => (int)$product['id'],
                'title' => $product['title'],
                'price' => $price,
                'image_url' => isset($product['image_url']) ? $product['image_url'] : '',
                'quantity' => $quantity
            ];
            
            error_log("Added new item to cart: ID={$product['id']}, Price={$price}");
        }
        
        // Calculate cart total
        $cart_count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $cart_count += $item['quantity'];
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Product added to cart', 
            'cart_count' => $cart_count,
            'product_name' => $product['title']
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log('Error adding to cart: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Tristate Cards</title>
    <meta name="description" content="Shop our curated selection of sports cards, trading cards, and collectibles. Find the perfect addition to your collection today!">
    
    <!-- Meta tags for SEO and social sharing -->
    <meta name="description" content="Shop the best selection of trading cards, sports cards, and collectibles at Tristate Cards.">
    <meta name="keywords" content="trading cards, sports cards, collectibles, memorabilia, baseball cards, basketball cards">
    <meta property="og:title" content="<?php echo $page_title; ?> - Tristate Cards">
    <meta property="og:description" content="Shop the best selection of trading cards, sports memorabilia, and collectibles at Tristate Cards.">
    <meta property="og:type" content="website">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --light-bg: #f8f9fa;
            --border-radius: 0.75rem;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            padding-top: 72px; /* Increased for better mobile spacing */
            background-color: var(--light-bg);
            color: #495057;
            line-height: 1.6;
        }
        
        /* Navbar Styles */
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: var(--transition);
        }
        
        .nav-link:hover {
            color: var(--primary-color) !important;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('/assets/images/cards-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            margin-bottom: 60px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }
        
        .hero-section h1 {
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 2.5rem;
        }
        
        .hero-section p {
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 2rem;
            opacity: 0.9;
        }
        
        /* Product Cards */
        .product-card {
            transition: var(--transition);
            height: 100%;
            border-radius: var(--border-radius);
            overflow: hidden;
            border: none;
            box-shadow: var(--box-shadow);
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
        }
        
        .card {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        
        .product-img-container {
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: #fff;
            padding: 1rem;
        }
        
        .product-img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
            transition: var(--transition);
        }
        
        .product-card:hover .product-img {
            transform: scale(1.05);
        }
        
        .product-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 1rem 0 0.5rem;
            line-height: 1.4;
            height: 3.1rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .product-price .original-price {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 1rem;
            margin-right: 0.5rem;
            font-weight: 400;
        }
        
        .product-category {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .product-rating {
            color: #ffc107;
            margin-bottom: 1rem;
        }
        
        .product-rating .count {
            color: #6c757d;
            font-size: 0.85rem;
            margin-left: 0.5rem;
        }
        
        /* Button Styles */
        .btn {
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            border: none;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
        }
        
        /* Filter Sidebar */
        .filter-sidebar {
            position: sticky;
            top: 90px;
        }
        
        .filter-heading {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .filter-group {
            margin-bottom: 1.5rem;
        }
        
        .filter-group-title {
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: block;
            color: var(--secondary-color);
        }
        
        .custom-control-label {
            cursor: pointer;
            padding-left: 0.25rem;
        }
        
        /* Search Box */
        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .search-box .form-control {
            padding-right: 3rem;
            border-radius: var(--border-radius);
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: var(--transition);
        }
        
        .search-box .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .search-box .btn-search {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            background: var(--primary-color);
            border: none;
            color: white;
            width: 50px;
        }
        
        /* Sort and Filter Controls */
        .store-controls {
            background-color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
        }
        
        .sort-select {
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .sort-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Pagination */
        .pagination {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        
        .page-item .page-link {
            border: none;
            color: var(--secondary-color);
            margin: 0 0.25rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
        }
        
        .page-item.active .page-link {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        
        .page-item .page-link:hover {
            background-color: #e9ecef;
            color: var(--primary-color);
            transform: translateY(-1px);
        }
        
        .page-item.active .page-link:hover {
            background: var(--gradient-primary);
            color: white;
        }
        
        .btn-success {
            background: var(--gradient-success);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #4e9a2a 0%, #95d4b8 100%);
            transform: translateY(-1px);
        }
        
        .section-title {
            position: relative;
            margin-bottom: 30px;
            padding-bottom: 15px;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 30px;
            padding-bottom: 15px;
            font-weight: 700;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        /* Product Badges */
        .badge-new, .badge-sale, .badge-featured {
            position: absolute;
            top: 10px;
            right: 10px;
            color: white;
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            z-index: 2;
        }
        
        .badge-new {
            background: linear-gradient(135deg, #ff6b6b 0%, #e74c3c 100%);
            box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
        }
        
        .badge-sale {
            background: linear-gradient(135deg, #ff9f43 0%, #ff7e00 100%);
            box-shadow: 0 2px 5px rgba(255, 126, 0, 0.3);
        }
        
        .badge-featured {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 5px rgba(102, 126, 234, 0.3);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #e9ecef;
            margin-bottom: 1.5rem;
        }
        
        .empty-state h3 {
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        
        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(102, 126, 234, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Footer */
        .footer {
            background: var(--gradient-primary);
            color: white;
            padding: 40px 0;
            margin-top: 60px;
        }
        
        .footer a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer a:hover {
            color: white;
        }
        
        .footer-title {
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: white;
        }
        
        .footer-links li {
            margin-bottom: 0.75rem;
        }
        
        /* Form Styling */
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Hero section update */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('/assets/images/cards-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            margin-bottom: 60px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: var(--gradient-primary);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">Tristate Cards</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/store/">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/contact.php">Contact</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <form action="/store/" method="get" class="d-none d-md-flex me-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit" class="btn btn-light">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    <a href="/store/cart.php" class="btn btn-light position-relative me-2">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $cart_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="/checkout/" class="btn btn-outline-light d-none d-sm-inline-block">
                        <i class="fas fa-credit-card me-1"></i> Checkout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1>Discover Rare and Collectible Trading Cards</h1>
            <p>Shop our curated selection of sports cards, trading cards, and collectibles. Find the perfect addition to your collection today!</p>
            <a href="#products" class="btn btn-light btn-lg">
                <i class="fas fa-search me-2"></i> Browse Collection
            </a>
        </div>
    </section>
    
    <!-- Featured Products Carousel (only show if we have featured products) -->
    <?php if (!empty($featured_products)): ?>
    <section class="container my-5">
        <h2 class="section-title">Featured Products</h2>
        <div id="featuredCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php foreach ($featured_products as $index => $product): ?>
                <button type="button" data-bs-target="#featuredCarousel" data-bs-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active"' : ''; ?> aria-label="Slide <?php echo $index + 1; ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach ($featured_products as $index => $product): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <img src="<?php echo !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : '/assets/images/placeholder.png'; ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($product['title']); ?>">
                        </div>
                        <div class="col-md-6 p-4">
                            <div class="badge-featured">Featured</div>
                            <h2 class="h3 mb-3"><?php echo htmlspecialchars($product['title']); ?></h2>
                            <p class="mb-4"><?php echo nl2br(htmlspecialchars(substr($product['description'], 0, 150) . (strlen($product['description']) > 150 ? '...' : ''))); ?></p>
                            <div class="d-flex align-items-center mb-4">
                                <div class="price-large me-3">
                                    <?php if (isset($product['price'])): ?>
                                    $<?php echo number_format((float)$product['price'], 2); ?>
                                    <?php else: ?>
                                    <span class="text-muted">Price not available</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($product['inventory']) && $product['inventory'] > 0): ?>
                                <button class="btn btn-primary" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                </button>
                                <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-times-circle me-2"></i> Out of Stock
                                </button>
                                <?php endif; ?>
                            </div>
                            <a href="/store/product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-info-circle me-2"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#featuredCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#featuredCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Main Content Section -->
    <section class="container my-5" id="products">
        <div class="row">
            <!-- Filter Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card filter-sidebar">
                    <div class="card-body">
                        <h3 class="filter-heading">Filter Products</h3>
                        
                        <!-- Search Box -->
                        <div class="search-box d-block d-md-none mb-4">
                            <form action="/store/" method="get">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button type="submit" class="btn-search">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Categories Filter -->
                        <div class="filter-group">
                            <span class="filter-group-title">Categories</span>
                            <div class="list-group">
                                <a href="/store/" class="list-group-item list-group-item-action <?php echo $category_id === 0 ? 'active' : ''; ?>">
                                    All Categories
                                </a>
                                <?php foreach ($categories as $category): ?>
                                <a href="/store/?category=<?php echo $category['id']; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo $sort_option !== 'newest' ? '&sort=' . urlencode($sort_option) : ''; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $category_id === (int)$category['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Sort Options -->
                        <div class="filter-group">
                            <span class="filter-group-title">Sort By</span>
                            <select class="form-select sort-select" id="sort-select" onchange="window.location = this.value;">
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=newest" <?php echo $sort_option === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=price_low" <?php echo $sort_option === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=price_high" <?php echo $sort_option === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=name_asc" <?php echo $sort_option === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=name_desc" <?php echo $sort_option === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Grid -->
            <div class="col-lg-9">
                <!-- Store Controls for Mobile -->
                <div class="store-controls d-lg-none mb-4">
                    <div class="row align-items-center">
                        <div class="col-6">
                            <button class="btn btn-outline-primary btn-sm w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                        <div class="col-6">
                            <select class="form-select sort-select" onchange="window.location = this.value;">
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=newest" <?php echo $sort_option === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=price_low" <?php echo $sort_option === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=price_high" <?php echo $sort_option === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=name_asc" <?php echo $sort_option === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                                <option value="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>sort=name_desc" <?php echo $sort_option === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Results Info -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h4 mb-0">
                        <?php if (!empty($search_query)): ?>
                            Search Results for "<?php echo htmlspecialchars($search_query); ?>"
                        <?php elseif ($category_id > 0): ?>
                            <?php 
                            $category_name = "Products";
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $category_id) {
                                    $category_name = htmlspecialchars($cat['name']);
                                    break;
                                }
                            }
                            echo $category_name;
                            ?>
                        <?php else: ?>
                            All Products
                        <?php endif; ?>
                    </h2>
                    <p class="text-muted mb-0"><?php echo $total_products; ?> products found</p>
                </div>
                
                <?php if (empty($products)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No Products Found</h3>
                    <p>We couldn't find any products matching your criteria.</p>
                    <a href="/store/" class="btn btn-primary">View All Products</a>
                </div>
                <?php else: ?>
                <!-- Product Grid -->
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
                    <?php foreach ($products as $product): ?>
                    <div class="col">
                        <div class="card product-card h-100">
                            <?php 
                            // Since there's no sale_price column, we'll use the featured flag for the Sale badge
                            if (isset($product['featured']) && $product['featured'] == 1): 
                            ?>
                            <div class="badge-sale">Featured</div>
                            <?php endif; ?>
                            <?php 
                            // Check if product is new (less than 14 days old)
                            $is_new = false;
                            if (!empty($product['created_at'])) {
                                try {
                                    $created_date = new DateTime($product['created_at']);
                                    $now = new DateTime();
                                    $days_old = $now->diff($created_date)->days;
                                    $is_new = $days_old <= 14;
                                } catch (Exception $e) {
                                    // If date parsing fails, don't show the badge
                                    $is_new = false;
                                }
                            }
                            if ($is_new): 
                            ?>
                            <div class="badge-new">New</div>
                            <?php endif; ?>
                            <a href="/store/product.php?id=<?php echo $product['id']; ?>" class="product-img-container">
                                <img src="<?php echo !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : '/assets/images/placeholder.png'; ?>" class="product-img" alt="<?php echo htmlspecialchars($product['title']); ?>">
                            </a>
                            <div class="card-body d-flex flex-column">
                                <div class="product-category"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></div>
                                <h3 class="product-title">
                                    <a href="/store/product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($product['title']); ?>
                                    </a>
                                </h3>
                                <div class="product-price mt-auto">
                                    <?php if (isset($product['price'])): ?>
                                    $<?php echo number_format((float)$product['price'], 2); ?>
                                    <?php else: ?>
                                    <span class="text-muted">Price not available</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($product['inventory']) && $product['inventory'] > 0): ?>
                                <button class="btn btn-primary mt-3" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </button>
                                <?php else: ?>
                                <button class="btn btn-secondary mt-3" disabled>
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Product pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?><?php echo $sort_option !== 'newest' ? 'sort=' . $sort_option . '&' : ''; ?>page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php 
                        // Calculate range of pages to show
                        $range = 2; // Show 2 pages before and after current page
                        $start_page = max(1, $current_page - $range);
                        $end_page = min($total_pages, $current_page + $range);
                        
                        // Always show first page
                        if ($start_page > 1): 
                        ?>
                        <li class="page-item">
                            <a class="page-link" href="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?><?php echo $sort_option !== 'newest' ? 'sort=' . $sort_option . '&' : ''; ?>page=1">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?><?php echo $sort_option !== 'newest' ? 'sort=' . $sort_option . '&' : ''; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php 
                        // Always show last page
                        if ($end_page < $total_pages): 
                        ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?><?php echo $sort_option !== 'newest' ? 'sort=' . $sort_option . '&' : ''; ?>page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="/store/?<?php echo $category_id > 0 ? 'category=' . $category_id . '&' : ''; ?><?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?><?php echo $sort_option !== 'newest' ? 'sort=' . $sort_option . '&' : ''; ?>page=<?php echo $current_page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Recently Viewed Products (only show if we have recently viewed products) -->
    <?php if (!empty($recently_viewed)): ?>
    <section class="container my-5">
        <h2 class="section-title">Recently Viewed</h2>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php foreach ($recently_viewed as $product): ?>
            <div class="col">
                <div class="card product-card h-100">
                    <a href="/store/product.php?id=<?php echo $product['id']; ?>" class="product-img-container">
                        <img src="<?php echo !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : '/assets/images/placeholder.png'; ?>" class="product-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <div class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></div>
                        <h3 class="product-title">
                            <a href="/store/product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h3>
                        <div class="product-price mt-auto">
                            <?php if (!empty($product['sale_price']) && $product['sale_price'] < $product['price']): ?>
                            <span class="original-price">$<?php echo number_format($product['price'], 2); ?></span>
                            $<?php echo number_format($product['sale_price'], 2); ?>
                            <?php else: ?>
                            $<?php echo number_format($product['price'], 2); ?>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-primary mt-3" onclick="addToCart(<?php echo $product['id']; ?>)">
                            <i class="fas fa-cart-plus me-2"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Mobile Filter Offcanvas -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="filterOffcanvasLabel">Filter Products</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <!-- Mobile Search Box -->
            <div class="search-box mb-4">
                <form action="/store/" method="get">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn-search">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Categories Filter -->
            <div class="filter-group">
                <span class="filter-group-title">Categories</span>
                <div class="list-group">
                    <a href="/store/" class="list-group-item list-group-item-action <?php echo $category_id === 0 ? 'active' : ''; ?>">
                        All Categories
                    </a>
                    <?php foreach ($categories as $category): ?>
                    <a href="/store/?category=<?php echo $category['id']; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo $sort_option !== 'newest' ? '&sort=' . urlencode($sort_option) : ''; ?>" 
                       class="list-group-item list-group-item-action <?php echo $category_id === (int)$category['id'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Container for Notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <!-- Why Shop With Us Section -->
    <section class="container my-5">
        <h2 class="section-title">Why Shop With Us</h2>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                        <h5>Secure Payments</h5>
                        <p>Shop with confidence using PayPal's secure payment processing.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-shipping-fast fa-3x text-primary mb-3"></i>
                        <h5>Fast Shipping</h5>
                        <p>Quick processing and shipping of all orders with tracking provided.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-certificate fa-3x text-primary mb-3"></i>
                        <h5>Quality Guaranteed</h5>
                        <p>All cards are carefully inspected and securely packaged.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Tristate Cards</h5>
                    <p class="text-muted">Your trusted source for collectible trading cards since 2005.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/" class="text-muted">Home</a></li>
                        <li><a href="/store/" class="text-muted">Shop</a></li>
                        <li><a href="/about.php" class="text-muted">About Us</a></li>
                        <li><a href="/contact.php" class="text-muted">Contact</a></li>
                        <li><a href="/privacy-policy.php" class="text-muted">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <address class="text-muted">
                        <i class="fas fa-map-marker-alt me-2"></i> 123 Card Street<br>
                        Collectible City, CC 12345<br>
                        <i class="fas fa-phone me-2"></i> (555) 123-4567<br>
                        <i class="fas fa-envelope me-2"></i> info@tristatecards.com
                    </address>
                </div>
            </div>
            <hr class="mt-4 mb-3">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> Tristate Cards. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <img src="/assets/images/payment-methods.png" alt="Payment Methods" class="payment-methods">
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to add item to cart
        window.addToCart = function(productId) {
            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('show');
            
            console.log('Adding product to cart:', productId);
            
            // Send AJAX request to dedicated cart handler
            fetch('/store/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `product_id=${productId}&quantity=1`
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json().catch(err => {
                    console.error('Error parsing JSON:', err);
                    throw new Error('Invalid JSON response');
                });
            })
            .then(data => {
                console.log('Response data:', data);
                // Hide loading overlay
                document.getElementById('loadingOverlay').classList.remove('show');
                
                if (data.success) {
                    // Update cart count in the UI
                    const cartBadges = document.querySelectorAll('.badge.rounded-pill.bg-danger');
                    cartBadges.forEach(badge => {
                        badge.textContent = data.cart_count;
                        badge.style.display = data.cart_count > 0 ? 'inline-block' : 'none';
                    });
                    
                    // Show success message
                    showToast('success', 'Added to Cart', data.message || 'Item added to your cart');
                } else {
                    // Show error message
                    showToast('error', 'Error', data.message || 'Could not add item to cart');
                }
            })
            .catch(error => {
                // Hide loading overlay
                document.getElementById('loadingOverlay').classList.remove('show');
                
                console.error('Error adding item to cart:', error);
                showToast('error', 'Error', 'Could not add item to cart. Please try again.');
            });
        };
        
        // Toast notification function
        function showToast(type, title, message) {
            // Create toast container if it doesn't exist
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toastId = 'toast-' + Date.now();
            const toastEl = document.createElement('div');
            toastEl.className = 'toast';
            toastEl.id = toastId;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            
            // Set toast background color based on type
            let bgColor = 'bg-primary';
            let icon = 'info-circle';
            
            switch (type) {
                case 'success':
                    bgColor = 'bg-success';
                    icon = 'check-circle';
                    break;
                case 'error':
                    bgColor = 'bg-danger';
                    icon = 'exclamation-circle';
                    break;
                case 'warning':
                    bgColor = 'bg-warning';
                    icon = 'exclamation-triangle';
                    break;
                case 'info':
                    bgColor = 'bg-info';
                    icon = 'info-circle';
                    break;
            }
            
            // Set toast content
            toastEl.innerHTML = `
                <div class="toast-header ${bgColor} text-white">
                    <i class="fas fa-${icon} me-2"></i>
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            
            // Add toast to container
            toastContainer.appendChild(toastEl);
            
            // Initialize and show toast
            const toast = new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 3000
            });
            toast.show();
            
            // Remove toast after it's hidden
            toastEl.addEventListener('hidden.bs.toast', function() {
                toastEl.remove();
            });
        }
        
        // No need to initialize event listeners for add to cart buttons
        // as we're using onclick handlers directly in the HTML
        
        console.log('Store index page loaded, cart functionality initialized');
    });
    </script>
    
    <?php if (!empty($paypal_settings['client_id'])): ?>
    <!-- PayPal JS SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo htmlspecialchars($paypal_settings['client_id']); ?>&currency=<?php echo htmlspecialchars($paypal_settings['currency']); ?>"></script>
    <?php endif; ?>
</body>
</html>
