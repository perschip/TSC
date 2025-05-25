<?php
// Include database connection and helper functions
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($message)) {
        $error_message = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Get recipient email from settings
        $to_email = getSetting('contact_email', 'info@tristatecards.com');
        
        // Prepare email headers
        $headers = "From: $name <$email>" . "\r\n";
        $headers .= "Reply-To: $email" . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Prepare email body
        $email_body = "<p><strong>Name:</strong> $name</p>";
        $email_body .= "<p><strong>Email:</strong> $email</p>";
        $email_body .= "<p><strong>Subject:</strong> $subject</p>";
        $email_body .= "<p><strong>Message:</strong></p>";
        $email_body .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>";
        
        // Send email
        if (mail($to_email, "Contact Form: $subject", $email_body, $headers)) {
            $success_message = 'Thank you for your message! We will get back to you soon.';
            
            // Save to database if needed
            try {
                $query = "INSERT INTO contact_messages (name, email, subject, message, ip_address, created_at) 
                          VALUES (:name, :email, :subject, :message, :ip, NOW())";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':subject', $subject);
                $stmt->bindParam(':message', $message);
                $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                $stmt->execute();
            } catch (PDOException $e) {
                // Silent fail - message was already sent by email
            }
            
            // Clear form data after successful submission
            $name = $email = $subject = $message = '';
        } else {
            $error_message = 'Sorry, there was an error sending your message. Please try again later.';
        }
    }
}

// Set page variables
$page_title = 'Contact Us';
$extra_css = '/assets/css/contact.css'; // Link to the page-specific CSS

// Include header
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1>Contact Us</h1>
        <p class="lead">Have questions or need assistance? We're here to help!</p>
    </div>
</section>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="card contact-form-card shadow-sm">
                <div class="card-body">
                    <h2 class="contact-form-heading mb-4">Send Us a Message</h2>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="contact.php" id="contact-form" class="contact-form">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Your Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Your Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject"
                                   value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card contact-info-card mb-4">
                <div class="card-body">
                    <h4 class="contact-info-heading">Contact Information</h4>
                    
                    <div class="contact-info-item">
                        <div class="contact-info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-info-content">
                            <h5>Address</h5>
                            <p>
                                Tristate Cards<br>
                                123 Card Collector Ave<br>
                                Hoffman, NJ 07601
                            </p>
                        </div>
                    </div>
                    
                    <div class="contact-info-item">
                        <div class="contact-info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-info-content">
                            <h5>Phone</h5>
                            <p>
                                <a href="tel:+12015551234"><?php echo htmlspecialchars(getSetting('contact_phone', '(201) 555-1234')); ?></a>
                            </p>
                        </div>
                    </div>
                    
                    <div class="contact-info-item">
                        <div class="contact-info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-info-content">
                            <h5>Email</h5>
                            <p>
                                <a href="mailto:<?php echo htmlspecialchars(getSetting('contact_email', 'info@tristatecards.com')); ?>"><?php echo htmlspecialchars(getSetting('contact_email', 'info@tristatecards.com')); ?></a>
                            </p>
                        </div>
                    </div>
                    
                    <div class="contact-info-item">
                        <div class="contact-info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="contact-info-content">
                            <h5>Business Hours</h5>
                            <p>
                                Monday - Friday: 9am - 5pm<br>
                                Saturday: 10am - 4pm<br>
                                Sunday: Closed
                            </p>
                        </div>
                    </div>
                    
                    <div class="social-links">
                        <?php if ($facebook = getSetting('social_facebook')): ?>
                            <a href="<?php echo htmlspecialchars($facebook); ?>" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        
                        <?php if ($twitter = getSetting('social_twitter')): ?>
                            <a href="<?php echo htmlspecialchars($twitter); ?>" target="_blank"><i class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                        
                        <?php if ($instagram = getSetting('social_instagram')): ?>
                            <a href="<?php echo htmlspecialchars($instagram); ?>" target="_blank"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        
                        <?php if ($youtube = getSetting('social_youtube')): ?>
                            <a href="<?php echo htmlspecialchars($youtube); ?>" target="_blank"><i class="fab fa-youtube"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card faq-card">
                <div class="card-body">
                    <h4 class="contact-info-heading">Frequently Asked Questions</h4>
                    
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                    Do you buy cards?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, we're always interested in purchasing quality cards and collections. Contact us with details about what you're looking to sell.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    How do I join a Whatnot break?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Follow us on Whatnot to get notifications for upcoming breaks. During the stream, you can purchase spots directly through the Whatnot platform.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    What shipping methods do you offer?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We ship via USPS First Class for orders under $50 and USPS Priority Mail for orders over $50. International shipping is available at additional rates.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Form validation
document.getElementById('contact-form').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const message = document.getElementById('message').value.trim();
    let isValid = true;
    
    if (name === '') {
        isValid = false;
        document.getElementById('name').classList.add('is-invalid');
    } else {
        document.getElementById('name').classList.remove('is-invalid');
    }
    
    if (email === '' || !isValidEmail(email)) {
        isValid = false;
        document.getElementById('email').classList.add('is-invalid');
    } else {
        document.getElementById('email').classList.remove('is-invalid');
    }
    
    if (message === '') {
        isValid = false;
        document.getElementById('message').classList.add('is-invalid');
    } else {
        document.getElementById('message').classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
    }
});

function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}
</script>