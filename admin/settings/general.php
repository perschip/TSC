<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Ensure user is an admin
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $settings = [
        // General Site Settings
        'site_name' => trim($_POST['site_name']),
        'site_description' => trim($_POST['site_description']),
        'contact_email' => trim($_POST['contact_email']),
        'contact_phone' => trim($_POST['contact_phone']),
        'contact_address' => trim($_POST['contact_address']),
        
        // SEO Settings
        'google_analytics_id' => trim($_POST['google_analytics_id']),
        'meta_keywords' => trim($_POST['meta_keywords']),
        
        // Social Media Settings
        'social_facebook' => trim($_POST['social_facebook']),
        'social_twitter' => trim($_POST['social_twitter']),
        'social_instagram' => trim($_POST['social_instagram']),
        'social_youtube' => trim($_POST['social_youtube']),
        
        // Integration APIs
        'tinymce_api_key' => trim($_POST['tinymce_api_key']),
        'ebay_seller_id' => trim($_POST['ebay_seller_id'])
    ];
    
    // Validate inputs
    $errors = [];
    
    if (empty($settings['site_name'])) {
        $errors[] = 'Site name is required';
    }
    
    // Only validate email if it's not empty
    if (!empty($settings['contact_email']) && !filter_var($settings['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Remove the URL validation for social media links entirely - let them be any string
    // We'll just ensure they have http:// or https:// prefix if provided
    foreach (['social_facebook', 'social_twitter', 'social_instagram', 'social_youtube'] as $social) {
        if (!empty($settings[$social])) {
            // If URL doesn't start with http:// or https://, add https://
            if (strpos($settings[$social], 'http://') !== 0 && strpos($settings[$social], 'https://') !== 0) {
                $settings[$social] = 'https://' . $settings[$social];
            }
        }
    }
    
    // If no errors, update settings
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update each setting in the database
            foreach ($settings as $key => $value) {
                updateSetting($key, $value);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $_SESSION['success_message'] = 'Settings updated successfully!';
            
            // Redirect to refresh the page
            header('Location: general.php');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get current settings
$site_name = getSetting('site_name', 'Tristate Cards');
$site_description = getSetting('site_description', 'Your trusted source for sports cards, collectibles, and memorabilia');
$contact_email = getSetting('contact_email', 'info@tristatecards.com');
$contact_phone = getSetting('contact_phone', '(201) 555-1234');
$contact_address = getSetting('contact_address', 'Hoffman, New Jersey, US');
$google_analytics_id = getSetting('google_analytics_id', '');
$meta_keywords = getSetting('meta_keywords', 'sports cards, trading cards, collectibles, memorabilia, card breaks, eBay listings, Whatnot');
$social_facebook = getSetting('social_facebook', '#');
$social_twitter = getSetting('social_twitter', '#');
$social_instagram = getSetting('social_instagram', '#');
$social_youtube = getSetting('social_youtube', '#');
$tinymce_api_key = getSetting('tinymce_api_key', 'no-api-key');
$ebay_seller_id = getSetting('ebay_seller_id', 'tristate_cards');

// Page variables
$page_title = 'General Settings';
$header_actions = '
<a href="/admin/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
</a>
';

// Include admin header
include_once '../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="general.php" method="post">
    <div class="row">
        <div class="col-lg-8">
            <!-- General Site Settings Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">General Site Settings</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="site_name" class="form-label">Site Name *</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" required>
                        <div class="form-text">This is your website's main title that appears in the header.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="site_description" class="form-label">Site Description</label>
                        <textarea class="form-control" id="site_description" name="site_description" rows="2"><?php echo htmlspecialchars($site_description); ?></textarea>
                        <div class="form-text">A brief description of your website. This appears in search engines and meta tags.</div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="fw-bold mb-3">Contact Information</h6>
                    
                    <div class="mb-3">
                        <label for="contact_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($contact_email); ?>">
                        <div class="form-text">Main contact email displayed on your website.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_phone" class="form-label">Contact Phone</label>
                        <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($contact_phone); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_address" class="form-label">Contact Address</label>
                        <textarea class="form-control" id="contact_address" name="contact_address" rows="2"><?php echo htmlspecialchars($contact_address); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Integration API Settings -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">API Integrations</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="tinymce_api_key" class="form-label">TinyMCE API Key</label>
                        <input type="text" class="form-control" id="tinymce_api_key" name="tinymce_api_key" value="<?php echo htmlspecialchars($tinymce_api_key); ?>">
                        <div class="form-text">Your TinyMCE API key for the WYSIWYG editor. <a href="https://www.tiny.cloud/auth/signup/" target="_blank">Register for a free API key</a>.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ebay_seller_id" class="form-label">eBay Seller ID</label>
                        <input type="text" class="form-control" id="ebay_seller_id" name="ebay_seller_id" value="<?php echo htmlspecialchars($ebay_seller_id); ?>">
                        <div class="form-text">Your eBay seller ID for displaying listings on your site.</div>
                    </div>
                </div>
            </div>
            
            <!-- SEO Settings -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">SEO Settings</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="google_analytics_id" class="form-label">Google Analytics Tracking ID</label>
                        <input type="text" class="form-control" id="google_analytics_id" name="google_analytics_id" value="<?php echo htmlspecialchars($google_analytics_id); ?>" placeholder="UA-XXXXXXXXX-X or G-XXXXXXXXXX">
                        <div class="form-text">Your Google Analytics tracking ID. Leave empty to disable analytics.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="meta_keywords" class="form-label">Meta Keywords</label>
                        <textarea class="form-control" id="meta_keywords" name="meta_keywords" rows="2"><?php echo htmlspecialchars($meta_keywords); ?></textarea>
                        <div class="form-text">Keywords for your site, separated by commas.</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Social Media Settings -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Social Media</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="social_facebook" class="form-label">Facebook URL</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fab fa-facebook-f"></i></span>
                            <input type="text" class="form-control" id="social_facebook" name="social_facebook" value="<?php echo htmlspecialchars($social_facebook); ?>" placeholder="https://facebook.com/your-page">
                        </div>
                        <div class="form-text">Optional. Leave empty if you don't have a Facebook page.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="social_twitter" class="form-label">Twitter URL</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fab fa-twitter"></i></span>
                            <input type="text" class="form-control" id="social_twitter" name="social_twitter" value="<?php echo htmlspecialchars($social_twitter); ?>" placeholder="https://twitter.com/your-handle">
                        </div>
                        <div class="form-text">Optional. Leave empty if you don't have a Twitter account.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="social_instagram" class="form-label">Instagram URL</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fab fa-instagram"></i></span>
                            <input type="text" class="form-control" id="social_instagram" name="social_instagram" value="<?php echo htmlspecialchars($social_instagram); ?>" placeholder="https://instagram.com/your-handle">
                        </div>
                        <div class="form-text">Optional. Leave empty if you don't have an Instagram account.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="social_youtube" class="form-label">YouTube URL</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fab fa-youtube"></i></span>
                            <input type="text" class="form-control" id="social_youtube" name="social_youtube" value="<?php echo htmlspecialchars($social_youtube); ?>" placeholder="https://youtube.com/your-channel">
                        </div>
                        <div class="form-text">Optional. Leave empty if you don't have a YouTube channel.</div>
                    </div>
                </div>
            </div>
            
            <!-- Tools Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Useful Tools</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/emoji_converter.php" class="btn btn-outline-primary btn-sm" target="_blank">
                            <i class="fas fa-smile me-1"></i> Emoji Database Converter
                        </a>
                        <a href="/ebay-listings-test.php" class="btn btn-outline-primary btn-sm" target="_blank">
                            <i class="fas fa-tag me-1"></i> eBay Listings Test
                        </a>
                        <a href="/ebay-troubleshooter.php" class="btn btn-outline-primary btn-sm" target="_blank">
                            <i class="fas fa-wrench me-1"></i> eBay Troubleshooter
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-1"></i> Save Settings
                </button>
            </div>
            
        </div>
    </div>
</form>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>