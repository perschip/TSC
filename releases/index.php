<?php
// Product Release Calendar - Frontend View
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get the current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}
if ($year < 2020 || $year > 2030) {
    $year = (int)date('Y');
}

// Get the first day of the month
$first_day = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('N', $first_day); // 1 (Monday) to 7 (Sunday)
$days_in_month = date('t', $first_day);
$month_name = date('F', $first_day);

// Calculate previous and next month
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get releases for this month
try {
    $query = "SELECT * FROM product_releases 
              WHERE YEAR(release_date) = :year 
              AND MONTH(release_date) = :month 
              ORDER BY release_date, title";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':year' => $year,
        ':month' => $month
    ]);
    $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group releases by day
    $releases_by_day = [];
    foreach ($releases as $release) {
        $day = (int)date('j', strtotime($release['release_date']));
        if (!isset($releases_by_day[$day])) {
            $releases_by_day[$day] = [];
        }
        $releases_by_day[$day][] = $release;
    }
} catch (PDOException $e) {
    $releases = [];
    $releases_by_day = [];
    error_log('Error fetching releases: ' . $e->getMessage());
}

// Get featured releases (upcoming only)
try {
    $query = "SELECT * FROM product_releases 
              WHERE is_featured = 1 
              AND release_date >= CURDATE() 
              ORDER BY release_date 
              LIMIT 5";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $featured_releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $featured_releases = [];
    error_log('Error fetching featured releases: ' . $e->getMessage());
}

// Get categories
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

// Include header
$page_title = "Product Release Calendar - " . $month_name . " " . $year;
include_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Release Calendar</li>
                </ol>
            </nav>
            <h1 class="display-5 mb-3">Product Release Calendar</h1>
            <p class="lead">Stay up to date with our upcoming product releases. Never miss a drop!</p>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="btn-group">
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" class="btn btn-outline-secondary">
                    Today
                </a>
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-outline-primary">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-9">
            <!-- Calendar -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h3 class="mb-0 text-center"><?php echo $month_name . ' ' . $year; ?></h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered calendar-table mb-0">
                            <thead>
                                <tr>
                                    <th>Mon</th>
                                    <th>Tue</th>
                                    <th>Wed</th>
                                    <th>Thu</th>
                                    <th>Fri</th>
                                    <th>Sat</th>
                                    <th>Sun</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Calculate the number of blank cells before the first day
                                $blank_cells = $first_day_of_week - 1;
                                if ($blank_cells < 0) $blank_cells = 6; // Adjust for Sunday
                                
                                // Calculate total cells (days + blank cells)
                                $total_cells = $blank_cells + $days_in_month;
                                $total_rows = ceil($total_cells / 7);
                                
                                // Current day
                                $current_day = 1;
                                
                                // Loop through rows
                                for ($row = 1; $row <= $total_rows; $row++) {
                                    echo '<tr>';
                                    
                                    // Loop through columns
                                    for ($col = 1; $col <= 7; $col++) {
                                        // Calculate the cell index
                                        $cell_index = ($row - 1) * 7 + $col;
                                        
                                        if ($cell_index <= $blank_cells || $current_day > $days_in_month) {
                                            // Empty cell
                                            echo '<td class="empty-day"></td>';
                                        } else {
                                            // Day cell
                                            $is_today = ($current_day == date('j') && $month == date('m') && $year == date('Y'));
                                            $has_releases = isset($releases_by_day[$current_day]);
                                            
                                            $cell_class = $is_today ? 'today' : '';
                                            $cell_class .= $has_releases ? ' has-releases' : '';
                                            
                                            echo '<td class="' . $cell_class . '">';
                                            echo '<div class="day-number">' . $current_day . '</div>';
                                            
                                            if ($has_releases) {
                                                echo '<div class="releases-container">';
                                                foreach ($releases_by_day[$current_day] as $release) {
                                                    $release_class = $release['is_featured'] ? 'featured' : '';
                                                    echo '<div class="release-item ' . $release_class . '">';
                                                    echo '<a href="view.php?id=' . $release['id'] . '" class="release-link">';
                                                    echo htmlspecialchars($release['title']);
                                                    echo '</a>';
                                                    if (!empty($release['category'])) {
                                                        echo '<span class="release-category">' . htmlspecialchars($release['category']) . '</span>';
                                                    }
                                                    echo '</div>';
                                                }
                                                echo '</div>';
                                            }
                                            
                                            echo '</td>';
                                            
                                            $current_day++;
                                        }
                                    }
                                    
                                    echo '</tr>';
                                    
                                    // Break if we've displayed all days
                                    if ($current_day > $days_in_month) {
                                        break;
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- List View for Mobile -->
            <div class="card shadow-sm d-md-none mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Releases This Month</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($releases)): ?>
                        <p class="text-center mb-0">No releases scheduled for this month.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($releases as $release): ?>
                                <?php
                                $release_date = new DateTime($release['release_date']);
                                $today = new DateTime();
                                $is_upcoming = $release_date >= $today;
                                ?>
                                <a href="view.php?id=<?php echo $release['id']; ?>" class="list-group-item list-group-item-action <?php echo $release['is_featured'] ? 'list-group-item-primary' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($release['title']); ?></h5>
                                        <small><?php echo $release_date->format('M j'); ?></small>
                                    </div>
                                    <?php if (!empty($release['category'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($release['category']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($is_upcoming): ?>
                                        <span class="badge bg-success">Upcoming</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3">
            <!-- Featured Releases -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">Featured Releases</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($featured_releases)): ?>
                        <p class="text-center mb-0">No featured releases at this time.</p>
                    <?php else: ?>
                        <?php foreach ($featured_releases as $release): ?>
                            <div class="featured-release-card mb-3">
                                <?php if (!empty($release['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($release['image_url']); ?>" alt="<?php echo htmlspecialchars($release['title']); ?>" class="featured-release-image mb-2">
                                <?php endif; ?>
                                <h5 class="featured-release-title">
                                    <a href="view.php?id=<?php echo $release['id']; ?>"><?php echo htmlspecialchars($release['title']); ?></a>
                                </h5>
                                <div class="featured-release-date mb-1">
                                    <i class="far fa-calendar-alt me-1"></i> <?php echo date('F j, Y', strtotime($release['release_date'])); ?>
                                </div>
                                <?php if (!empty($release['category'])): ?>
                                    <div class="featured-release-category mb-2">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($release['category']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($release['description'])): ?>
                                    <p class="featured-release-description small">
                                        <?php echo substr(htmlspecialchars($release['description']), 0, 100); ?>
                                        <?php if (strlen($release['description']) > 100): ?>...<?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($release['product_url'])): ?>
                                <a href="<?php echo htmlspecialchars($release['product_url']); ?>" class="btn btn-sm btn-primary w-100 mb-3" target="_blank">
                                    Pre-order Now
                                </a>
                            <?php endif; ?>
                            <hr class="my-3">
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Categories -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">Categories</h4>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($categories as $category): ?>
                            <a href="category.php?name=<?php echo urlencode($category); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($category); ?>
                                <?php
                                // Count releases in this category
                                try {
                                    $count_query = "SELECT COUNT(*) FROM product_releases WHERE category = :category";
                                    $count_stmt = $pdo->prepare($count_query);
                                    $count_stmt->execute([':category' => $category]);
                                    $count = $count_stmt->fetchColumn();
                                    
                                    echo '<span class="badge bg-primary rounded-pill">' . $count . '</span>';
                                } catch (PDOException $e) {
                                    // Silently fail
                                }
                                ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Calendar Styles */
.calendar-table {
    table-layout: fixed;
}

.calendar-table th {
    text-align: center;
    background-color: #f8f9fa;
    font-weight: 500;
}

.calendar-table td {
    height: 120px;
    vertical-align: top;
    padding: 5px;
    position: relative;
}

.empty-day {
    background-color: #f8f9fa;
}

.day-number {
    font-weight: bold;
    margin-bottom: 5px;
    text-align: right;
}

.today {
    background-color: rgba(0, 123, 255, 0.1);
}

.today .day-number {
    color: #007bff;
}

.has-releases {
    background-color: rgba(255, 255, 255, 0.9);
}

.releases-container {
    overflow-y: auto;
    max-height: 85px;
}

.release-item {
    margin-bottom: 5px;
    padding: 3px 5px;
    border-radius: 3px;
    background-color: #f8f9fa;
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.release-item.featured {
    background-color: #fff3cd;
    border-left: 3px solid #ffc107;
}

.release-link {
    color: #212529;
    text-decoration: none;
    display: block;
}

.release-link:hover {
    text-decoration: underline;
}

.release-category {
    display: inline-block;
    font-size: 0.7rem;
    background-color: #6c757d;
    color: white;
    padding: 1px 4px;
    border-radius: 3px;
    margin-left: 5px;
}

/* Featured Releases */
.featured-release-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 4px;
}

.featured-release-title {
    font-size: 1.1rem;
    margin-bottom: 5px;
}

.featured-release-title a {
    color: #212529;
    text-decoration: none;
}

.featured-release-title a:hover {
    text-decoration: underline;
}

.featured-release-date {
    font-size: 0.85rem;
    color: #6c757d;
}

.featured-release-category {
    font-size: 0.85rem;
}

.featured-release-description {
    color: #6c757d;
    margin-bottom: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .calendar-table td {
        height: 80px;
    }
    
    .releases-container {
        max-height: 50px;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
