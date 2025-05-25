<?php
// Include database connection and helper functions
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get all published testimonials
try {
    $testimonials_query = "SELECT * FROM testimonials WHERE status = 'published' ORDER BY is_featured DESC, created_at DESC";
    $testimonials_stmt = $pdo->prepare($testimonials_query);
    $testimonials_stmt->execute();
    $testimonials = $testimonials_stmt->fetchAll();
} catch (PDOException $e) {
    $testimonials = [];
}

// Set page variables
$page_title = 'Customer Testimonials';

// Include header
include 'includes/header.php';
?>

<!-- Hero Banner Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1>Customer Testimonials</h1>
        <p class="lead">What our valued customers have to say about their experience with us</p>
    </div>
</section>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <?php if (empty($testimonials)): ?>
                <div class="alert alert-info">
                    <p class="mb-0">No testimonials available at this time. Check back soon!</p>
                </div>
            <?php else: ?>
                <div class="testimonials-list">
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="card mb-4 testimonial-card <?php echo $testimonial['is_featured'] ? 'border-primary' : ''; ?>">
                            <div class="card-body">
                                <?php if ($testimonial['is_featured']): ?>
                                    <div class="featured-badge">
                                        <i class="fas fa-star text-warning"></i> Featured
                                    </div>
                                <?php endif; ?>
                                
                                <blockquote class="testimonial-quote">
                                    <p class="mb-3">"<?php echo htmlspecialchars($testimonial['content']); ?>"</p>
                                    <footer class="blockquote-footer">
                                        <?php echo htmlspecialchars($testimonial['author_name']); ?>
                                        <?php if (!empty($testimonial['author_location'])): ?>
                                            <cite title="Source Title"><?php echo htmlspecialchars($testimonial['author_location']); ?></cite>
                                        <?php endif; ?>
                                    </footer>
                                </blockquote>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
                
            <!-- Share Your Experience Section -->
            <div class="card mt-5">
                <div class="card-body">
                    <h3 class="card-title">Share Your Experience</h3>
                    <p>We value your feedback! If you've enjoyed our products or services, please consider leaving a testimonial. Contact us at <?php echo htmlspecialchars(getSetting('contact_email', 'info@tristatecards.com')); ?> to share your story.</p>
                    <a href="contact.php" class="btn btn-primary">Contact Us</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.testimonial-card {
    transition: transform 0.3s ease;
    margin-bottom: 1.5rem;
}

.testimonial-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
}

.testimonial-card.border-primary {
    border-width: 2px;
}

.featured-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: #f8f9fa;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.testimonial-quote p {
    font-size: 1.1rem;
    line-height: 1.6;
    font-style: italic;
}

.blockquote-footer {
    margin-top: 0.5rem;
    text-align: right;
}

.hero-section {
    background-color: #f8f9fa;
    padding: 3rem 0;
    margin-bottom: 2rem;
}
</style>

<?php include 'includes/footer.php'; ?>