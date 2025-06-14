<?php
// Product Release Calendar - Admin Index
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_release':
                $release_id = (int)$_POST['release_id'];
                try {
                    $query = "DELETE FROM product_releases WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':id' => $release_id]);
                    $_SESSION['success_message'] = 'Release deleted successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error deleting release: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_featured':
                $release_id = (int)$_POST['release_id'];
                try {
                    // Get current featured status
                    $query = "SELECT is_featured FROM product_releases WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':id' => $release_id]);
                    $current = $stmt->fetchColumn();
                    
                    // Toggle the status
                    $new_status = $current ? 0 : 1;
                    
                    $query = "UPDATE product_releases SET is_featured = :status WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([
                        ':status' => $new_status,
                        ':id' => $release_id
                    ]);
                    
                    $_SESSION['success_message'] = $new_status ? 'Release marked as featured!' : 'Release removed from featured!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error updating featured status: ' . $e->getMessage();
                }
                break;
        }
        
        header('Location: index.php' . (isset($_GET['page']) ? '?page=' . $_GET['page'] : ''));
        exit;
    }
}

// Pagination and filtering
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';
$featured_filter = isset($_GET['featured']) && $_GET['featured'] === '1';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'release_date';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE :search OR description LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = :category";
    $params[':category'] = $category_filter;
}

if ($featured_filter) {
    $where_conditions[] = "is_featured = 1";
}

// Date filtering
if ($date_filter === 'upcoming') {
    $where_conditions[] = "release_date >= CURDATE()";
} elseif ($date_filter === 'past') {
    $where_conditions[] = "release_date < CURDATE()";
} elseif ($date_filter === 'this_month') {
    $where_conditions[] = "MONTH(release_date) = MONTH(CURDATE()) AND YEAR(release_date) = YEAR(CURDATE())";
} elseif ($date_filter === 'next_month') {
    $where_conditions[] = "(MONTH(release_date) = MONTH(DATE_ADD(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(release_date) = YEAR(DATE_ADD(CURDATE(), INTERVAL 1 MONTH)))";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valid sort columns
$valid_sorts = ['title', 'release_date', 'category', 'created_at'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'release_date';
}

// Get total count
try {
    $count_query = "SELECT COUNT(*) FROM product_releases $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_releases = $count_stmt->fetchColumn();
} catch (PDOException $e) {
    $total_releases = 0;
}

$total_pages = ceil($total_releases / $per_page);

// Get releases
try {
    $query = "SELECT * FROM product_releases $where_clause ORDER BY $sort $order LIMIT :offset, :per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $releases = $stmt->fetchAll();
} catch (PDOException $e) {
    $releases = [];
    $_SESSION['error_message'] = 'Error fetching releases: ' . $e->getMessage();
}

// Get categories from the product_releases table
try {
    $categories = [];
    $cat_query = "SELECT DISTINCT category FROM product_releases WHERE category IS NOT NULL AND category != '' ORDER BY category";
    $cat_stmt = $pdo->query($cat_query);
    while ($row = $cat_stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row['category'];
    }
} catch (PDOException $e) {
    $categories = [];
}

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Product Release Calendar</h1>
        <a href="add.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Add New Release
        </a>
    </div>
    
    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Filter Releases</h6>
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
                                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title or description">
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
                                <label for="date_filter" class="mb-2">Date Range</label>
                                <select class="form-control" id="date_filter" name="date_filter">
                                    <option value="">All Dates</option>
                                    <option value="upcoming" <?php echo $date_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="past" <?php echo $date_filter === 'past' ? 'selected' : ''; ?>>Past</option>
                                    <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="next_month" <?php echo $date_filter === 'next_month' ? 'selected' : ''; ?>>Next Month</option>
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
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" <?php echo $featured_filter ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="featured">
                                    Featured releases only
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-group mb-0">
                                <label for="sort" class="mb-2">Sort By</label>
                                <select class="form-control" id="sort" name="sort">
                                    <option value="release_date" <?php echo $sort === 'release_date' ? 'selected' : ''; ?>>Release Date</option>
                                    <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title</option>
                                    <option value="category" <?php echo $sort === 'category' ? 'selected' : ''; ?>>Category</option>
                                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date Added</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="order" name="order" value="asc" <?php echo strtolower($order) === 'asc' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="order">
                                    Ascending order
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 text-end">
                            <a href="index.php" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-undo me-1"></i> Reset Filters
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Content Row -->
    <div class="row">
        <div class="col-12">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($releases)): ?>
                <div class="card shadow mb-4">
                    <div class="card-body text-center py-5">
                        <p class="mb-0">No product releases found. <a href="add.php">Add your first release</a>.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;"></th>
                                        <th>Title</th>
                                        <th style="width: 120px;">Release Date</th>
                                        <th style="width: 120px;">Category</th>
                                        <th style="width: 80px;">Featured</th>
                                        <th style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($releases as $release): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($release['image_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($release['image_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($release['title']); ?>" 
                                                         class="release-image">
                                                <?php else: ?>
                                                    <div class="release-image bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-calendar text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($release['title']); ?></div>
                                                <div class="small text-muted">
                                                    <?php echo substr(htmlspecialchars($release['description']), 0, 100); ?>
                                                    <?php if (strlen($release['description']) > 100): ?>...<?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $release_date = new DateTime($release['release_date']);
                                                $today = new DateTime();
                                                $is_upcoming = $release_date > $today;
                                                ?>
                                                <span class="badge <?php echo $is_upcoming ? 'bg-primary' : 'bg-secondary'; ?>">
                                                    <?php echo $release_date->format('M j, Y'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($release['category'])): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($release['category']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_featured">
                                                    <input type="hidden" name="release_id" value="<?php echo $release['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-link text-warning p-0">
                                                        <i class="<?php echo $release['is_featured'] ? 'fas' : 'far'; ?> fa-star fa-lg"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="edit.php?id=<?php echo $release['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_release">
                                                        <input type="hidden" name="release_id" value="<?php echo $release['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this release?');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <a href="../../releases/view.php?id=<?php echo $release['id']; ?>" class="btn btn-sm btn-outline-info" target="_blank">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Releases pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($current_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode(strtolower($order)); ?><?php echo $featured_filter ? '&featured=1' : ''; ?>">
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
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode(strtolower($order)); ?><?php echo $featured_filter ? '&featured=1' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($current_page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode(strtolower($order)); ?><?php echo $featured_filter ? '&featured=1' : ''; ?>">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.release-image {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 4px;
    transition: transform 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.release-image:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05);
}
</style>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
