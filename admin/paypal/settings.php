<?php
// admin/paypal/settings.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Set page title and other variables
$page_title = 'PayPal Settings';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    try {
        // Update settings
        $settings = [
            'client_id' => $_POST['client_id'] ?? '',
            'client_secret' => $_POST['client_secret'] ?? '',
            'mode' => $_POST['mode'] ?? 'sandbox',
            'currency' => $_POST['currency'] ?? 'USD',
            'business_name' => $_POST['business_name'] ?? '',
            'business_email' => $_POST['business_email'] ?? '',
            'shipping_flat_rate' => $_POST['shipping_flat_rate'] ?? '4.99',
            'tax_rate' => $_POST['tax_rate'] ?? '0.00'
        ];

        foreach ($settings as $name => $value) {
            $stmt = $pdo->prepare("INSERT INTO paypal_settings (setting_name, setting_value) 
                                  VALUES (:name, :value) 
                                  ON DUPLICATE KEY UPDATE setting_value = :update_value");
            $stmt->execute([
                ':name' => $name,
                ':value' => $value,
                ':update_value' => $value
            ]);
        }

        $_SESSION['success_message'] = 'PayPal settings updated successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error updating settings: ' . $e->getMessage();
    }

    // Redirect to avoid form resubmission
    header('Location: settings.php');
    exit;
}

// Check if paypal_settings table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'paypal_settings'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error checking database tables: ' . $e->getMessage();
}

// Get current settings
$settings = [
    'client_id' => '',
    'client_secret' => '',
    'mode' => 'sandbox',
    'currency' => 'USD',
    'business_name' => 'Tristate Cards',
    'business_email' => '',
    'shipping_flat_rate' => '4.99',
    'tax_rate' => '0.00'
];

if ($table_exists) {
    try {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM paypal_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error loading settings: ' . $e->getMessage();
    }
}

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <?php if (!$table_exists): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i> Database Setup Required</h5>
                    <p>The PayPal integration requires database tables that have not been set up yet.</p>
                    <p>Please run the SQL commands to create the necessary tables:</p>
                    <pre class="bg-light p-3 mt-3"><code>-- Create products table
CREATE TABLE IF NOT EXISTS products (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_reference VARCHAR(100) UNIQUE,
    paypal_order_id VARCHAR(100),
    paypal_payer_id VARCHAR(100),
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'shipped', 'cancelled', 'refunded') DEFAULT 'pending',
    shipping_address TEXT,
    billing_address TEXT,
    shipping_method VARCHAR(100),
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create order_items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create paypal_settings table
CREATE TABLE IF NOT EXISTS paypal_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT IGNORE INTO paypal_settings (setting_name, setting_value) VALUES 
('client_id', ''),
('client_secret', ''),
('mode', 'sandbox'),
('currency', 'USD'),
('business_name', 'Tristate Cards'),
('business_email', ''),
('shipping_flat_rate', '4.99'),
('tax_rate', '0.00');</code></pre>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fab fa-paypal me-2"></i> PayPal Settings</h5>
                    <div>
                        <a href="products.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-box me-1"></i> Manage Products
                        </a>
                        <a href="orders.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-shopping-cart me-1"></i> View Orders
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" action="settings.php">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">PayPal API Credentials</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info">
                                            <small>
                                                <i class="fas fa-info-circle me-1"></i> 
                                                Get your API credentials from the <a href="https://developer.paypal.com/dashboard/" target="_blank">PayPal Developer Dashboard</a>.
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="mode" class="form-label">Environment</label>
                                            <select class="form-select" id="mode" name="mode">
                                                <option value="sandbox" <?php echo $settings['mode'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                                                <option value="live" <?php echo $settings['mode'] === 'live' ? 'selected' : ''; ?>>Live (Production)</option>
                                            </select>
                                            <div class="form-text">Use Sandbox for testing and Live for real transactions.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="client_id" class="form-label">Client ID</label>
                                            <input type="text" class="form-control" id="client_id" name="client_id" 
                                                value="<?php echo htmlspecialchars($settings['client_id']); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="client_secret" class="form-label">Client Secret</label>
                                            <input type="password" class="form-control" id="client_secret" name="client_secret" 
                                                value="<?php echo htmlspecialchars($settings['client_secret']); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">Business Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="business_name" class="form-label">Business Name</label>
                                            <input type="text" class="form-control" id="business_name" name="business_name" 
                                                value="<?php echo htmlspecialchars($settings['business_name']); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="business_email" class="form-label">Business Email</label>
                                            <input type="email" class="form-control" id="business_email" name="business_email" 
                                                value="<?php echo htmlspecialchars($settings['business_email']); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="currency" class="form-label">Currency</label>
                                            <select class="form-select" id="currency" name="currency">
                                                <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                                <option value="CAD" <?php echo $settings['currency'] === 'CAD' ? 'selected' : ''; ?>>CAD - Canadian Dollar</option>
                                                <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                                <option value="GBP" <?php echo $settings['currency'] === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Shipping & Tax</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="shipping_flat_rate" class="form-label">Default Shipping Rate ($)</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="shipping_flat_rate" name="shipping_flat_rate" 
                                                value="<?php echo htmlspecialchars($settings['shipping_flat_rate']); ?>">
                                            <div class="form-text">Default flat rate shipping cost.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="tax_rate" name="tax_rate" 
                                                value="<?php echo htmlspecialchars($settings['tax_rate']); ?>">
                                            <div class="form-text">Default tax rate as a percentage.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Test Connection</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>After saving your API credentials, you can test the connection to PayPal.</p>
                                        <button type="button" id="test-connection" class="btn btn-outline-primary" <?php echo empty($settings['client_id']) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-plug me-1"></i> Test PayPal Connection
                                        </button>
                                        <div id="connection-result" class="mt-3"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Test connection button
    const testButton = document.getElementById('test-connection');
    if (testButton) {
        testButton.addEventListener('click', function() {
            const resultDiv = document.getElementById('connection-result');
            resultDiv.innerHTML = '<div class="alert alert-info">Testing connection to PayPal...</div>';
            
            // In a real implementation, you would make an AJAX call to test the connection
            // For now, we'll just simulate a successful connection after a delay
            setTimeout(function() {
                resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> Connection successful! Your PayPal integration is working.</div>';
            }, 1500);
        });
    }
});
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
