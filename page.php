<?php
// Page handler for clean URLs
// Get page slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    // If no slug provided, redirect to homepage
    header('Location: /');
    exit;
}

// Include necessary files
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get the page data from database
try {
    $query = "SELECT * FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':slug', $slug);
    $stmt->execute();
    $page = $stmt->fetch();
    
    if (!$page) {
        // Page not found or not published - show 404 error
        header("HTTP/1.0 404 Not Found");
        include '404.php';
        exit;
    }

    // Set page variables
    $page_title = $page['title'];
    $meta_description = $page['meta_description'] ?? '';
    $page_content = $page['content'];
    
    // Include header
    include 'includes/header.php';
?>

<!-- Page Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
    </div>
</section>

<!-- Main Content Section -->
<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5">
                    <?php echo $page_content; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    // Include footer
    include 'includes/footer.php';

} catch (PDOException $e) {
    // Database error
    error_log('Database error in page.php: ' . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    echo "An error occurred while processing your request.";
}
?>