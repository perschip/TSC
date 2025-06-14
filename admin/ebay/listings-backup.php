<?php
// admin/ebay/listings.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if we have a valid eBay connection
$ebay_connected = !empty(getSetting('ebay_access_token')) && 
                 !empty(getSetting('ebay_refresh_token')) && 
                 getSetting('ebay_token_expires_at', 0) > time();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_listing':
                $listing_id = (int)$_POST['listing_id'];
                try {
                    $query = "DELETE FROM ebay_listings WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':id' => $listing_id]);
                    $_SESSION['success_message'] = 'Listing deleted successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error deleting listing: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_favorite':
                $listing_id = (int)$_POST['listing_id'];
                try {
                    // Get current favorite status
                    $query = "SELECT is_favorite FROM ebay_listings WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':id' => $listing_id]);
                    $current = $stmt->fetchColumn();
                    
                    // Toggle the status
                    $new_status = $current ? 0 : 1;
                    
                    $query = "UPDATE ebay_listings SET is_favorite = :status WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([
                        ':status' => $new_status,
                        ':id' => $listing_id
                    ]);
                    
                    $_SESSION['success_message'] = $new_status ? 'Listing added to favorites!' : 'Listing removed from favorites!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error updating favorite status: ' . $e->getMessage();
                }
                break;
                
            case 'update_category':
                $listing_id = (int)$_POST['listing_id'];
                $category = trim($_POST['category']);
                
                try {
                    $query = "UPDATE ebay_listings SET category = :category WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([
                        ':category' => $category,
                        ':id' => $listing_id
                    ]);
                    
                    $_SESSION['success_message'] = 'Category updated successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error updating category: ' . $e->getMessage();
                }
                break;
                
            case 'bulk_delete':
                if (isset($_POST['listing_ids']) && is_array($_POST['listing_ids'])) {
                    $listing_ids = $_POST['listing_ids'];
                    
                    // Debug information
                    error_log('Bulk delete requested. Listing IDs: ' . print_r($listing_ids, true));
                    
                    if (!empty($listing_ids)) {
                        try {
                            // Begin transaction
                            $pdo->beginTransaction();
                            
                            // Create a simple SQL query with IN clause
                            $ids_string = implode(',', array_map('intval', $listing_ids));
                            $query = "DELETE FROM ebay_listings WHERE id IN ($ids_string)";
                            error_log("Executing query: $query");
                            
                            // Execute the query directly
                            $deleted_count = $pdo->exec($query);
                            
                            // Commit transaction
                            $pdo->commit();
                            
                            if ($deleted_count > 0) {
                                $_SESSION['success_message'] = "Successfully deleted $deleted_count listings!";
                                error_log("Bulk delete successful. Deleted $deleted_count listings.");
                            } else {
                                $_SESSION['error_message'] = 'No listings were deleted.';
                                error_log('Bulk delete completed but no listings were deleted.');
                            }
                        } catch (PDOException $e) {
                            // Rollback transaction on error
                            $pdo->rollBack();
                            $_SESSION['error_message'] = 'Error deleting listings: ' . $e->getMessage();
                            error_log('PDO Exception in bulk delete: ' . $e->getMessage());
                        }
                    } else {
                        $_SESSION['error_message'] = 'No listings selected for deletion.';
                        error_log('Bulk delete requested but empty listing IDs array.');
                    }
                } else {
                    $_SESSION['error_message'] = 'No listings selected for deletion.';
                    error_log('Bulk delete requested but no listing_ids parameter.');
                }
                break;
                
            case 'bulk_update_category':
                if (isset($_POST['listing_ids']) && is_array($_POST['listing_ids']) && isset($_POST['category'])) {
                    $listing_ids = $_POST['listing_ids'];
                    $category = trim($_POST['category']);
                    
                    // Debug information
                    error_log('Bulk category update requested. Listing IDs: ' . print_r($listing_ids, true));
                    error_log('New category: ' . $category);
                    
                    if (!empty($listing_ids)) {
                        try {
                            // Begin transaction
                            $pdo->beginTransaction();
                            
                            // Create a simple SQL query with IN clause
                            $ids_string = implode(',', array_map('intval', $listing_ids));
                            $query = "UPDATE ebay_listings SET category = :category WHERE id IN ($ids_string)";
                            $stmt = $pdo->prepare($query);
                            $stmt->bindParam(':category', $category);
                            $stmt->execute();
                            
                            // Get number of affected rows
                            $updated_count = $stmt->rowCount();
                            
                            // Commit transaction
                            $pdo->commit();
                            
                            if ($updated_count > 0) {
                                $_SESSION['success_message'] = "Successfully updated category for $updated_count listings!";
                                error_log("Bulk category update successful. Updated $updated_count listings.");
                            } else {
                                $_SESSION['error_message'] = 'No listings were updated.';
                                error_log('Bulk category update completed but no listings were updated.');
                            }
                        } catch (PDOException $e) {
                            // Rollback transaction on error
                            $pdo->rollBack();
                            $_SESSION['error_message'] = 'Error updating listings: ' . $e->getMessage();
                            error_log('PDO Exception in bulk category update: ' . $e->getMessage());
                        }
                    } else {
                        $_SESSION['error_message'] = 'No listings selected for update.';
                        error_log('Bulk category update requested but empty listing IDs array.');
                    }
                } else {
                    $_SESSION['error_message'] = 'Missing required parameters for bulk category update.';
                    error_log('Bulk category update requested but missing parameters.');
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
$favorites_filter = isset($_GET['favorites']) && $_GET['favorites'] === '1';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'last_updated';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE :search OR sku LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = :category";
    $params[':category'] = $category_filter;
}

if ($favorites_filter) {
    $where_conditions[] = "is_favorite = 1";
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

// Get categories from the ebay_categories table
try {
    $stmt = $pdo->query("SELECT name FROM ebay_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
    error_log('Error fetching categories: ' . $e->getMessage());
}

// If no categories in the table, fall back to distinct values from listings
if (empty($categories)) {
    $stmt = $pdo->query("SELECT DISTINCT category FROM ebay_listings WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Page variables
$page_title = 'eBay Listings';

$header_actions = '
<a href="categories.php" class="btn btn-sm btn-outline-primary">
    <i class="fas fa-tags me-1"></i> Manage Categories
</a>
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
function deleteSelected() {
    // Get all checked checkboxes
    const checkedBoxes = document.querySelectorAll("input[name=\'listing_ids[]\']:checked");
    
    if (checkedBoxes.length === 0) {
        alert("Please select at least one listing to delete.");
        return;
    }
    
    if (!confirm("Are you sure you want to delete " + checkedBoxes.length + " listings? This action cannot be undone.")) {
        return;
    }
    
    // Clear previous inputs
    document.getElementById("selected-ids-container").innerHTML = "";
    
    // Create hidden inputs for each selected ID
    checkedBoxes.forEach(function(checkbox) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "listing_ids[]";
        input.value = checkbox.value;
        document.getElementById("selected-ids-container").appendChild(input);
    });
    
    // Submit the form
    document.getElementById("bulk-delete-form").submit();
}

function updateSelectedCategory() {
    // Get all checked checkboxes
    const checkedBoxes = document.querySelectorAll("input[name=\'listing_ids[]\']:checked");
    
    if (checkedBoxes.length === 0) {
        alert("Please select at least one listing to update.");
        return;
    }
    
    // Get the category value
    const categoryValue = document.getElementById("bulk-category").value.trim();
    
    if (!categoryValue) {
        alert("Please enter a category name.");
        return;
    }
    
    if (!confirm("Are you sure you want to update the category for " + checkedBoxes.length + " listings to \'" + categoryValue + "\' ?")) {
        return;
    }
    
    // Set the category value
    document.getElementById("bulk-category-value").value = categoryValue;
    
    // Clear previous inputs
    document.getElementById("selected-category-ids-container").innerHTML = "";
    
    // Create hidden inputs for each selected ID
    checkedBoxes.forEach(function(checkbox) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "listing_ids[]";
        input.value = checkbox.value;
        document.getElementById("selected-category-ids-container").appendChild(input);
    });
    
    // Submit the form
    document.getElementById("bulk-category-form").submit();
}

document.addEventListener("DOMContentLoaded", function() {
    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById("select-all");
    const listingCheckboxes = document.querySelectorAll("input[name=\'listing_ids[]\']");
    const bulkActionDiv = document.getElementById("bulk-actions");
    
    // Initialize
    updateSelectedCount();
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("change", function() {
            listingCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    listingCheckboxes.forEach(checkbox => {
        checkbox.addEventListener("change", updateSelectedCount);
    });
    
    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll("input[name=\'listing_ids[]\']:checked");
        
        if (checkedBoxes.length > 0) {
            bulkActionDiv.style.display = "block";
            document.getElementById("selected-count").textContent = checkedBoxes.length;
        } else {
            bulkActionDiv.style.display = "none";
        }
    }
});
</script>';

// Include admin header
include_once '../includes/header.php';
?>

<!-- Filter Form -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Filter Listings</h6>
        <button class="btn btn-sm btn-link" type="button" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="true" aria-controls="filterCollapse">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
    <div class="collapse show" id="filterCollapse">
        <div class="card-body">
            <form method="get" class="mb-0">
                <div class="row mb-3">
                    <div class="col-md-4 mb-3">
                        <div class="form-group mb-0">
                            <label for="search" class="mb-2">Search</label>
                            <div class="input-group">
                                <span class="input-group-text" style="height: 38px;"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title or eBay ID">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="form-group mb-0">
                            <label for="category" class="mb-2">Category</label>
                            <select class="form-control" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="form-group mb-0">
                            <label for="status" class="mb-2">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>Ended</option>
                                <option value="favorite" <?php echo $status_filter === 'favorite' ? 'selected' : ''; ?>>Favorites</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Apply Filters
                        </button>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 mb-3">
                        <div class="form-group mb-0">
                            <label for="price_min" class="mb-2">Min Price</label>
                            <div class="input-group">
                                <span class="input-group-text" style="height: 38px;">$</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="price_min" name="price_min" value="<?php echo isset($_GET['price_min']) ? htmlspecialchars($_GET['price_min']) : ''; ?>" placeholder="Min">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="form-group mb-0">
                            <label for="price_max" class="mb-2">Max Price</label>
                            <div class="input-group">
                                <span class="input-group-text" style="height: 38px;">$</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="price_max" name="price_max" value="<?php echo isset($_GET['price_max']) ? htmlspecialchars($_GET['price_max']) : ''; ?>" placeholder="Max">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="form-group mb-0">
                            <label for="sort" class="mb-2">Sort By</label>
                            <select class="form-control" id="sort" name="sort">
                                <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title</option>
                                <option value="price" <?php echo $sort === 'price' ? 'selected' : ''; ?>>Price</option>
                                <option value="date_added" <?php echo $sort === 'date_added' ? 'selected' : ''; ?>>Date Added</option>
                                <option value="ebay_id" <?php echo $sort === 'ebay_id' ? 'selected' : ''; ?>>eBay ID</option>
                            </select>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="order" name="order" value="desc" <?php echo strtolower($order) === 'desc' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="order">Descending order</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12 text-end">
                        <a href="listings.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-undo me-1"></i> Reset Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Actions (Hidden by default) -->
<div id="bulk-actions" class="card mb-4" style="display: none;">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
            <span><span id="selected-count">0</span> listings selected</span>
            <div class="d-flex gap-2">
                <!-- Bulk Category Update -->
                <div class="input-group">
                    <select id="bulk-category" class="form-select form-select-sm">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" onclick="updateSelectedCategory()">
                        <i class="fas fa-tags me-1"></i> Update Category
                    </button>
                </div>
                
                <!-- Bulk Delete Button -->
                <button type="button" id="bulk-delete-btn" class="btn btn-sm btn-danger" onclick="deleteSelected()">
                    <i class="fas fa-trash me-1"></i> Delete Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for bulk delete -->
<form id="bulk-delete-form" method="post" style="display:none;">
    <input type="hidden" name="action" value="bulk_delete">
    <div id="selected-ids-container"></div>
</form>

<!-- Hidden form for bulk category update -->
<form id="bulk-category-form" method="post" style="display:none;">
    <input type="hidden" name="action" value="bulk_update_category">
    <input type="hidden" name="category" id="bulk-category-value">
    <div id="selected-category-ids-container"></div>
</form>

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
                            <th style="width: 80px;">Quantity</th>
                            <th style="width: 120px;">Category</th>
                            <th style="width: 120px;">Last Updated</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $listing): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="listing_ids[]" value="<?php echo $listing['id']; ?>" class="form-check-input">
                                </td>
                                <td>
                                    <?php if (!empty($listing['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" 
                                             alt="Item" class="listing-image">
                                    <?php else: ?>
                                        <div class="listing-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        <a href="https://www.ebay.com/itm/<?php echo str_replace('EBAY-', '', $listing['sku']); ?>" 
                                           class="ebay-listing-link text-primary" 
                                           data-id="<?php echo $listing['id']; ?>" 
                                           target="_blank">
                                            <?php echo htmlspecialchars($listing['title']); ?>
                                        </a>
                                    </div>
                                    <div class="small text-muted">
                                        SKU: <?php echo htmlspecialchars($listing['sku'] ?? 'N/A'); ?>
                                        <?php if ($listing['is_favorite']): ?>
                                            <span class="ms-2 text-warning"><i class="fas fa-star"></i> Favorite</span>
                                        <?php endif; ?>
                                        <?php if (isset($listing['click_count']) && $listing['click_count'] > 0): ?>
                                            <span class="ms-2 text-info"><i class="fas fa-mouse-pointer"></i> <?php echo $listing['click_count']; ?> clicks</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-success"><?php echo $listing['currency'] ?? 'USD'; ?> <?php echo number_format($listing['price'] ?? 0, 2); ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $listing['quantity'] ?? 0; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <?php if ($listing['quantity'] > 0): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <form method="post" class="category-form">
                                        <input type="hidden" name="action" value="update_category">
                                        <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                        <div class="input-group input-group-sm">
                                            <select class="form-select form-select-sm" name="category">
                                                <option value="">-- Select --</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($listing['category'] ?? '') === $category ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if (!empty($listing['category']) && !in_array($listing['category'], $categories)): ?>
                                                    <option value="<?php echo htmlspecialchars($listing['category']); ?>" selected>
                                                        <?php echo htmlspecialchars($listing['category']); ?>
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    <?php if (!empty($listing['last_updated'])): ?>
                                        <?php 
                                        $update_time = strtotime($listing['last_updated']);
                                        $now = time();
                                        $diff = $now - $update_time;
                                        
                                        if ($diff < 3600): // Less than 1 hour
                                        ?>
                                            <div class="text-success fw-bold">Just updated</div>
                                            <div class="small text-muted"><?php echo date('g:i A', $update_time); ?></div>
                                        <?php elseif ($diff < 86400): // Less than 24 hours ?>
                                            <div><?php echo date('g:i A', $update_time); ?></div>
                                            <div class="small text-muted">Today</div>
                                        <?php else: ?>
                                            <div><?php echo date('M j', $update_time); ?></div>
                                            <div class="small text-muted"><?php echo date('g:i A', $update_time); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary" title="View on eBay">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_favorite">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?php echo $listing['is_favorite'] ? 'warning' : 'secondary'; ?>" 
                                                    title="<?php echo $listing['is_favorite'] ? 'Remove from Favorites' : 'Add to Favorites'; ?>">
                                                <i class="fas fa-star"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="delete_listing">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    title="Delete Listing" onclick="return confirm('Are you sure you want to delete this listing?');">
                                                <i class="fas fa-trash"></i>
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
</form><!-- End of bulk actions form -->

<style>
.listing-image {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 4px;
    transition: transform 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.listing-image:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05);
}

.badge-favorite {
    background-color: #ffc107;
    color: #212529;
}

.badge-active {
    background-color: #28a745;
    color: white;
}

.badge-ended {
    background-color: #dc3545;
    color: white;
}

.category-badge {
    background-color: #6c757d;
    color: white;
    font-size: 0.8rem;
    font-weight: normal;
}

.quick-edit-category {
    cursor: pointer;
}

.quick-edit-category:hover {
    text-decoration: underline;
    color: #007bff;
}

/* Loading indicator for AJAX operations */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    display: none;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<!-- Loading Overlay for AJAX Operations -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Quick Edit Category Modal -->
<div class="modal fade" id="quickEditModal" tabindex="-1" role="dialog" aria-labelledby="quickEditModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickEditModalLabel">Edit Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="quickEditForm">
                    <input type="hidden" id="quickEditListingId" name="listing_id">
                    <input type="hidden" name="action" value="update_category">
                    
                    <div class="form-group">
                        <label for="quickEditCategory">Category</label>
                        <input type="text" class="form-control" id="quickEditCategory" name="category" list="categoryOptions">
                        <datalist id="categoryOptions">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveQuickEdit">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
// Quick category edit functionality
$(document).ready(function() {
    // Handle quick edit category click
    $('.quick-edit-category').click(function() {
        const listingId = $(this).data('id');
        const currentCategory = $(this).data('category');
        
        $('#quickEditListingId').val(listingId);
        $('#quickEditCategory').val(currentCategory);
        $('#quickEditModal').modal('show');
    });
    
    // Handle save button click
    $('#saveQuickEdit').click(function() {
        const form = $('#quickEditForm');
        const listingId = $('#quickEditListingId').val();
        const category = $('#quickEditCategory').val();
        
        // Show loading overlay
        $('#loadingOverlay').show();
        
        // Submit form via AJAX
        $.ajax({
            url: 'listings.php',
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                // Update the category in the table without page refresh
                const categoryCell = $(`[data-id="${listingId}"]`);
                categoryCell.text(category);
                categoryCell.data('category', category);
                
                // Hide modal and loading overlay
                $('#quickEditModal').modal('hide');
                $('#loadingOverlay').hide();
                
                // Show success message
                toastr.success('Category updated successfully!');
            },
            error: function() {
                $('#loadingOverlay').hide();
                toastr.error('Error updating category. Please try again.');
            }
        });
    });
    
    // Toggle favorite via AJAX
    $('.toggle-favorite').click(function(e) {
        e.preventDefault();
        const btn = $(this);
        const form = btn.closest('form');
        
        $.ajax({
            url: 'listings.php',
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                // Toggle the star icon
                const icon = btn.find('i');
                if (icon.hasClass('far')) {
                    icon.removeClass('far').addClass('fas');
                    toastr.success('Added to favorites!');
                } else {
                    icon.removeClass('fas').addClass('far');
                    toastr.success('Removed from favorites!');
                }
            },
            error: function() {
                toastr.error('Error updating favorite status. Please try again.');
            }
        });
    });
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Initialize toastr notifications
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 3000
    };
});
</script>

<!-- Include eBay Listing Click Tracking Script -->
<script src="listing_clicks.js"></script>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>