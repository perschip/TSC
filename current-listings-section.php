<?php
// Make sure $listings is defined
if (!isset($listings) || !is_array($listings)) {
    $listings = [];
}

if (empty($listings)): ?>
    <div class="listings-empty-state">
        <i class="fas fa-search"></i>
        <h4>No listings found</h4>
        <p>We couldn't find any listings matching your criteria.</p>
        <a href="index.php" class="btn btn-outline-primary">View all listings</a>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($listings as $listing): ?>
            <div class="col">
                <div class="card h-100 listing-card">
                    <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" target="_blank" class="card-link">
                        <div class="card-img-container">
                            <?php if (!empty($listing['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                            <?php else: ?>
                                <div class="text-center text-muted pt-4">
                                    <i class="fas fa-image fa-2x"></i>
                                    <p class="mt-1 small">No image</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div class="card-body pb-0">
                        <!-- Title container with fixed height -->
                        <div class="title-container bg-transparent" style="padding: 10px 0; margin: 5px 0;">
                            <h5 class="card-title">
                            <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" target="_blank" class="text-dark">
                                    <?php echo htmlspecialchars($listing['title']); ?>
                                </a>
                            </h5>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <!-- Price and category -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <p class="card-price mb-0 text-success fw-bold">$<?php echo number_format($listing['price'], 2); ?></p>
                            <?php if (!empty($listing['category'])): ?>
                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($listing['category']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Quantity and view button -->
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Qty: <?php echo $listing['quantity']; ?></small>
                            <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" class="btn btn-sm btn-primary" target="_blank">View on eBay</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
