<?php
// Include database connection and helper functions
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ebay-listings.php';

// Set page variables
$page_title = 'All eBay Listings';

// Add eBay listings CSS
$extra_css = '<link rel="stylesheet" href="assets/css/ebay-listings.css">';

// Get selected category from URL
$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Get current page from URL
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 12; // 12 listings per page (4x3 grid)
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

<!-- Page Header -->
<section class="page-header bg-light py-4 mb-4">
    <div class="container">
        <h1 class="mb-0">All eBay Listings</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">All Listings</li>
            </ol>
        </nav>
    </div>
</section>

<div class="container">
    <div class="row">
        <div class="col-lg-12">
            <!-- eBay Listings Section -->
            <div class="card">
                <div class="card-body">
                    <!-- Category Filter -->
                    <div class="category-filter mb-4">
                        <h4 class="mb-3">Filter by Category</h4>
                        <nav>
                            <div class="nav nav-pills">
                                <a class="nav-link <?php echo empty($selected_category) ? 'active' : ''; ?>" href="all-listings.php">All</a>
                                <?php foreach ($categories as $category): ?>
                                    <a class="nav-link <?php echo $selected_category === $category ? 'active' : ''; ?>" href="all-listings.php?category=<?php echo urlencode($category); ?>">
                                        <?php echo htmlspecialchars($category); ?>
                                        <?php if (isset($category_counts[$category])): ?>
                                            <span class="badge rounded-pill"><?php echo $category_counts[$category]; ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </nav>
                    </div>
                    
                    <!-- Listings Grid -->
                    <div id="ebay-listings">
                        <?php if (empty($listings)): ?>
                        <div class="listings-empty-state">
                            <i class="fas fa-search"></i>
                            <h4>No listings found</h4>
                            <p>We couldn't find any listings matching your criteria.</p>
                            <a href="all-listings.php" class="btn btn-outline-primary">View all listings</a>
                        </div>
                        <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
                            <?php foreach ($listings as $listing): ?>
                                <div class="col">
                                    <div class="card h-100 listing-card">
                                        <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" target="_blank" class="card-link">
                                            <div class="card-img-container">
                                                <?php if (!empty($listing['image_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                                                <?php else: ?>
                                                    <div class="text-center text-muted pt-4">
                                                        <i class="fas fa-image fa-2x"></i>
                                                        <p class="mt-1 small">No image</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                        <div class="card-body">
                                            <h5 class="card-title mb-3">
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
                        
                        <!-- Simple Pagination System -->
                        <?php 
                        // Make sure we have integer values
                        $current_page = intval($current_page);
                        $total_pages = intval($total_listings / $items_per_page);
                        if ($total_listings % $items_per_page > 0) $total_pages++;
                        
                        if ($total_pages > 1): 
                        ?>
                        <div class="pagination-container mt-4">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <!-- Previous button -->
                                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="all-listings.php?<?php echo ($current_page > 1) ? 'page='.($current_page-1) : 'page=1'; echo (!empty($selected_category)) ? '&category='.urlencode($selected_category) : ''; ?>">
                                            &laquo; Previous
                                        </a>
                                    </li>
                                    
                                    <!-- Page numbers -->
                                    <?php
                                    // Show max 5 page numbers with current page in the middle when possible
                                    $start_page = max(1, min($current_page - 2, $total_pages - 4));
                                    $end_page = min($total_pages, $start_page + 4);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item ' . ($current_page == $i ? 'active' : '') . '">'
                                           . '<a class="page-link" href="all-listings.php?page=' . $i . (!empty($selected_category) ? '&category=' . urlencode($selected_category) : '') . '">' . $i . '</a>'
                                           . '</li>';
                                    }
                                    ?>
                                    
                                    <!-- Next button -->
                                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="all-listings.php?<?php echo ($current_page < $total_pages) ? 'page='.($current_page+1) : 'page='.$total_pages; echo (!empty($selected_category)) ? '&category='.urlencode($selected_category) : ''; ?>">
                                            Next &raquo;
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                            
                            <!-- Page counter -->
                            <div class="text-center mt-2">
                                <small class="text-muted">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
