/**
 * Coupon handling for checkout page
 * This script handles the coupon code application during checkout
 */

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const couponForm = document.getElementById('couponForm');
    const couponCode = document.getElementById('couponCode');
    const couponMessage = document.getElementById('couponMessage');
    const couponDetails = document.getElementById('couponDetails');
    const subtotalElement = document.getElementById('subtotal');
    const discountElement = document.getElementById('discount');
    const totalElement = document.getElementById('total');
    
    // Variables
    let appliedCoupon = null;
    let originalTotal = 0;
    let cart = [];
    
    // Initialize
    function init() {
        if (!couponForm) return;
        
        // Load cart from localStorage
        cart = JSON.parse(localStorage.getItem('tristateCart')) || [];
        
        // Calculate original total from cart
        calculateCartTotal();
        
        // Add event listeners
        if (couponForm) {
            couponForm.addEventListener('submit', handleCouponSubmit);
        }
        
        // Check if there's already a coupon applied (from PHP session)
        if (discountElement) {
            const discountText = discountElement.innerText.replace('$', '');
            const discountAmount = parseFloat(discountText);
            
            if (discountAmount > 0) {
                // There's already a coupon applied from the session
                appliedCoupon = {
                    code: 'Applied', // We don't know the code from JS
                    discount: discountAmount
                };
            }
        }
    }
    
    // Calculate cart total from localStorage
    function calculateCartTotal() {
        originalTotal = 0;
        
        if (cart && cart.length > 0) {
            cart.forEach(item => {
                originalTotal += item.price * item.quantity;
            });
        }
        
        // Update subtotal display
        if (subtotalElement) {
            subtotalElement.textContent = `$${originalTotal.toFixed(2)}`;
        }
        
        // Update total display if no discount is applied
        if (totalElement && !appliedCoupon) {
            totalElement.textContent = `$${originalTotal.toFixed(2)}`;
        }
        
        return originalTotal;
    }
    
    // Handle coupon form submission
    function handleCouponSubmit(e) {
        e.preventDefault();
        
        if (!couponCode || !couponCode.value.trim()) {
            showCouponMessage('Please enter a coupon code.', 'warning');
            return;
        }
        
        // Disable submit button and show loading state
        const submitBtn = couponForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Applying...';
        }
        
        // Get cart total from localStorage
        const cartTotal = calculateCartTotal();
        
        // Send AJAX request with cart data
        fetch('/checkout/apply_coupon.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'coupon_code': couponCode.value.trim(),
                'cart_total': cartTotal,
                'cart_items': JSON.stringify(cart)
            })
        })
        .then(response => response.json())
        .then(data => {
            // Reset button state
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Apply Coupon';
            }
            
            if (data.success) {
                // Show success message
                showCouponMessage(data.message, 'success');
                
                // Store applied coupon
                appliedCoupon = {
                    code: couponCode.value.trim(),
                    discount: data.discount,
                    discountType: data.discount_type,
                    discountDisplay: data.discount_display
                };
                
                // Update UI
                updateOrderSummary(data.discount, data.new_total);
                
                // Add a remove button to the coupon form
                if (!document.getElementById('removeCoupon')) {
                    const removeBtn = document.createElement('button');
                    removeBtn.id = 'removeCoupon';
                    removeBtn.className = 'btn btn-outline-danger mt-2';
                    removeBtn.innerHTML = 'Remove Coupon';
                    removeBtn.type = 'button';
                    removeBtn.addEventListener('click', removeCoupon);
                    couponMessage.parentNode.appendChild(removeBtn);
                }
                
                // Clear the coupon code input
                couponCode.value = '';
                
                // If there's a global updateOrderSummary function, call it to update cart totals
                if (typeof window.updateOrderSummary === 'function') {
                    window.updateOrderSummary();
                }
            } else {
                // Show error message
                showCouponMessage(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCouponMessage('An error occurred. Please try again.', 'danger');
            
            // Reset button state
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Apply Coupon';
            }
        });
    }
    
    // Show coupon message
    function showCouponMessage(message, type) {
        if (!couponMessage) return;
        
        couponMessage.textContent = message;
        couponMessage.className = `alert alert-${type} mt-2`;
        couponMessage.style.display = 'block';
        
        // Hide message after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(() => {
                couponMessage.style.display = 'none';
            }, 5000);
        }
    }
    
    // Update order summary
    function updateOrderSummary(discountAmount, newTotal) {
        if (!discountElement || !totalElement) return;
        
        // Update discount amount
        discountElement.textContent = `$${discountAmount.toFixed(2)}`;
        
        // Update total
        totalElement.textContent = `$${newTotal.toFixed(2)}`;
        
        // If there's a global updatePayPalAmount function, call it
        if (typeof window.updatePayPalAmount === 'function') {
            window.updatePayPalAmount(newTotal);
        }
    }
    
    // Remove coupon
    function removeCoupon() {
        // Remove the remove coupon button if it exists
        const removeBtn = document.getElementById('removeCoupon');
        if (removeBtn) {
            removeBtn.remove();
        }
        
        // Clear coupon code input
        if (couponCode) couponCode.value = '';
        
        // Hide any messages
        if (couponMessage) couponMessage.style.display = 'none';
        
        // Reset order summary
        if (discountElement) discountElement.textContent = '$0.00';
        if (totalElement) totalElement.textContent = `$${originalTotal.toFixed(2)}`;
        
        // Show success message for coupon removal
        showCouponMessage('Coupon removed successfully.', 'success');
        
        // Remove from session via AJAX
        fetch('/checkout/remove_coupon.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Error removing coupon:', data.message);
            } else {
                // If there's a global updateOrderSummary function, call it
                if (typeof window.updateOrderSummary === 'function') {
                    window.updateOrderSummary();
                }
                
                // If there's a global updatePayPalAmount function, call it
                if (typeof window.updatePayPalAmount === 'function') {
                    window.updatePayPalAmount(originalTotal);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
        
        // Reset applied coupon
        appliedCoupon = null;
    }
    
    // Initialize
    init();
});
