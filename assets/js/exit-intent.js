/**
 * Exit Intent Popup with Coupon Offer
 * Displays a popup with a special coupon offer when the user is about to leave the site
 * This helps reduce the site's high bounce rate (99.8%)
 */
document.addEventListener('DOMContentLoaded', function() {
    // Check if the user has already seen the popup
    const hasSeenPopup = localStorage.getItem('exitPopupShown');
    
    // Generate a random coupon code for this session
    const generateCouponCode = () => {
        const prefix = 'SAVE';
        const characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed confusing characters
        let result = prefix;
        for (let i = 0; i < 5; i++) {
            result += characters.charAt(Math.floor(Math.random() * characters.length));
        }
        return result;
    };
    
    // Store the coupon code in session storage
    if (!sessionStorage.getItem('exitCouponCode')) {
        sessionStorage.setItem('exitCouponCode', generateCouponCode());
    }
    
    if (!hasSeenPopup) {
        // Set up exit intent detection
        let showExitPopup = true;
        let mouseY = 0;
        
        // Detect when the mouse leaves the window at the top
        document.addEventListener('mouseleave', function(e) {
            mouseY = e.clientY;
            if (mouseY < 50 && showExitPopup) {
                showPopup();
            }
        });
        
        // Also show after a certain amount of time on the page
        setTimeout(function() {
            if (showExitPopup) {
                showPopup();
            }
        }, 30000); // 30 seconds
        
        // Function to show the popup
        function showPopup() {
            if (!showExitPopup) return;
            
            // Get the coupon code
            const couponCode = sessionStorage.getItem('exitCouponCode');
            
            // Update the popup content with the coupon code
            const couponElement = document.getElementById('exitCouponCode');
            if (couponElement) {
                couponElement.textContent = couponCode;
            }
            
            // Show the popup
            document.getElementById('exitIntentPopup').classList.add('show');
            showExitPopup = false;
            
            // Mark as shown for this session
            localStorage.setItem('exitPopupShown', 'true');
            
            // Set expiration for 1 day
            setTimeout(function() {
                localStorage.removeItem('exitPopupShown');
            }, 24 * 60 * 60 * 1000);
            
            // Track this event for analytics
            if (typeof gtag === 'function') {
                gtag('event', 'exit_intent_popup_shown', {
                    'event_category': 'Engagement',
                    'event_label': 'Exit Intent Popup',
                    'coupon_code': couponCode
                });
            }
        }
        
        // Close popup when the close button is clicked
        document.getElementById('closeExitPopup').addEventListener('click', function() {
            document.getElementById('exitIntentPopup').classList.remove('show');
        });
        
        // Copy coupon code to clipboard
        document.getElementById('copyCouponBtn').addEventListener('click', function() {
            const couponCode = document.getElementById('exitCouponCode').textContent;
            navigator.clipboard.writeText(couponCode).then(function() {
                // Show success message
                const copyBtn = document.getElementById('copyCouponBtn');
                const originalText = copyBtn.textContent;
                copyBtn.textContent = 'Copied!';
                copyBtn.classList.add('btn-success');
                copyBtn.classList.remove('btn-outline-primary');
                
                // Reset button after 2 seconds
                setTimeout(function() {
                    copyBtn.textContent = originalText;
                    copyBtn.classList.remove('btn-success');
                    copyBtn.classList.add('btn-outline-primary');
                }, 2000);
                
                // Track copy event
                if (typeof gtag === 'function') {
                    gtag('event', 'coupon_code_copied', {
                        'event_category': 'Engagement',
                        'event_label': 'Exit Coupon',
                        'coupon_code': couponCode
                    });
                }
            });
        });
        
        // Submit the newsletter form
        document.getElementById('exitIntentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('exitIntentEmail').value;
            const name = document.getElementById('exitIntentName').value;
            const couponCode = document.getElementById('exitCouponCode').textContent;
            
            // Send the data to the server
            fetch('subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `email=${encodeURIComponent(email)}&name=${encodeURIComponent(name)}&coupon=${encodeURIComponent(couponCode)}&source=exit_popup`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Track successful subscription
                    if (typeof gtag === 'function') {
                        gtag('event', 'exit_popup_subscription', {
                            'event_category': 'Engagement',
                            'event_label': 'Exit Popup Subscription',
                            'coupon_code': couponCode
                        });
                    }
                    
                    // Show success message with coupon code reminder
                    document.getElementById('exitIntentFormContent').innerHTML = `
                        <div class="text-center">
                            <h4>Thank You!</h4>
                            <p>You've been successfully subscribed. We've emailed your special discount code.</p>
                            <div class="alert alert-success">
                                <p class="mb-1">Your coupon code:</p>
                                <h3 class="mb-0">${couponCode}</h3>
                                <p class="mt-2 mb-0"><small>Get 15% off your purchase today!</small></p>
                            </div>
                            <a href="/checkout/" class="btn btn-primary mt-3">Shop Now</a>
                        </div>`;
                } else {
                    document.getElementById('exitIntentError').textContent = data.message || 'An error occurred. Please try again.';
                    document.getElementById('exitIntentError').style.display = 'block';
                }
            })
            .catch(error => {
                document.getElementById('exitIntentError').textContent = 'An error occurred. Please try again.';
                document.getElementById('exitIntentError').style.display = 'block';
            });
        });
    }
});
