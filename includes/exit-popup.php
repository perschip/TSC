<?php
// includes/exit-popup.php
// Exit intent popup with special coupon offer to reduce bounce rate
?>
<!-- Exit Intent Popup -->
<div id="exitIntentPopup" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-tag me-2"></i> Special Offer Just For You!
                </h5>
                <button type="button" id="closeExitPopup" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <h3>Don't Leave Empty-Handed!</h3>
                    <p class="lead">Get <strong>15% OFF</strong> your purchase today!</p>
                </div>
                
                <div class="coupon-container p-3 mb-4 bg-light rounded text-center">
                    <p class="mb-1"><small>Use this code at checkout:</small></p>
                    <div class="d-flex justify-content-center align-items-center">
                        <h2 class="mb-0 me-2 coupon-code" id="exitCouponCode">SAVE15</h2>
                        <button id="copyCouponBtn" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-copy me-1"></i> Copy
                        </button>
                    </div>
                </div>
                
                <div id="exitIntentFormContent">
                    <p class="text-center">Sign up to receive your coupon code by email and get notified about future deals!</p>
                    
                    <form id="exitIntentForm">
                        <div class="alert alert-danger" id="exitIntentError" style="display: none;"></div>
                        
                        <div class="mb-3">
                            <label for="exitIntentName" class="form-label">Your Name</label>
                            <input type="text" class="form-control" id="exitIntentName" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exitIntentEmail" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="exitIntentEmail" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-envelope me-2"></i> Send Me My Coupon
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                By signing up, you agree to receive promotional emails. You can unsubscribe at any time.
                            </small>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <a href="/checkout/" class="btn btn-success">
                    <i class="fas fa-shopping-cart me-2"></i> Shop Now with 15% OFF
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Exit Intent Popup Styles */
#exitIntentPopup {
    background-color: rgba(0, 0, 0, 0.8);
}

#exitIntentPopup.show {
    display: block;
}

.coupon-code {
    font-family: monospace;
    letter-spacing: 2px;
    font-weight: bold;
    color: #0d6efd;
    padding: 8px 15px;
    background-color: #e9ecef;
    border-radius: 4px;
    border: 1px dashed #6c757d;
}

.coupon-container {
    border: 2px dashed #0d6efd;
}

/* Animation for popup */
#exitIntentPopup .modal-dialog {
    animation: popupAnimation 0.5s ease;
}

@keyframes popupAnimation {
    0% { transform: scale(0.7); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
</style>

<script>
// Load the exit intent script
document.addEventListener('DOMContentLoaded', function() {
    // Create script element
    const script = document.createElement('script');
    script.src = '/assets/js/exit-intent.js';
    script.async = true;
    
    // Append to document
    document.head.appendChild(script);
});
</script>
