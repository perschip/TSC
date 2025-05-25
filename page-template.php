<?php
// Page template for custom pages from database
// Expected variables: $page_title, $meta_description, $page_content

// Safety check in case this file is accessed directly
if (!isset($page_title) || !isset($page_content)) {
    header("Location: /");
    exit;
}

// Include header
include 'includes/header.php';
?>

<!-- Page Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
    </div>
</section>

<!-- Main Content Section -->
<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5">
                    <?php echo $page_content; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include footer
include 'includes/footer.php'; 
?>