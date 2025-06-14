<?php
// Use relative paths for includes
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ebay-listings.php';

// Check if Whatnot status needs update
if (time() - strtotime(getSetting('whatnot_last_check', date('Y-m-d H:i:s'))) > 60 * (int)getSetting('whatnot_check_interval', 15)) {
    checkWhatnotStatus();
    updateSetting('whatnot_last_check', date('Y-m-d H:i:s'));
}

// Get current Whatnot status
try {
    $status_query = "SELECT * FROM whatnot_status ORDER BY id DESC LIMIT 1";
    $status_stmt = $pdo->prepare($status_query);
    $status_stmt->execute();
    $whatnot_status = $status_stmt->fetch();
} catch (PDOException $e) {
    $whatnot_status = null;
}

// Set page variables
$page_title = ''; // Homepage doesn't need a specific title prefix

// Add eBay listings CSS
$extra_css = '/assets/css/ebay-listings.css';

// Get featured listings (favorites)
$featured_listings = getFrontendEbayListings($pdo, [
    'featured_only' => true,
    'limit' => 9, // For carousel (3 per slide)
    'sort' => 'last_updated',
    'order' => 'DESC'
]);

// Get selected category from URL
$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Get current page from URL
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 9; // 9 listings per page (3x3 grid)
$offset = ($current_page - 1) * $items_per_page;

// Get total listings count for pagination
$total_listings = intval(getTotalListingsCount($pdo, $selected_category));
$total_pages = intval(ceil($total_listings / $items_per_page));

// Get regular listings with pagination
$listings = getFrontendEbayListings($pdo, [
    'category' => $selected_category,
    'limit' => $items_per_page,
    'offset' => $offset,
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'last_updated',
    'order' => isset($_GET['order']) ? $_GET['order'] : 'DESC'
]);

// Get categories for filter
$categories = getFrontendCategories($pdo);

// Get category counts
$category_counts = countListingsByCategory($pdo);

// Include header
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1><?php echo htmlspecialchars(getSetting('site_name', 'Tristate Cards')); ?></h1>
        <p class="lead">Discover our latest eBay listings and Whatnot streams</p>
        <div class="hero-cta mt-4">
            <a href="#featured-listings" class="btn btn-primary btn-lg me-2">View Featured Cards</a>
            <a href="<?php echo (!empty($whatnot_status) && isset($whatnot_status['status']) && $whatnot_status['status'] === 'live' && isset($whatnot_status['stream_url'])) ? htmlspecialchars($whatnot_status['stream_url']) : 'https://www.whatnot.com/user/' . htmlspecialchars($whatnot_username ?? 'tscardbreaks'); ?>" target="_blank" class="btn btn-outline-light btn-lg">
                <?php echo (!empty($whatnot_status) && isset($whatnot_status['status']) && $whatnot_status['status'] === 'live') ? '<i class="fas fa-circle text-danger me-2 pulse"></i>Watch Live Now' : '<i class="fas fa-video me-2"></i>Check Whatnot'; ?>
            </a>
        </div>
    </div>
</section>

<div class="container">
    <div class="row">
        <div class="col-lg-8">
            <!-- eBay Listings Section -->
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title mb-4">Current eBay Listings</h2>
                    
                    <!-- Featured Listings Section -->
                    <h3 class="mb-3" id="featured-listings">Featured Listings</h3>
                    
                    <?php if (!empty($featured_listings)): ?>
                    <!-- Featured Listings Carousel (3 per slide) -->
                    <div id="featuredListingsCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                        <!-- Carousel indicators -->
                        <div class="carousel-indicators">
                            <?php 
                            $total_slides = ceil(count($featured_listings) / 3);
                            for ($i = 0; $i < $total_slides; $i++): 
                            ?>
                                <button type="button" data-bs-target="#featuredListingsCarousel" data-bs-slide-to="<?php echo $i; ?>" <?php echo ($i === 0) ? 'class="active" aria-current="true"' : ''; ?> aria-label="Slide <?php echo $i + 1; ?>"></button>
                            <?php endfor; ?>
                        </div>
                        
                        <!-- Carousel slides -->
                        <div class="carousel-inner">
                            <?php 
                            $chunks = array_chunk($featured_listings, 3); // Split listings into groups of 3
                            foreach ($chunks as $index => $slide_listings): 
                            ?>
                                <div class="carousel-item <?php echo ($index === 0) ? 'active' : ''; ?>">
                                    <div class="row row-cols-1 row-cols-md-3 g-4">
                                        <?php foreach ($slide_listings as $listing): ?>
                                            <div class="col">
                                                <div class="card h-100">
                                                    <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" target="_blank" class="card-link">
                                                        <div class="card-img-container">
                                                            <?php if (!empty($listing['image_url'])): ?>
                                                                <img loading="lazy" src="<?php echo htmlspecialchars($listing['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                                                            <?php else: ?>
                                                                <div class="text-center text-muted pt-4">
                                                                    <i class="fas fa-image fa-2x"></i>
                                                                    <p class="mt-1 small">No image</p>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </a>
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-4" style="min-height: 60px;">
                                                            <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" target="_blank" class="text-dark">
                                                                <?php echo htmlspecialchars($listing['title']); ?>
                                                            </a>
                                                        </h5>
                                                    </div>
                                                    <div class="card-footer bg-transparent">
                                                        <!-- Price and category -->
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <p class="card-price mb-0 text-success fw-bold">$<?php echo number_format($listing['price'], 2); ?></p>
                                                            <?php if (!empty($listing['category'])): ?>
                                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($listing['category']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- View button -->
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">Featured</small>
                                                            <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" class="btn btn-sm btn-primary" target="_blank">View on eBay</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Carousel controls -->
                        <button class="carousel-control-prev" type="button" data-bs-target="#featuredListingsCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#featuredListingsCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">No featured listings available at this time.</div>
                    <?php endif; ?>
                    
                    <hr class="my-5">
                    
                    <!-- Current Listings Section -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3>Recent Listings</h3>
                        <a href="listings.php" class="btn btn-outline-primary">View All Listings</a>
                    </div>
                    
                    <!-- Listings Grid -->
                    <div id="ebay-listings">
                        <?php if (empty($listings)): ?>
                        <div class="listings-empty-state">
                            <i class="fas fa-search"></i>
                            <h4>No listings found</h4>
                            <p>We couldn't find any listings matching your criteria.</p>
                            <a href="index.php" class="btn btn-outline-primary">View all listings</a>
                        </div>
                        <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-3 g-4">
                            <?php foreach ($listings as $listing): ?>
                                <div class="col">
                                    <div class="card h-100 listing-card">
                                        <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" target="_blank" class="card-link">
                                            <div class="card-img-container">
                                                <?php if (!empty($listing['image_url'])): ?>
                                                    <img loading="lazy" src="<?php echo htmlspecialchars($listing['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                                                <?php else: ?>
                                                    <div class="text-center text-muted pt-4">
                                                        <i class="fas fa-image fa-2x"></i>
                                                        <p class="mt-1 small">No image</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                        <div class="card-body" style="padding-bottom: 20px;">
                                            <h5 class="card-title mb-4" style="min-height: 60px;">
                                                <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" target="_blank" class="text-dark">
                                                    <?php echo htmlspecialchars($listing['title']); ?>
                                                </a>
                                            </h5>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <!-- Price and category -->
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <p class="card-price mb-0 text-success fw-bold">$<?php echo number_format($listing['price'], 2); ?></p>
                                                <?php if (!empty($listing['category'])): ?>
                                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($listing['category']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Quantity and view button -->
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">Qty: <?php echo $listing['quantity']; ?></small>
                                                <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" class="btn btn-sm btn-primary" target="_blank">View on eBay</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <a href="listings.php" class="btn btn-primary">View All Listings</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Whatnot Status -->
            <?php if ($whatnot_status): ?>
                <?php if ($whatnot_status['is_live']): ?>
                    <!-- Live Stream Status -->
                    <div class="whatnot-status whatnot-live">
                        <h4><span class="status-indicator status-live"></span> LIVE NOW!</h4>
                        <div class="mb-3">
                            <p class="fw-bold mb-1"><?php echo htmlspecialchars($whatnot_status['stream_title']); ?></p>
                            <p class="mb-0">Join us for amazing pulls!</p>
                        </div>
                        <a href="<?php echo htmlspecialchars($whatnot_status['stream_url']); ?>" class="btn btn-success btn-sm" target="_blank" 
                           onclick="logWhatnotClick(<?php echo $whatnot_status['id']; ?>)">Watch Live</a>
                    </div>
                <?php elseif ($whatnot_status['scheduled_time'] && strtotime($whatnot_status['scheduled_time']) > time()): ?>
                    <!-- Upcoming Stream Status -->
                    <div class="whatnot-status whatnot-upcoming">
                        <h4><span class="status-indicator status-upcoming"></span> Next Stream</h4>
                        <div class="mb-3">
                            <p class="fw-bold mb-1"><?php echo htmlspecialchars($whatnot_status['stream_title']); ?></p>
                            <p class="mb-0"><?php echo date('F j, Y \a\t g:i A', strtotime($whatnot_status['scheduled_time'])); ?></p>
                        </div>
                        <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars(getSetting('whatnot_username', 'tristate_cards')); ?>" class="btn btn-primary btn-sm" target="_blank"
                           onclick="logWhatnotClick(<?php echo $whatnot_status['id']; ?>)">Follow on Whatnot</a>
                    </div>
                <?php else: ?>
                    <!-- Default Whatnot Promo -->
                    <div class="whatnot-status">
                        <h4>Find Us on Whatnot</h4>
                        <div class="mb-3">
                            <p class="mb-0">Follow us for live breaks and exclusive deals!</p>
                        </div>
                        <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars(getSetting('whatnot_username', 'tristate_cards')); ?>" class="btn btn-primary btn-sm" target="_blank"
                           onclick="logWhatnotClick(0)">Follow on Whatnot</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Default Whatnot Promo -->
                <div class="whatnot-status">
                    <h4>Find Us on Whatnot</h4>
                    <div class="mb-3">
                        <p class="mb-0">Follow us for live breaks and exclusive deals!</p>
                    </div>
                    <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars(getSetting('whatnot_username', 'tristate_cards')); ?>" class="btn btn-primary btn-sm" target="_blank">Follow on Whatnot</a>
                </div>
            <?php endif; ?>
            
            <!-- Newsletter Signup -->
            <div class="card mt-4">
                <div class="card-body">
                    <h4 class="card-title">Stay Updated</h4>
                    <p>Subscribe to our newsletter for exclusive deals and updates.</p>
                    <form id="newsletter-form">
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Your email" required>
                            <button type="submit" class="btn btn-primary">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Social Links -->
            <div class="card mt-4">
                <div class="card-body">
                    <h4 class="card-title">Connect With Us</h4>
                    <div class="social-links mt-3">
                        <?php if ($ebay_username = getSetting('ebay_seller_id')): ?>
                            <a href="https://www.ebay.com/usr/<?php echo htmlspecialchars($ebay_username); ?>" class="btn btn-outline-secondary me-2 mb-2" target="_blank">
                                <i class="fab fa-ebay"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($whatnot_username = getSetting('whatnot_username')): ?>
                            <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars($whatnot_username); ?>" class="btn btn-outline-secondary me-2 mb-2" target="_blank">
                                <i class="fas fa-video"></i> Whatnot
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($instagram = getSetting('instagram_username')): ?>
                            <a href="https://www.instagram.com/<?php echo htmlspecialchars($instagram); ?>" class="btn btn-outline-secondary me-2 mb-2" target="_blank">
                                <i class="fab fa-instagram"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($twitter = getSetting('twitter_username')): ?>
                            <a href="https://twitter.com/<?php echo htmlspecialchars($twitter); ?>" class="btn btn-outline-secondary me-2 mb-2" target="_blank">
                                <i class="fab fa-twitter"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Testimonials -->
            <div class="card mt-4">
                <div class="card-body">
                    <h4 class="card-title">Customer Testimonials</h4>
                    <?php
                    // Get featured testimonials
                    try {
                        $testimonials_query = "SELECT * FROM testimonials WHERE status = 'published' AND is_featured = 1 ORDER BY created_at DESC LIMIT 2";
                        $testimonials_stmt = $pdo->prepare($testimonials_query);
                        $testimonials_stmt->execute();
                        $featured_testimonials = $testimonials_stmt->fetchAll();
                        
                        if (!empty($featured_testimonials)) {
                            foreach ($featured_testimonials as $index => $testimonial) {
                                ?>
                                <div class="testimonial">
                                    <p class="testimonial-text">"<?php echo htmlspecialchars($testimonial['content']); ?>"</p>
                                    <p class="testimonial-author">- <?php echo htmlspecialchars($testimonial['author_name']); ?></p>
                                </div>
                                <?php
                                if ($index < count($featured_testimonials) - 1) {
                                    echo '<hr>';
                                }
                            }
                        } else {
                            // Fallback to default testimonials if none are in the database
                            ?>
                            <div class="testimonial">
                                <p class="testimonial-text">"Great selection of cards and fast shipping! Will definitely be buying from Tristate Cards again."</p>
                                <p class="testimonial-author">- John D.</p>
                            </div>
                            <hr>
                            <div class="testimonial">
                                <p class="testimonial-text">"The cards I ordered were in perfect condition and arrived well-packaged. Excellent service!"</p>
                                <p class="testimonial-author">- Sarah T.</p>
                            </div>
                            <?php
                        }
                    } catch (PDOException $e) {
                        // If there's a database error, show the default testimonials
                        ?>
                        <div class="testimonial">
                            <p class="testimonial-text">"Great selection of cards and fast shipping! Will definitely be buying from Tristate Cards again."</p>
                            <p class="testimonial-author">- John D.</p>
                        </div>
                        <hr>
                        <div class="testimonial">
                            <p class="testimonial-text">"The cards I ordered were in perfect condition and arrived well-packaged. Excellent service!"</p>
                            <p class="testimonial-author">- Sarah T.</p>
                        </div>
                        <?php
                    }
                    ?>
                    <div class="text-center mt-3">
                        <a href="testimonials.php" class="btn btn-sm btn-outline-primary">See All Testimonials</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Track Whatnot clicks -->
<script>
function logWhatnotClick(streamId) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'track_click.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('type=whatnot&stream_id=' + streamId);
}

// Newsletter form submission
document.getElementById('newsletter-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const email = this.querySelector('input[type="email"]').value;
    
    // Here you would typically send this to your backend
    alert('Thanks for subscribing! We\'ll keep you updated with the latest news.');
    this.reset();
});
</script>

<?php include 'includes/footer.php'; ?>
