<?php
// admin/includes/sidebar.php - Completely updated version with dropdowns
?>
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
            
            <!-- Content Section -->
            <li>
                <p class="sidebar-heading mt-2 mb-1">Content</p>
            </li>
            
            <!-- Blog Posts Dropdown -->
            <li>
                <a href="#" class="nav-link dropdown-toggle <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/blog/') !== false && strpos($_SERVER['REQUEST_URI'], '/admin/blog/settings.php') === false ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#blogSubmenu" aria-expanded="false">
                    <i class="fas fa-blog me-2"></i>
                    Blog Posts
                </a>
                <div class="collapse <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/blog/') !== false && strpos($_SERVER['REQUEST_URI'], '/admin/blog/settings.php') === false ? 'show' : ''; ?>" id="blogSubmenu">
                    <ul class="nav flex-column ms-3 mt-2">
                        <li>
                            <a href="/admin/blog/list.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'list.php' && strpos($_SERVER['REQUEST_URI'], '/admin/blog/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-list me-1"></i> All Posts
                            </a>
                        </li>
                        <li>
                            <a href="/admin/blog/create.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'create.php' && strpos($_SERVER['REQUEST_URI'], '/admin/blog/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-plus me-1"></i> Add New
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Blog Settings Link -->
            <li>
                <a href="/admin/blog/settings.php" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/blog/settings.php') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-cog me-2"></i>
                    Blog Settings
                </a>
            </li>
            
            <!-- Pages Dropdown -->
            <li>
                <a href="#" class="nav-link dropdown-toggle <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/pages/') !== false ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#pagesSubmenu" aria-expanded="false">
                    <i class="fas fa-file-alt me-2"></i>
                    Pages
                </a>
                <div class="collapse <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/pages/') !== false ? 'show' : ''; ?>" id="pagesSubmenu">
                    <ul class="nav flex-column ms-3 mt-2">
                        <li>
                            <a href="/admin/pages/list.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'list.php' && strpos($_SERVER['REQUEST_URI'], '/admin/pages/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-list me-1"></i> All Pages
                            </a>
                        </li>
                        <li>
                            <a href="/admin/pages/create.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'create.php' && strpos($_SERVER['REQUEST_URI'], '/admin/pages/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-plus me-1"></i> Add New
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Navigation Manager Dropdown -->
            <li>
                <a href="#" class="nav-link dropdown-toggle <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/navigation/') !== false ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#navigationSubmenu" aria-expanded="false">
                    <i class="fas fa-bars me-2"></i>
                    Navigation Manager
                </a>
                <div class="collapse <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/navigation/') !== false ? 'show' : ''; ?>" id="navigationSubmenu">
                    <ul class="nav flex-column ms-3 mt-2">
                        <li>
                            <a href="/admin/navigation/manage.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'manage.php' && strpos($_SERVER['REQUEST_URI'], '/admin/navigation/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-link me-1"></i> Manage Links
                            </a>
                        </li>
                        <li>
                            <a href="/admin/navigation/categories.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'categories.php' && strpos($_SERVER['REQUEST_URI'], '/admin/navigation/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-folder me-1"></i> Manage Categories
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Testimonials Dropdown -->
            <li>
                <a href="#" class="nav-link dropdown-toggle <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/testimonials/') !== false ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#testimonialsSubmenu" aria-expanded="false">
                    <i class="fas fa-comment-dots me-2"></i>
                    Testimonials
                </a>
                <div class="collapse <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/testimonials/') !== false ? 'show' : ''; ?>" id="testimonialsSubmenu">
                    <ul class="nav flex-column ms-3 mt-2">
                        <li>
                            <a href="/admin/testimonials/list.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'list.php' && strpos($_SERVER['REQUEST_URI'], '/admin/testimonials/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-list me-1"></i> All Testimonials
                            </a>
                        </li>
                        <li>
                            <a href="/admin/testimonials/create.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'create.php' && strpos($_SERVER['REQUEST_URI'], '/admin/testimonials/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-plus me-1"></i> Add New
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Integrations Section -->
            <li>
                <p class="sidebar-heading mt-2 mb-1">Integrations</p>
            </li>
            
            <!-- Whatnot Integration -->
            <li>
                <a href="/admin/whatnot/settings.php" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/whatnot/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-video me-2"></i>
                    Whatnot Integration
                </a>
            </li>
            
            <!-- System Section -->
            <li>
                <p class="sidebar-heading mt-2 mb-1">System</p>
            </li>
            
            <!-- Analytics -->
            <li>
                <a href="/admin/analytics/dashboard.php" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/analytics/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line me-2"></i>
                    Analytics
                </a>
            </li>
            
            <!-- Settings Dropdown -->
            <li>
                <a href="#" class="nav-link dropdown-toggle <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/settings/') !== false ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#settingsSubmenu" aria-expanded="false">
                    <i class="fas fa-cogs me-2"></i>
                    Settings
                </a>
                <div class="collapse <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/settings/') !== false ? 'show' : ''; ?>" id="settingsSubmenu">
                    <ul class="nav flex-column ms-3 mt-2">
                        <li>
                            <a href="/admin/settings/account.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'account.php' && strpos($_SERVER['REQUEST_URI'], '/admin/settings/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-user-cog me-1"></i> Account Settings
                            </a>
                        </li>
                        <li>
                            <a href="/admin/settings/general.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'general.php' && strpos($_SERVER['REQUEST_URI'], '/admin/settings/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-sliders-h me-1"></i> General Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        <hr>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="https://via.placeholder.com/32" alt="Admin" width="32" height="32" class="rounded-circle me-2">
                <strong><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'; ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="/admin/settings/account.php">Account Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/admin/logout.php">Sign out</a></li>
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