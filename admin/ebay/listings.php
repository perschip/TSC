<?php
// admin/ebay/listings.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_featured':
                $listing_id = (int)$_POST['listing_id'];
                try {
                    $query = "UPDATE ebay_listings SET is_featured = NOT is_featured WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':id' => $listing_id]);
                    $_SESSION['success_message'] = 'Featured status updated!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error updating featured status: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_active':
                $listing_id = (int)$_POST['listing_id'];
                try {
                    $query = "UPDATE ebay_listings SET is_active = NOT is_active WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':id' => $listing_id]);
                    $_SESSION['success_message'] = 'Listing status updated!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error updating listing status: ' . $e->getMessage();
                }
                break;
                
            case 'bulk_featured':
                $listing_ids = $_POST['listing_ids'] ?? [];
                $featured = isset($_POST['set_featured']) ? 1 : 0;
                
                if (!empty($listing_ids)) {
                    try {
                        $placeholders = str_repeat('?,', count($listing_ids) - 1) . '?';
                        $query = "UPDATE ebay_listings SET is_featured = ? WHERE id IN ($placeholders)";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute(array_merge([$featured], $listing_ids));
                        $_SESSION['success_message'] = 'Updated ' . count($listing_ids) . ' listings!';
                    } catch (PDOException $e) {
                        $_SESSION['error_message'] = 'Error updating listings: ' . $e->getMessage();
                    }
                }
                break;
        }
        
        header('Location: listings.php' . (isset($_GET['page']) ? '?page=' . $_GET['page'] : ''));
        exit;
    }
}

// Pagination and filtering
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($current_page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'last_updated';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE :search OR item_id LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category_name = :category";
    $params[':category'] = $category_filter;
}

if ($status_filter === 'active') {
    $where_conditions[] = "end_time > NOW() AND is_active = 1";
} elseif ($status_filter === 'ended') {
    $where_conditions[] = "end_time <= NOW()";
} elseif ($status_filter === 'featured') {
    $where_conditions[] = "is_featured = 1";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valid sort columns
$valid_sorts = ['title', 'current_price', 'watch_count', 'end_time', 'last_updated'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'last_updated';
}

// Get total count
try {
    $count_query = "SELECT COUNT(*) FROM ebay_listings $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_listings = $count_stmt->fetchColumn();
} catch (PDOException $e) {
    $total_listings = 0;
}

$total_pages = ceil($total_listings / $per_page);

// Get listings
try {
    $query = "SELECT * FROM ebay_listings $where_clause ORDER BY $sort $order LIMIT :offset, :per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $listings = $stmt->fetchAll();
} catch (PDOException $e) {
    $listings = [];
    $_SESSION['error_message'] = 'Error fetching listings: ' . $e->getMessage();
}

// Get categories for filter
try {
    $categories_query = "SELECT DISTINCT category_name FROM ebay_listings WHERE category_name IS NOT NULL ORDER BY category_name";
    $categories_stmt = $pdo->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Page variables
$page_title = 'eBay Listings';

$header_actions = '
<a href="settings.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-cog me-1"></i> Settings
</a>
<form method="post" action="settings.php" class="d-inline">
    <input type="hidden" name="action" value="sync_listings">
    <button type="submit" class="btn btn-sm btn-success">
        <i class="fas fa-sync me-1"></i> Sync Now
    </button>
</form>
';

$extra_scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById("select-all");
    const listingCheckboxes = document.querySelectorAll("input[name=\"listing_ids[]\"]");
    const bulkActionDiv = document.getElementById("bulk-actions");
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("change", function() {
            listingCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            toggleBulkActions();
        });
    }
    
    listingCheckboxes.forEach(checkbox => {
        checkbox.addEventListener("change", toggleBulkActions);
    });
    
    function toggleBulkActions() {
        const checkedBoxes = document.querySelectorAll("input[name=\"listing_ids[]\"]:checked");
        if (checkedBoxes.length > 0) {
            bulkActionDiv.style.display = "block";
            document.getElementById("selected-count").textContent = checkedBoxes.length;
        } else {
            bulkActionDiv.style.display = "none";
        }
    }
    
    // Confirm bulk actions
    document.getElementById("bulk-featured-btn")?.addEventListener("click", function() {
        const checkedBoxes = document.querySelectorAll("input[name=\"listing_ids[]\"]:checked");
        if (checkedBoxes.length === 0) {
            alert("Please select listings first.");
            return false;
        }
        return confirm(`Set ${checkedBoxes.length} listings as featured?`);
    });
    
    document.getElementById("bulk-unfeatured-btn")?.addEventListener("click", function() {
        const checkedBoxes = document.querySelectorAll("input[name=\"listing_ids[]\"]:checked");
        if (checkedBoxes.length === 0) {
            alert("Please select listings first.");
            return false;
        }
        return confirm(`Remove ${checkedBoxes.length} listings from featured?`);
    });
});
</script>';

// Include admin header
include_once '../includes/header.php';
?>

<!-- Filters and Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search listings..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>Ended</option>
                    <option value="featured" <?php echo $status_filter === 'featured' ? 'selected' : ''; ?>>Featured</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="sort">
                    <option value="last_updated" <?php echo $sort === 'last_updated' ? 'selected' : ''; ?>>Last Updated</option>
                    <option value="current_price" <?php echo $sort === 'current_price' ? 'selected' : ''; ?>>Price</option>
                    <option value="watch_count" <?php echo $sort === 'watch_count' ? 'selected' : ''; ?>>Watchers</option>
                    <option value="end_time" <?php echo $sort === 'end_time' ? 'selected' : ''; ?>>End Time</option>
                    <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="btn-group w-100">
                    <button type="submit" name="order" value="desc" class="btn btn-outline-secondary <?php echo $order === 'DESC' ? 'active' : ''; ?>">
                        <i class="fas fa-sort-amount-down"></i>
                    </button>
                    <button type="submit" name="order" value="asc" class="btn btn-outline-secondary <?php echo $order === 'ASC' ? 'active' : ''; ?>">
                        <i class="fas fa-sort-amount-up"></i>
                    </button>
                </div>
            </div>
        </form>
        
        <?php if (!empty($search) || !empty($category_filter) || !empty($status_filter)): ?>
            <div class="mt-3">
                <a href="listings.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk Actions (Hidden by default) -->
<div id="bulk-actions" class="card mb-4" style="display: none;">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="bulk_featured">
            <div class="d-flex align-items-center justify-content-between">
                <span><span id="selected-count">0</span> listings selected</span>
                <div class="btn-group">
                    <button type="submit" name="set_featured" value="1" id="bulk-featured-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-star me-1"></i> Set Featured
                    </button>
                    <button type="submit" name="set_featured" value="0" id="bulk-unfeatured-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="far fa-star me-1"></i> Remove Featured
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Listings Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">eBay Listings (<?php echo number_format($total_listings); ?>)</h6>
        <div class="small text-muted">
            Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($listings)): ?>
            <div class="text-center py-5">
                <div class="text-muted mb-3"><i class="fas fa-box-open fa-3x"></i></div>
                <?php if (!empty($search) || !empty($category_filter) || !empty($status_filter)): ?>
                    <p>No listings found matching your criteria. <a href="listings.php">View all listings</a></p>
                <?php else: ?>
                    <p>No eBay listings found. <a href="settings.php">Configure your eBay integration</a> and sync your listings.</p>
                    <a href="settings.php" class="btn btn-primary">
                        <i class="fas fa-cog me-1"></i> Go to Settings
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="select-all" class="form-check-input">
                            </th>
                            <th style="width: 80px;"></th>
                            <th>Title</th>
                            <th style="width: 100px;">Price</th>
                            <th style="width: 80px;">Watchers</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 140px;">Ends</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $listing): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="listing_ids[]" value="<?php echo $listing['id']; ?>" class="form-check-input">
                                </td>
                                <td>
                                    <?php if (!empty($listing['gallery_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($listing['gallery_url']); ?>" 
                                             alt="Item" class="listing-image">
                                    <?php else: ?>
                                        <div class="listing-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($listing['title']); ?></div>
                                    <div class="small text-muted">
                                        ID: <?php echo htmlspecialchars($listing['item_id']); ?>
                                        <?php if (!empty($listing['condition_name'])): ?>
                                            • <?php echo htmlspecialchars($listing['condition_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($listing['category_name'])): ?>
                                        <div class="small">
                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($listing['category_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-success">$<?php echo number_format($listing['current_price'], 2); ?></div>
                                    <?php if ($listing['shipping_cost'] > 0): ?>
                                        <div class="small text-muted">+$<?php echo number_format($listing['shipping_cost'], 2); ?> ship</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($listing['watch_count'] > 0): ?>
                                        <span class="badge bg-info"><?php echo $listing['watch_count']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <?php if ($listing['is_featured']): ?>
                                            <span class="badge bg-warning"><i class="fas fa-star"></i> Featured</span>
                                        <?php endif; ?>
                                        
                                        <?php if (!$listing['is_active']): ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php elseif ($listing['end_time'] && strtotime($listing['end_time']) <= time()): ?>
                                            <span class="badge bg-danger">Ended</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($listing['end_time']): ?>
                                        <?php 
                                        $end_time = strtotime($listing['end_time']);
                                        $now = time();
                                        if ($end_time > $now): 
                                            $diff = $end_time - $now;
                                            if ($diff < 86400): // Less than 24 hours
                                        ?>
                                                <div class="text-danger fw-bold"><?php echo date('g:i A', $end_time); ?></div>
                                                <div class="small text-danger">Today</div>
                                            <?php else: ?>
                                                <div><?php echo date('M j', $end_time); ?></div>
                                                <div class="small text-muted"><?php echo date('g:i A', $end_time); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="text-danger">Ended</div>
                                            <div class="small text-muted"><?php echo date('M j', $end_time); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?php echo htmlspecialchars($listing['view_item_url']); ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary" title="View on eBay">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_featured">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" 
                                                    title="<?php echo $listing['is_featured'] ? 'Remove from Featured' : 'Add to Featured'; ?>">
                                                <i class="<?php echo $listing['is_featured'] ? 'fas' : 'far'; ?> fa-star"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" 
                                                    title="<?php echo $listing['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $listing['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Listings pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode(strtolower($order)); ?>">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode(strtolower($order)); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode(strtolower($order)); ?>">
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

<style>
.listing-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
}
</style>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>