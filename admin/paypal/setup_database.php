<?php
// admin/paypal/setup_database.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Set page title
$page_title = 'PayPal Database Setup';

// Check if tables exist
$tables_exist = [
    'products' => false,
    'orders' => false,
    'order_items' => false,
    'paypal_settings' => false,
    'coupons' => false
];

try {
    foreach (array_keys($tables_exist) as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $tables_exist[$table] = $stmt->rowCount() > 0;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error checking database tables: ' . $e->getMessage();
}

// Handle form submission
$setup_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setup_database') {
    try {
        // Create products table
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            image_url VARCHAR(255),
            inventory INT DEFAULT 0,
            category VARCHAR(100),
            status ENUM('active', 'draft', 'archived') DEFAULT 'draft',
            featured BOOLEAN DEFAULT 0,
            sku VARCHAR(100) UNIQUE,
            weight DECIMAL(10,2) DEFAULT 0,
            dimensions VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Create orders table
        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_reference VARCHAR(100) UNIQUE,
            paypal_order_id VARCHAR(100),
            paypal_payer_id VARCHAR(100),
            customer_name VARCHAR(255),
            customer_email VARCHAR(255),
            billing_address TEXT,
            shipping_address TEXT,
            shipping_cost DECIMAL(10,2) DEFAULT 0,
            tax_amount DECIMAL(10,2) DEFAULT 0,
            total_amount DECIMAL(10,2) NOT NULL,
            coupon_id INT DEFAULT NULL,
            coupon_code VARCHAR(50) DEFAULT NULL,
            discount_amount DECIMAL(10,2) DEFAULT 0,
            discount_type VARCHAR(20) DEFAULT NULL,
            status ENUM('pending', 'processing', 'completed', 'shipped', 'cancelled', 'refunded') DEFAULT 'pending',
            payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Create order_items table
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT,
            product_id INT,
            product_title VARCHAR(255),
            product_sku VARCHAR(100),
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Create paypal_settings table
        $pdo->exec("CREATE TABLE IF NOT EXISTS paypal_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(100) UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Create coupons table
        $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
            discount_value DECIMAL(10,2) NOT NULL,
            min_purchase DECIMAL(10,2) DEFAULT 0,
            max_uses INT DEFAULT NULL,
            uses_count INT DEFAULT 0,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Insert default settings
        $pdo->exec("INSERT IGNORE INTO paypal_settings (setting_name, setting_value) VALUES 
            ('client_id', ''),
            ('client_secret', ''),
            ('mode', 'sandbox'),
            ('currency', 'USD'),
            ('business_name', 'Tristate Cards'),
            ('business_email', ''),
            ('shipping_flat_rate', '4.99'),
            ('tax_rate', '0.00');");

        // Refresh table existence status
        foreach (array_keys($tables_exist) as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $tables_exist[$table] = $stmt->rowCount() > 0;
        }

        $setup_result = [
            'success' => true,
            'message' => 'Database tables created successfully!'
        ];
    } catch (PDOException $e) {
        $setup_result = [
            'success' => false,
            'message' => 'Error creating database tables: ' . $e->getMessage()
        ];
    }
}

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-database me-2"></i> PayPal Database Setup</h5>
                    <div>
                        <a href="settings.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-cog me-1"></i> Settings
                        </a>
                        <a href="products.php" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="fas fa-box me-1"></i> Products
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($setup_result): ?>
                        <div class="alert alert-<?php echo $setup_result['success'] ? 'success' : 'danger'; ?> mb-4">
                            <?php echo $setup_result['message']; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h5>Database Table Status</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Status</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>products</code></td>
                                        <td>
                                            <?php if ($tables_exist['products']): ?>
                                                <span class="badge bg-success">Exists</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>Stores product information for the PayPal store</td>
                                    </tr>
                                    <tr>
                                        <td><code>orders</code></td>
                                        <td>
                                            <?php if ($tables_exist['orders']): ?>
                                                <span class="badge bg-success">Exists</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>Stores customer order information</td>
                                    </tr>
                                    <tr>
                                        <td><code>order_items</code></td>
                                        <td>
                                            <?php if ($tables_exist['order_items']): ?>
                                                <span class="badge bg-success">Exists</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>Stores individual items within each order</td>
                                    </tr>
                                    <tr>
                                        <td><code>paypal_settings</code></td>
                                        <td>
                                            <?php if ($tables_exist['paypal_settings']): ?>
                                                <span class="badge bg-success">Exists</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>Stores PayPal integration settings</td>
                                    </tr>
                                    <tr>
                                        <td><code>coupons</code></td>
                                        <td>
                                            <?php if ($tables_exist['coupons']): ?>
                                                <span class="badge bg-success">Exists</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>Stores discount coupon codes</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if (!in_array(false, $tables_exist)): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i> All Required Tables Exist</h6>
                            <p class="mb-0">Your database is properly set up for the PayPal integration. You can now:</p>
                            <ul class="mb-0 mt-2">
                                <li><a href="settings.php">Configure your PayPal settings</a></li>
                                <li><a href="products.php">Manage your products</a></li>
                                <li><a href="orders.php">View and manage orders</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i> Database Setup Required</h6>
                            <p>One or more required tables are missing. Click the button below to set up your database.</p>
                            <form method="post" action="setup_database.php" class="mt-3">
                                <input type="hidden" name="action" value="setup_database">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-database me-1"></i> Set Up Database Tables
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">Database Schema Information</h6>
                        </div>
                        <div class="card-body">
                            <p>The PayPal integration uses the following database tables:</p>
                            
                            <div class="mb-3">
                                <h6>products</h6>
                                <p>Stores information about products available for purchase in your store.</p>
                                <ul>
                                    <li><code>id</code>: Auto-incrementing primary key</li>
                                    <li><code>title</code>: Product name/title</li>
                                    <li><code>description</code>: Detailed product description</li>
                                    <li><code>price</code>: Product price</li>
                                    <li><code>image_url</code>: URL to product image</li>
                                    <li><code>inventory</code>: Current stock level</li>
                                    <li><code>category</code>: Product category</li>
                                    <li><code>status</code>: Product status (active, draft, archived)</li>
                                    <li><code>featured</code>: Whether product is featured on homepage</li>
                                    <li><code>sku</code>: Unique product identifier</li>
                                    <li><code>weight</code>: Product weight (for shipping)</li>
                                    <li><code>dimensions</code>: Product dimensions (for shipping)</li>
                                </ul>
                            </div>
                            
                            <div class="mb-3">
                                <h6>orders</h6>
                                <p>Stores information about customer orders.</p>
                                <ul>
                                    <li><code>id</code>: Auto-incrementing primary key</li>
                                    <li><code>order_reference</code>: Unique order reference number</li>
                                    <li><code>paypal_order_id</code>: PayPal's order ID</li>
                                    <li><code>paypal_payer_id</code>: PayPal's payer ID</li>
                                    <li><code>customer_name</code>: Customer's name</li>
                                    <li><code>customer_email</code>: Customer's email</li>
                                    <li><code>billing_address</code>: Billing address</li>
                                    <li><code>shipping_address</code>: Shipping address</li>
                                    <li><code>shipping_cost</code>: Cost of shipping</li>
                                    <li><code>tax_amount</code>: Tax amount</li>
                                    <li><code>total_amount</code>: Total order amount</li>
                                    <li><code>status</code>: Order status</li>
                                    <li><code>payment_status</code>: Payment status</li>
                                    <li><code>notes</code>: Order notes</li>
                                </ul>
                            </div>
                            
                            <div class="mb-3">
                                <h6>order_items</h6>
                                <p>Stores individual items within each order.</p>
                                <ul>
                                    <li><code>id</code>: Auto-incrementing primary key</li>
                                    <li><code>order_id</code>: Foreign key to orders table</li>
                                    <li><code>product_id</code>: Foreign key to products table</li>
                                    <li><code>product_title</code>: Product title at time of purchase</li>
                                    <li><code>product_sku</code>: Product SKU at time of purchase</li>
                                    <li><code>quantity</code>: Quantity purchased</li>
                                    <li><code>unit_price</code>: Price per unit at time of purchase</li>
                                    <li><code>total_price</code>: Total price for this item</li>
                                </ul>
                            </div>
                            
                            <div>
                                <h6>paypal_settings</h6>
                                <p>Stores PayPal integration settings.</p>
                                <ul>
                                    <li><code>id</code>: Auto-incrementing primary key</li>
                                    <li><code>setting_name</code>: Setting name/key</li>
                                    <li><code>setting_value</code>: Setting value</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
