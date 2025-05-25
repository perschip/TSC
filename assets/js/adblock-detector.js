// Enhanced AdBlock Detector v3.0
(function() {
    // Function to run when DOM is fully loaded
    function initAdblockDetection() {
        console.log("AdBlock detection initialized");
        
        // Create a bait element that ad blockers typically target
        function createBait() {
            const bait = document.createElement('div');
            bait.setAttribute('class', 'ad-banner ad adsbox ad-placement ad-container');
            bait.setAttribute('id', 'ad-detector');
            bait.setAttribute('data-ad-status', 'not-blocked');
            bait.style.position = 'absolute';
            bait.style.height = '1px';
            bait.style.width = '1px';
            bait.style.left = '-10000px';
            bait.style.top = '-10000px';
            bait.innerHTML = '&nbsp;';
            document.body.appendChild(bait);
            return bait;
        }

        // Multiple detection methods to improve reliability
        function detectAdBlocker() {
            const bait = createBait();
            
            setTimeout(function() {
                let adBlockDetected = false;
                let detectionMethod = '';
                
                // Method 1: Check if the element has been hidden or removed
                if (bait.offsetParent === null || 
                    bait.offsetHeight === 0 || 
                    bait.offsetLeft === 0 || 
                    bait.offsetTop === 0 || 
                    bait.offsetWidth === 0 || 
                    bait.clientHeight === 0 || 
                    bait.clientWidth === 0) {
                    adBlockDetected = true;
                    detectionMethod = 'Offset method';
                }
                
                // Method 2: Check computed style
                const computed = window.getComputedStyle(bait);
                if (computed && (computed.display === 'none' || 
                                 computed.visibility === 'hidden' || 
                                 computed.opacity === '0')) {
                    adBlockDetected = true;
                    detectionMethod = 'Style method';
                }
                
                // Method 3: Check if element was removed
                if (!document.getElementById('ad-detector')) {
                    adBlockDetected = true;
                    detectionMethod = 'Element removed';
                }
                
                // Store adblock status in a global variable
                window.adBlockDetected = adBlockDetected;
                console.log("AdBlock detected: " + adBlockDetected + " (" + detectionMethod + ")");
                
                // Remove the bait if it still exists
                if (bait.parentNode) {
                    bait.parentNode.removeChild(bait);
                }
                
                // Check eBay listings if adblock is detected
                if (adBlockDetected) {
                    checkEbayListings();
                }
                
            }, 300); // Increased delay for more reliable detection
        }

        // Check for eBay listing issues
        function checkEbayListings() {
            console.log("Checking eBay listings");
            
            // Wait for Auction Nudge to attempt to load
            setTimeout(function() {
                // Try multiple possible container IDs
                const possibleContainers = [
                    'ebay-listings',
                    'auction-nudge-items',
                    'auction-nudge-tristatecards123',
                    'auction-nudge-classic123',
                    'auction-nudge-unique123',
                    'auction-nudge-4c9be4bc1'
                ];
                
                let containerFound = false;
                let listingsLoaded = false;
                let containerElement = null;
                
                // Check each possible container
                for (const containerId of possibleContainers) {
                    const container = document.getElementById(containerId);
                    if (container) {
                        containerFound = true;
                        containerElement = container;
                        
                        // Check for actual listings
                        const listingElements = container.querySelectorAll('.an-item, .an-auction, .item-card');
                        if (listingElements && listingElements.length > 0) {
                            listingsLoaded = true;
                            console.log(`Found ${listingElements.length} eBay listings in container #${containerId}`);
                            break;
                        }
                        
                        // Also check if iframe is loaded
                        const iframes = container.querySelectorAll('iframe');
                        if (iframes && iframes.length > 0) {
                            // If we have iframes, assume it might be working
                            const iframe = iframes[0];
                            if (iframe.contentDocument && iframe.contentDocument.body) {
                                const iframeContent = iframe.contentDocument.body.innerHTML;
                                if (iframeContent && iframeContent.length > 100) {
                                    listingsLoaded = true;
                                    console.log(`Found iframe content in container #${containerId}`);
                                    break;
                                }
                            }
                        }
                    }
                }
                
                console.log("eBay container found: " + containerFound);
                console.log("eBay listings loaded: " + listingsLoaded);
                
                // If no container was found, look for the main ebay-listings div
                if (!containerFound) {
                    const mainEbayContainer = document.getElementById('ebay-listings');
                    if (mainEbayContainer) {
                        containerElement = mainEbayContainer;
                        containerFound = true;
                    }
                }
                
                // If a container was found but listings weren't loaded, show error message
                if (containerFound && !listingsLoaded) {
                    showEbayErrorMessage(containerElement);
                } else if (!containerFound) {
                    // Try to find any elements with "ebay" in their ID or class
                    const ebayElements = document.querySelectorAll('[id*="ebay"], [class*="ebay"], [id*="auction"], [class*="auction"]');
                    if (ebayElements.length > 0) {
                        console.log(`Found ${ebayElements.length} possible eBay-related elements`);
                        // Show error in the first one
                        showEbayErrorMessage(ebayElements[0]);
                    } else {
                        // Last resort - try to find a container with the word "listing" in it
                        const listingElements = document.querySelectorAll('[id*="listing"], [class*="listing"]');
                        if (listingElements.length > 0) {
                            showEbayErrorMessage(listingElements[0]);
                        } else {
                            console.log("No eBay containers found at all");
                        }
                    }
                }
            }, 2000); // Allow 2 seconds for Auction Nudge to load
        }

        // Show error message for eBay listings
        function showEbayErrorMessage(container) {
            console.log("Showing eBay error message in container:", container);
            
            if (!container) {
                console.log("No container provided to show error message");
                // Try to find the ebay-listings container as a fallback
                container = document.getElementById('ebay-listings');
                if (!container) {
                    console.log("Could not find ebay-listings container");
                    return;
                }
            }
            
            // Check if error message already exists
            if (container.querySelector('.adblock-warning')) {
                console.log("Warning message already exists");
                return;
            }
            
            // Try to hide any auction nudge elements
            const auctionNudgeElements = document.querySelectorAll('[id^="auction-nudge-"], iframe');
            auctionNudgeElements.forEach(element => {
                if (element) {
                    element.style.display = 'none';
                }
            });
            
            // Create error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-warning adblock-warning';
            errorDiv.innerHTML = `
                <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i> eBay Listings Blocked</h5>
                <p>We've detected that you're using an ad blocker which is preventing our eBay listings from displaying properly.</p>
                <p class="mb-0">To view our current listings, please consider temporarily disabling your ad blocker for this site, or visit our <a href="https://www.ebay.com/usr/tristate_cards" target="_blank" class="alert-link">eBay store directly <i class="fas fa-external-link-alt fa-xs"></i></a>.</p>
            `;
            
            // Clear the container's content
            container.innerHTML = '';
            
            // Add our error message to the container
            container.appendChild(errorDiv);
            
            console.log("AdBlock warning message added to container");
        }

        // Start the detection process
        detectAdBlocker();
    }

    // Export the checkEbayListings function to the global scope
    window.checkEbayListings = function() {
        console.log("Manual check for eBay listings triggered");
        setTimeout(function() {
            const possibleContainers = [
                'ebay-listings',
                'auction-nudge-items',
                'auction-nudge-tristatecards123'
            ];
            
            let containerFound = false;
            let listingsLoaded = false;
            let containerElement = null;
            
            // Check each possible container
            for (const containerId of possibleContainers) {
                const container = document.getElementById(containerId);
                if (container) {
                    containerFound = true;
                    containerElement = container;
                    
                    // Check if container has content
                    if (container.innerHTML.trim() !== '') {
                        // Check for actual listings
                        const listingElements = container.querySelectorAll('.an-item, .an-auction, .item-card');
                        if (listingElements && listingElements.length > 0) {
                            listingsLoaded = true;
                            console.log(`Found ${listingElements.length} eBay listings in container #${containerId}`);
                            break;
                        }
                    }
                }
            }
            
            console.log("eBay container found: " + containerFound);
            console.log("eBay listings loaded: " + listingsLoaded);
            
            // If a container was found but listings weren't loaded, show error message
            if (containerFound && !listingsLoaded) {
                // Create error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-warning adblock-warning';
                errorDiv.innerHTML = `
                    <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i> eBay Listings Blocked</h5>
                    <p>We've detected that you're using an ad blocker which is preventing our eBay listings from displaying properly.</p>
                    <p class="mb-0">To view our current listings, please consider temporarily disabling your ad blocker for this site, or visit our <a href="https://www.ebay.com/usr/tristate_cards" target="_blank" class="alert-link">eBay store directly <i class="fas fa-external-link-alt fa-xs"></i></a>.</p>
                `;
                
                // Clear the container and add our message
                if (containerElement) {
                    containerElement.innerHTML = '';
                    containerElement.appendChild(errorDiv);
                    console.log("AdBlock warning message added to container");
                }
            }
        }, 1000);
    };

    // Make sure the DOM is fully loaded before running detection
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initAdblockDetection, 1000);
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initAdblockDetection, 1000);
        });
    }
    
    // Add event listener for page load completion
    window.addEventListener('load', function() {
        setTimeout(initAdblockDetection, 2000);
        
        // Additional check after everything has loaded
        setTimeout(function() {
            const mainContainer = document.getElementById('ebay-listings');
            if (mainContainer) {
                const listingElements = mainContainer.querySelectorAll('.an-item, .an-auction, .item-card');
                if (!listingElements || listingElements.length === 0) {
                    console.log("No listings found after complete page load, checking eBay listings again");
                    window.checkEbayListings();
                }
            }
        }, 4000);
    });
})();