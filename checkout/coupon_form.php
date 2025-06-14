<?php
// checkout/coupon_form.php
// This file contains the coupon form component for the checkout page
?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Discount Code</h5>
    </div>
    <div class="card-body">
        <!-- Coupon message area -->
        <div id="couponMessage" style="display: none;" class="alert"></div>
        
        <!-- Coupon details (shown when coupon is applied) -->
        <div id="couponDetails" style="display: none;"></div>
        
        <!-- Coupon form -->
        <form id="couponForm">
            <div class="input-group">
                <input type="text" id="couponCode" class="form-control" placeholder="Enter coupon code">
                <button type="submit" id="applyCoupon" class="btn btn-primary">Apply</button>
            </div>
            <small class="form-text text-muted">Enter a valid coupon code to get a discount</small>
        </form>
        
        <!-- Remove coupon button (hidden by default) -->
        <button id="removeCoupon" class="btn btn-sm btn-outline-danger mt-2" style="display: none;">
            Remove Coupon
        </button>
    </div>
</div>

<!-- Make sure to include the coupon.js script in your checkout page -->
<script>
    // Check if the script is already loaded
    if (typeof window.couponScriptLoaded === 'undefined') {
        // Create script element
        const script = document.createElement('script');
        script.src = '/assets/js/coupon.js';
        script.async = true;
        script.onload = function() {
            window.couponScriptLoaded = true;
        };
        
        // Append to document
        document.head.appendChild(script);
    }
</script>
