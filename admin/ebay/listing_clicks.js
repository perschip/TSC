/**
 * eBay Listing Click Tracking
 * This script tracks clicks on eBay listings and records them via AJAX
 */
$(document).ready(function() {
    console.log('eBay click tracking initialized');
    
    // Add click tracking to all eBay listing links
    $(document).on('click', '.ebay-listing-link', function(e) {
        const listingId = $(this).data('id');
        console.log('Tracking click for listing ID:', listingId);
        
        // Record the click via AJAX
        $.ajax({
            url: 'track_click.php',
            type: 'POST',
            data: {
                listing_id: listingId
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                console.log('Click tracked successfully:', response);
                
                // Update the click count display without refreshing the page
                const clickCountSpan = $(`a[data-id="${listingId}"]`).closest('td').find('.click-count');
                if (clickCountSpan.length) {
                    // If the span exists, update the count
                    const currentCount = parseInt(clickCountSpan.text(), 10) || 0;
                    clickCountSpan.text(currentCount + 1);
                } else {
                    // If the span doesn't exist, add it
                    const infoDiv = $(`a[data-id="${listingId}"]`).closest('td').find('.small.text-muted');
                    infoDiv.append('<span class="ms-2 text-info click-count"><i class="fas fa-mouse-pointer"></i> 1 clicks</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error tracking click:', error, xhr.responseText);
            }
        });
        
        // Don't prevent the default action - let the user navigate to eBay
    });
});
