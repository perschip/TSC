<?php
// Include database connection and helper functions
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get site settings
$site_name = getSetting('site_name', 'Tristate Cards');
$site_description = getSetting('site_description', 'Your trusted source for sports cards, collectibles, and memorabilia');

// Include header
$page_title = 'About Us';
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1>About Tristate Cards</h1>
        <p class="lead">Our story, mission, and passion for sports cards</p>
    </div>
</section>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5">
                    <h2 class="mb-4">Our Story</h2>
                    
                    <p>Welcome to Tristate Cards, your premier destination for sports cards, collectibles, and memorabilia. Founded in 2019, we've grown from a small hobby shop into a trusted online retailer serving collectors across the country.</p>
                    
                    <p>What started as a passion project between two lifelong friends and card collectors has evolved into a business dedicated to bringing quality products, fair prices, and exceptional service to fellow enthusiasts.</p>
                    
                    <div class="text-center my-5">
                        <img src="https://via.placeholder.com/800x400" alt="Tristate Cards Team" class="img-fluid rounded shadow">
                        <p class="text-muted mt-2 small">The Tristate Cards team at a recent card show</p>
                    </div>
                    
                    <h3 class="mt-5 mb-3">Our Mission</h3>
                    
                    <p>At Tristate Cards, our mission is simple: to provide collectors with authentic, high-quality sports cards and memorabilia while delivering an exceptional shopping experience. We believe collecting should be fun, transparent, and accessible to everyone – from seasoned investors to newcomers just discovering the hobby.</p>
                    
                    <h3 class="mt-5 mb-3">What Sets Us Apart</h3>
                    
                    <div class="row mt-4">
                        <div class="col-md-6 mb-4">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-certificate text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <div>
                                    <h4>Authenticity Guaranteed</h4>
                                    <p>Every card we sell undergoes rigorous authentication. If it's not genuine, we don't sell it.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-handshake text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <div>
                                    <h4>Fair Pricing</h4>
                                    <p>We keep our margins reasonable and our prices transparent – no hidden fees or inflated values.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-video text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <div>
                                    <h4>Live Breaks</h4>
                                    <p>Our Whatnot streams bring the excitement of card breaks directly to you, no matter where you're located.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-users text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <div>
                                    <h4>Community Focus</h4>
                                    <p>We're building more than a business – we're cultivating a community of passionate collectors.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h3 class="mt-5 mb-3">Meet the Team</h3>
                    
                    <div class="row mt-4">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <img src="https://via.placeholder.com/300x300" class="card-img-top" alt="Team Member">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Mike Johnson</h5>
                                    <p class="text-muted">Founder & CEO</p>
                                    <p class="card-text">A lifelong baseball card collector who turned his passion into a business after 15 years in finance.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <img src="https://via.placeholder.com/300x300" class="card-img-top" alt="Team Member">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Sarah Williams</h5>
                                    <p class="text-muted">Operations Manager</p>
                                    <p class="card-text">The organizational genius behind our smooth operations and customer service excellence.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h3 class="mt-5 mb-3">Our Certifications and Memberships</h3>
                    
                    <p>Tristate Cards is a proud member of the following organizations:</p>
                    
                    <ul>
                        <li>Professional Sports Authenticator (PSA) Authorized Dealer</li>
                        <li>Sports Card Retailers Association</li>
                        <li>Better Business Bureau (A+ Rating)</li>
                        <li>National Sports Collectors Convention Exhibitor</li>
                    </ul>
                    
                    <div class="alert alert-primary mt-5">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle fa-2x"></i>
                            </div>
                            <div>
                                <h4>Want to know more?</h4>
                                <p class="mb-0">Have questions about our business or interested in wholesale opportunities? We'd love to hear from you! <a href="contact.php">Contact us</a> today.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>