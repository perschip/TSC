<?php
// This would be part of includes/footer.php
// Replace the current quick links section with this code

// Query to get footer navigation links (if you're using a navigation table)
try {
    $footer_links_query = "SELECT * FROM navigation WHERE location IN ('footer', 'both') AND is_active = 1 ORDER BY category, display_order ASC";
    $footer_links_stmt = $pdo->prepare($footer_links_query);
    $footer_links_stmt->execute();
    $footer_links = $footer_links_stmt->fetchAll();
    
    // Organize links by category
    $categorized_links = [];
    foreach ($footer_links as $link) {
        $category = $link['category'] ?? 'Main';
        if (!isset($categorized_links[$category])) {
            $categorized_links[$category] = [];
        }
        $categorized_links[$category][] = $link;
    }
    
    // Sort categories alphabetically, but keep Main first and More last
    uksort($categorized_links, function($a, $b) {
        if ($a === 'Main') return -1;
        if ($b === 'Main') return 1;
        if ($a === 'More') return 1;
        if ($b === 'More') return -1;
        return strcasecmp($a, $b);
    });
} catch (PDOException $e) {
    // In case of error, use default categories and links
    $categorized_links = [
        'Main' => [
            ['title' => 'Home', 'url' => 'index.php'],
            ['title' => 'Blog', 'url' => 'blog.php'],
            ['title' => 'About', 'url' => 'about.php'],
            ['title' => 'Contact', 'url' => 'contact.php'],
            ['title' => 'Testimonials', 'url' => 'testimonials.php'],
        ],
        'Legal' => [
            ['title' => 'Privacy Policy', 'url' => 'privacy.php'],
            ['title' => 'Terms of Service', 'url' => 'terms.php'],
        ]
    ];
}
?>

<!-- Footer -->
<footer class="footer mt-5 py-5 bg-dark text-white">
    <div class="container">
        <div class="row">
            <!-- Site Information -->
            <div class="col-lg-4 mb-4">
                <h5><?php echo htmlspecialchars(getSetting('site_name', 'Tristate Cards')); ?></h5>
                <hr class="accent-line">
                <div class="contact-info">
                    <p class="mb-2">
                        <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars(getSetting('contact_email', 'info@tristatecards.com')); ?>
                    </p>
                    <p class="mb-2">
                        <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars(getSetting('contact_phone', '(201) 555-1234')); ?>
                    </p>
                    <p class="mb-3">
                        <i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars(getSetting('contact_address', 'Hoffman, New Jersey, US')); ?>
                    </p>
                    
                    <div class="social-links mb-3">
                        <?php if ($instagram = getSetting('social_instagram')): ?>
                            <a href="<?php echo htmlspecialchars($instagram); ?>" target="_blank" class="me-2"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        
                        <?php if ($twitter = getSetting('social_twitter')): ?>
                            <a href="<?php echo htmlspecialchars($twitter); ?>" target="_blank" class="me-2"><i class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                        
                        <?php if ($youtube = getSetting('social_youtube')): ?>
                            <a href="<?php echo htmlspecialchars($youtube); ?>" target="_blank" class="me-2"><i class="fab fa-youtube"></i></a>
                        <?php endif; ?>
                        
                        <?php if ($facebook = getSetting('social_facebook')): ?>
                            <a href="<?php echo htmlspecialchars($facebook); ?>" target="_blank" class="me-2"><i class="fab fa-facebook"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="col-lg-8">
                <div class="row">
                    <?php 
                    // Calculate column width based on category count 
                    $column_count = count($categorized_links);
                    $column_class = 'col-md-' . min(12, max(3, floor(12 / $column_count)));
                    
                    // Display each category
                    foreach ($categorized_links as $category => $links): 
                        if (!empty($links)):
                    ?>
                    <div class="<?php echo $column_class; ?> mb-4">
                        <h5><?php echo htmlspecialchars($category); ?></h5>
                        <ul class="list-unstyled footer-links">
                            <?php foreach ($links as $link): ?>
                                <li class="mb-2">
                                    <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                                       <?php echo isset($link['target']) && $link['target'] ? 'target="' . htmlspecialchars($link['target']) . '"' : ''; ?>>
                                        <?php echo htmlspecialchars($link['title']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
        
        <hr class="my-4 bg-secondary">
        
        <div class="row">
            <div class="col-md-6">
                <p class="small text-muted mb-0">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getSetting('site_name', 'Tristate Cards')); ?>. All rights reserved.
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="small text-muted mb-0">
                    <a href="privacy.php" class="text-muted">Privacy Policy</a> | 
                    <a href="terms.php" class="text-muted">Terms of Service</a>
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript for the page -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
</body>
</html>