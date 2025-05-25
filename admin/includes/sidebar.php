<!-- Sidebar -->
<div class="col-md-3 col-lg-2 d-md-block sidebar collapse p-0">
    <div class="d-flex flex-column p-3 h-100">
        <a href="/admin/index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <span class="fs-4">Tristate Cards</span>
        </a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="/admin/index.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            
            <!-- Content Management Dropdown -->
            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/blog/') !== false || strpos($_SERVER['REQUEST_URI'], '/admin/pages/') !== false || strpos($_SERVER['REQUEST_URI'], '/admin/navigation/') !== false) ? 'active' : ''; ?>" 
                   id="contentDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-edit me-2"></i>
                    Content
                </a>
                <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="contentDropdown">
                    <li><a class="dropdown-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/blog/') !== false && strpos($_SERVER['REQUEST_URI'], '/admin/blog/settings.php') === false) ? 'active' : ''; ?>" href="/admin/blog/list.php">
                        <i class="fas fa-blog me-2"></i> Blog Posts
                    </a></li>
                    <li><a class="dropdown-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/blog/settings.php') !== false ? 'active' : ''; ?>" href="/admin/blog/settings.php">
                        <i class="fas fa-cog me-2"></i> Blog Settings
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/pages/') !== false ? 'active' : ''; ?>" href="/admin/pages/list.php">
                        <i class="fas fa-file-alt me-2"></i> Pages
                    </a></li>
                    <li><a class="dropdown-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/navigation/') !== false ? 'active' : ''; ?>" href="/admin/navigation/manage.php">
                        <i class="fas fa-bars me-2"></i> Navigation
                    </a></li>
                </ul>
            </li>
            
            <!-- Business Tools Dropdown -->
            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/breaks/') !== false ? 'active' : ''; ?>" 
                   id="businessDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-calculator me-2"></i>
                    Business Tools
                </a>
                <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="businessDropdown">
                    <li><a class="dropdown-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/breaks/calculator.php') !== false) ? 'active' : ''; ?>" href="/admin/breaks/calculator.php">
                        <i class="fas fa-calculator me-2"></i> Break Calculator
                    </a></li>
                    <li><a class="dropdown-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/breaks/teams.php') !== false) ? 'active' : ''; ?>" href="/admin/breaks/teams.php">
                        <i class="fas fa-users me-2"></i> Team Popularity
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/admin/analytics/dashboard.php">
                        <i class="fas fa-chart-line me-2"></i> Analytics
                    </a></li>
                </ul>
            </li>
            
            <!-- Integrations Dropdown -->
            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/whatnot/') !== false ? 'active' : ''; ?>" 
                   id="integrationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-plug me-2"></i>
                    Integrations
                </a>
                <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="integrationsDropdown">
                    <li><a class="dropdown-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/whatnot/') !== false ? 'active' : ''; ?>" href="/admin/whatnot/settings.php">
                        <i class="fas fa-video me-2"></i> Whatnot
                    </a></li>
                    <li><a class="dropdown-item disabled" href="#" title="Coming Soon">
                        <i class="fab fa-ebay me-2"></i> eBay <small>(Soon)</small>
                    </a></li>
                    <li><a class="dropdown-item disabled" href="#" title="Coming Soon">
                        <i class="fab fa-paypal me-2"></i> PayPal <small>(Soon)</small>
                    </a></li>
                </ul>
            </li>
            
            <!-- Settings Dropdown -->
            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/settings/') !== false ? 'active' : ''; ?>" 
                   id="settingsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cogs me-2"></i>
                    Settings
                </a>
                <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="settingsDropdown">
                    <li><a class="dropdown-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/settings/account') !== false ? 'active' : ''; ?>" href="/admin/settings/account.php">
                        <i class="fas fa-user-cog me-2"></i> Account
                    </a></li>
                    <li><a class="dropdown-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/settings/general') !== false ? 'active' : ''; ?>" href="/admin/settings/general.php">
                        <i class="fas fa-cogs me-2"></i> General
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/admin/breaks/setup_database.php">
                        <i class="fas fa-database me-2"></i> Database Setup
                    </a></li>
                    <li><a class="dropdown-item" href="/emergency_access.php" target="_blank">
                        <i class="fas fa-tools me-2"></i> Emergency Tools
                    </a></li>
                </ul>
            </li>
        </ul>
        <hr>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="https://via.placeholder.com/32" alt="Admin" width="32" height="32" class="rounded-circle me-2">
                <strong><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'; ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="/admin/settings/account.php">
                    <i class="fas fa-user-circle me-2"></i> My Account
                </a></li>
                <li><a class="dropdown-item" href="/" target="_blank">
                    <i class="fas fa-globe me-2"></i> View Website
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/admin/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i> Sign out
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Mobile Sidebar Toggle Button (visible on small screens) -->
<div class="d-md-none position-fixed bottom-0 end-0 m-3" style="z-index: 1050;">
    <button class="btn btn-primary rounded-circle" id="sidebarToggle" style="width: 50px; height: 50px;">
        <i class="fas fa-bars"></i>
    </button>
</div>

<style>
/* Custom dropdown styles for sidebar */
.sidebar .dropdown-menu {
    background-color: #343a40;
    border: 1px solid #495057;
    margin-left: 0.5rem;
    min-width: 200px;
}

.sidebar .dropdown-item {
    color: rgba(255, 255, 255, 0.8);
    padding: 0.5rem 1rem;
    transition: all 0.2s ease;
}

.sidebar .dropdown-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.sidebar .dropdown-item.active {
    background-color: #0d6efd;
    color: #fff;
}

.sidebar .dropdown-item.disabled {
    color: rgba(255, 255, 255, 0.4);
}

.sidebar .dropdown-divider {
    border-color: #495057;
}

.sidebar .nav-link.dropdown-toggle::after {
    margin-left: auto;
}

/* Mobile responsive adjustments */
@media (max-width: 991.98px) {
    .sidebar .dropdown-menu {
        position: static !important;
        transform: none !important;
        margin-left: 1rem;
        margin-top: 0.5rem;
        box-shadow: none;
        border-left: 2px solid #0d6efd;
        border-radius: 0;
        background-color: rgba(0, 0, 0, 0.1);
    }
    
    .sidebar .dropdown-menu.show {
        display: block;
    }
}
</style>