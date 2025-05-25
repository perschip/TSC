<?php
// 404 Error Page
$page_title = 'Page Not Found';
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1>Page Not Found</h1>
        <p class="lead">The page you requested could not be found.</p>
    </div>
</section>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5 text-center">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 5rem;"></i>
                    <h2 class="mt-4">404 - Page Not Found</h2>
                    <p class="lead">We couldn't find the page you were looking for.</p>
                    <p>The page may have been moved, deleted, or never existed.</p>
                    <a href="/" class="btn btn-primary mt-3">Return to Homepage</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>