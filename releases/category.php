<?php
// Product Release Calendar - Category View
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get category name
$category_name = isset($_GET['name']) ? trim($_GET['name']) : '';

if (empty($category_name)) {
    header('Location: index.php');
    exit;
}

// Get releases for this category
try {
    $query = "SELECT * FROM product_releases 
              WHERE category = :category 
              ORDER BY release_date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':category' => $category_name]);
    $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($releases)) {
        header('Location: index.php');
        exit;
    }
    
    // Group releases by month/year
    $releases_by_month = [];
    foreach ($releases as $release) {
        $month_year = date('F Y', strtotime($release['release_date']));
        if (!isset($releases_by_month[$month_year])) {
            $releases_by_month[$month_year] = [];
        }
        $releases_by_month[$month_year][] = $release;
    }
    
    // Count upcoming releases
    $upcoming_count = 0;
    $today = date('Y-m-d');
    foreach ($releases as $release) {
        if ($release['release_date'] >= $today) {
            $upcoming_count++;
        }
    }
} catch (PDOException $e) {
    error_log('Error fetching releases: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Get all categories for the sidebar
try {
    $categories = [];
    $cat_query = "SELECT DISTINCT category, COUNT(*) as count FROM product_releases 
                 WHERE category IS NOT NULL AND category != '' 
                 GROUP BY category 
                 ORDER BY category";
    $cat_stmt = $pdo->query($cat_query);
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Set page title
$page_title = htmlspecialchars($category_name) . " Releases - Product Calendar";
include_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Release Calendar</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($category_name); ?></li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0"><?php echo htmlspecialchars($category_name); ?> Releases</h1>
                </div>
                <div class="card-body">
                    <div class="category-stats mb-4">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-value"><?php echo count($releases); ?></div>
                                <div class="stat-label">Total Releases</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value"><?php echo $upcoming_count; ?></div>
                                <div class="stat-label">Upcoming</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value"><?php echo count($releases) - $upcoming_count; ?></div>
                                <div class="stat-label">Released</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($releases_by_month)): ?>
                        <?php foreach ($releases_by_month as $month_year => $month_releases): ?>
                            <div class="month-section mb-4">
                                <h3 class="month-heading"><?php echo $month_year; ?></h3>
                                <div class="list-group">
                                    <?php foreach ($month_releases as $release): ?>
                                        <?php
                                        $release_date = new DateTime($release['release_date']);
                                        $today = new DateTime();
                                        $is_upcoming = $release_date >= $today;
                                        ?>
                                        <a href="view.php?id=<?php echo $release['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="row align-items-center">
                                                <?php if (!empty($release['image_url'])): ?>
                                                    <div class="col-md-2 col-sm-3">
                                                        <img src="<?php echo htmlspecialchars($release['image_url']); ?>" alt="<?php echo htmlspecialchars($release['title']); ?>" class="img-fluid rounded">
                                                    </div>
                                                    <div class="col-md-10 col-sm-9">
                                                <?php else: ?>
                                                    <div class="col-12">
                                                <?php endif; ?>
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($release['title']); ?></h5>
                                                            <small><?php echo $release_date->format('M j, Y'); ?></small>
                                                        </div>
                                                        <?php if (!empty($release['description'])): ?>
                                                            <p class="mb-1 text-muted small">
                                                                <?php echo substr(htmlspecialchars($release['description']), 0, 120); ?>
                                                                <?php if (strlen($release['description']) > 120): ?>...<?php endif; ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <?php if ($is_upcoming): ?>
                                                            <span class="badge bg-success">Upcoming</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Released</span>
                                                        <?php endif; ?>
                                                        <?php if ($release['is_featured']): ?>
                                                            <span class="badge bg-warning text-dark">Featured</span>
                                                        <?php endif; ?>
                                                    </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No releases found for this category.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Calendar Navigation -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Release Calendar</h4>
                </div>
                <div class="card-body">
                    <p>View our complete release schedule to plan your purchases and never miss a drop!</p>
                    <a href="index.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-calendar-alt me-1"></i> View Calendar
                    </a>
                </div>
            </div>
            
            <!-- Categories -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">Categories</h4>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($categories as $cat): ?>
                            <a href="category.php?name=<?php echo urlencode($cat['category']); ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo ($cat['category'] === $category_name) ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($cat['category']); ?>
                                <span class="badge <?php echo ($cat['category'] === $category_name) ? 'bg-light text-primary' : 'bg-primary'; ?> rounded-pill">
                                    <?php echo $cat['count']; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Email Notification -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">Get Notified</h4>
                </div>
                <div class="card-body">
                    <p>Subscribe to our newsletter to get notified about upcoming releases and promotions.</p>
                    <form id="notification-form" class="mb-0">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Your email address" required>
                            <button class="btn btn-primary" type="submit">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Category View Styles */
.category-stats {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #007bff;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
}

.month-heading {
    font-size: 1.5rem;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.list-group-item img {
    max-height: 80px;
    object-fit: cover;
}
</style>

<script>
// Newsletter subscription
document.addEventListener('DOMContentLoaded', function() {
    const notificationForm = document.getElementById('notification-form');
    if (notificationForm) {
        notificationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Thank you for subscribing! We will notify you about upcoming releases.');
            this.reset();
        });
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
