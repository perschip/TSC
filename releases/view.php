<?php
// Product Release Calendar - View Single Release
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get release ID
$release_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($release_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get release data
try {
    $query = "SELECT * FROM product_releases WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':id' => $release_id]);
    $release = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$release) {
        header('Location: index.php');
        exit;
    }
    
    // Format release date
    $release_date = new DateTime($release['release_date']);
    $formatted_date = $release_date->format('F j, Y');
    $days_until = $release_date->diff(new DateTime())->days;
    $is_future = $release_date > new DateTime();
    
    // Get related releases (same category)
    $related_releases = [];
    if (!empty($release['category'])) {
        $related_query = "SELECT * FROM product_releases 
                         WHERE category = :category 
                         AND id != :id 
                         ORDER BY release_date DESC 
                         LIMIT 4";
        $related_stmt = $pdo->prepare($related_query);
        $related_stmt->execute([
            ':category' => $release['category'],
            ':id' => $release_id
        ]);
        $related_releases = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If not enough related releases by category, get some by date
    if (count($related_releases) < 4) {
        $date_query = "SELECT * FROM product_releases 
                      WHERE id != :id 
                      AND category != :category 
                      ORDER BY ABS(DATEDIFF(release_date, :release_date)) 
                      LIMIT :limit";
        $date_stmt = $pdo->prepare($date_query);
        $date_stmt->execute([
            ':id' => $release_id,
            ':category' => $release['category'] ?? '',
            ':release_date' => $release['release_date'],
            ':limit' => 4 - count($related_releases)
        ]);
        $date_related = $date_stmt->fetchAll(PDO::FETCH_ASSOC);
        $related_releases = array_merge($related_releases, $date_related);
    }
} catch (PDOException $e) {
    error_log('Error fetching release: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Set page title
$page_title = htmlspecialchars($release['title']) . " - Product Release";
include_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Release Calendar</a></li>
                    <?php if (!empty($release['category'])): ?>
                        <li class="breadcrumb-item"><a href="category.php?name=<?php echo urlencode($release['category']); ?>"><?php echo htmlspecialchars($release['category']); ?></a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($release['title']); ?></li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h1 class="card-title mb-3"><?php echo htmlspecialchars($release['title']); ?></h1>
                    
                    <div class="release-meta mb-4">
                        <div class="release-date">
                            <i class="far fa-calendar-alt me-1"></i> Release Date: <strong><?php echo $formatted_date; ?></strong>
                            
                            <?php if ($is_future): ?>
                                <span class="badge bg-success ms-2">
                                    <?php echo $days_until; ?> day<?php echo $days_until !== 1 ? 's' : ''; ?> to go
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary ms-2">Released</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($release['category'])): ?>
                            <div class="release-category mt-2">
                                <i class="fas fa-tag me-1"></i> Category: 
                                <a href="category.php?name=<?php echo urlencode($release['category']); ?>" class="category-link">
                                    <?php echo htmlspecialchars($release['category']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($release['image_url'])): ?>
                        <div class="release-image-container mb-4">
                            <img src="<?php echo htmlspecialchars($release['image_url']); ?>" alt="<?php echo htmlspecialchars($release['title']); ?>" class="img-fluid rounded">
                        </div>
                    <?php endif; ?>
                    
                    <div class="release-description mb-4">
                        <?php if (!empty($release['description'])): ?>
                            <div class="card-text">
                                <?php echo nl2br(htmlspecialchars($release['description'])); ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No description available for this release.</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($release['product_url'])): ?>
                        <div class="release-actions">
                            <a href="<?php echo htmlspecialchars($release['product_url']); ?>" class="btn btn-primary" target="_blank">
                                <?php echo $is_future ? 'Pre-order Now' : 'View Product'; ?>
                            </a>
                            
                            <!-- Social sharing buttons -->
                            <div class="social-share mt-3">
                                <span class="me-2">Share:</span>
                                <?php
                                $share_url = urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
                                $share_title = urlencode($release['title']);
                                ?>
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" class="btn btn-sm btn-outline-primary me-1" target="_blank" rel="noopener">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" class="btn btn-sm btn-outline-info me-1" target="_blank" rel="noopener">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="mailto:?subject=<?php echo $share_title; ?>&body=Check out this upcoming release: <?php echo $share_url; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Countdown Timer for upcoming releases -->
            <?php if ($is_future): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Countdown to Release</h4>
                    </div>
                    <div class="card-body">
                        <div class="countdown-timer" data-release-date="<?php echo $release['release_date']; ?>">
                            <div class="row text-center">
                                <div class="col-3">
                                    <div class="countdown-value days">--</div>
                                    <div class="countdown-label">Days</div>
                                </div>
                                <div class="col-3">
                                    <div class="countdown-value hours">--</div>
                                    <div class="countdown-label">Hours</div>
                                </div>
                                <div class="col-3">
                                    <div class="countdown-value minutes">--</div>
                                    <div class="countdown-label">Minutes</div>
                                </div>
                                <div class="col-3">
                                    <div class="countdown-value seconds">--</div>
                                    <div class="countdown-label">Seconds</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
            
            <!-- Related Releases -->
            <?php if (!empty($related_releases)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h4 class="mb-0">Related Releases</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($related_releases as $related): ?>
                                <a href="view.php?id=<?php echo $related['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 align-items-center">
                                        <?php if (!empty($related['image_url'])): ?>
                                            <div class="related-image-container me-3">
                                                <img src="<?php echo htmlspecialchars($related['image_url']); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>" class="related-image">
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($related['title']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($related['release_date'])); ?>
                                            </small>
                                            <?php if (!empty($related['category'])): ?>
                                                <br>
                                                <small class="badge bg-secondary"><?php echo htmlspecialchars($related['category']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
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
/* Release View Styles */
.release-meta {
    color: #6c757d;
}

.category-link {
    color: #6c757d;
    text-decoration: none;
    background-color: #f8f9fa;
    padding: 2px 8px;
    border-radius: 4px;
}

.category-link:hover {
    background-color: #e9ecef;
    text-decoration: none;
}

.release-image-container {
    text-align: center;
}

.release-image-container img {
    max-height: 400px;
    width: auto;
    max-width: 100%;
}

.related-image-container {
    width: 60px;
    height: 60px;
    overflow: hidden;
    border-radius: 4px;
    flex-shrink: 0;
}

.related-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Countdown Timer */
.countdown-timer {
    padding: 10px 0;
}

.countdown-value {
    font-size: 2rem;
    font-weight: bold;
    color: #007bff;
}

.countdown-label {
    font-size: 0.9rem;
    color: #6c757d;
}
</style>

<script>
// Countdown Timer
document.addEventListener('DOMContentLoaded', function() {
    const countdownElement = document.querySelector('.countdown-timer');
    if (countdownElement) {
        const releaseDate = new Date(countdownElement.dataset.releaseDate).getTime();
        
        // Update the countdown every second
        const countdownTimer = setInterval(function() {
            // Get current date and time
            const now = new Date().getTime();
            
            // Calculate the time remaining
            const distance = releaseDate - now;
            
            // Time calculations
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            // Update the HTML
            document.querySelector('.countdown-value.days').textContent = days;
            document.querySelector('.countdown-value.hours').textContent = hours;
            document.querySelector('.countdown-value.minutes').textContent = minutes;
            document.querySelector('.countdown-value.seconds').textContent = seconds;
            
            // If the countdown is finished
            if (distance < 0) {
                clearInterval(countdownTimer);
                document.querySelector('.countdown-timer').innerHTML = '<div class="alert alert-success">This product has been released!</div>';
            }
        }, 1000);
    }
    
    // Newsletter subscription
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
