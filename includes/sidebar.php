<div class="col-md-3 col-lg-2 d-md-block sidebar collapse p-0">
    <div class="d-flex flex-column p-3 h-100">
        <a href="/admin/index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <span class="fs-4">Tristate Cards</span>
        </a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="/admin/index.php" class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'active' : ''; ?>" aria-current="page">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <p class="sidebar-heading mt-4 mb-2">Integrations</p>
            </li>
            <li>
                <a href="/admin/whatnot/settings.php" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/whatnot/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-video me-2"></i>
                    Whatnot Integration
                </a>
            </li>
            <li>
                <p class="sidebar-heading mt-4 mb-2">System</p>
            </li>
            <li>
                <a href="/admin/analytics/dashboard.php" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/analytics/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line me-2"></i>
                    Analytics
                </a>
            </li>
        </ul>
        <hr>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="https://via.placeholder.com/32" alt="Admin" width="32" height="32" class="rounded-circle me-2">
                <strong><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="/admin/logout.php">Sign out</a></li>
            </ul>
        </div>
    </div>
</div>