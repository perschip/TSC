<?php
// admin/paypal/coupons.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Check if coupons table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'coupons'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error checking database tables: ' . $e->getMessage();
}

// Get coupons if table exists
$coupons = [];
if ($table_exists) {
    try {
        $stmt = $pdo->query("SELECT * FROM coupons ORDER BY id DESC");
        $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error fetching coupons: ' . $e->getMessage();
    }
}

// Set page title
$page_title = 'Coupon Codes';

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <?php if (!$table_exists): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i> Database Setup Required</h5>
                    <p>The coupon management requires database tables that have not been set up yet.</p>
                    <p>Please visit the <a href="setup_database.php">PayPal Database Setup</a> page to set up the database.</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-tags me-2"></i> Coupon Codes</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addCouponModal">
                                <i class="fas fa-plus me-1"></i> Add New Coupon
                            </button>
                            <a href="settings.php" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="fas fa-cog me-1"></i> Settings
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Code</th>
                                        <th>Type</th>
                                        <th>Value</th>
                                        <th>Min Purchase</th>
                                        <th>Usage</th>
                                        <th>Valid Period</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($coupons)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <p class="text-muted mb-0">No coupons found. Add your first coupon code.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($coupons as $coupon): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($coupon['id']); ?></td>
                                        <td><code><?php echo htmlspecialchars($coupon['code']); ?></code></td>
                                        <td>
                                            <?php if ($coupon['discount_type'] == 'percentage'): ?>
                                            <span class="badge bg-info">Percentage</span>
                                            <?php else: ?>
                                            <span class="badge bg-primary">Fixed Amount</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($coupon['discount_type'] == 'percentage'): ?>
                                            <?php echo htmlspecialchars($coupon['discount_value']); ?>%
                                            <?php else: ?>
                                            $<?php echo number_format($coupon['discount_value'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($coupon['min_purchase'] > 0): ?>
                                            $<?php echo number_format($coupon['min_purchase'], 2); ?>
                                            <?php else: ?>
                                            <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($coupon['max_uses'] !== null): ?>
                                            <?php echo htmlspecialchars($coupon['uses_count']); ?> / <?php echo htmlspecialchars($coupon['max_uses']); ?>
                                            <?php else: ?>
                                            <?php echo htmlspecialchars($coupon['uses_count']); ?> / ∞
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $start_date = $coupon['start_date'] ? date('M d, Y', strtotime($coupon['start_date'])) : 'Any time';
                                            $end_date = $coupon['end_date'] ? date('M d, Y', strtotime($coupon['end_date'])) : 'No expiry';
                                            echo $start_date . ' - ' . $end_date;
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($coupon['active']): ?>
                                            <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary edit-coupon" 
                                                    data-id="<?php echo $coupon['id']; ?>" 
                                                    data-code="<?php echo htmlspecialchars($coupon['code']); ?>" 
                                                    data-type="<?php echo htmlspecialchars($coupon['discount_type']); ?>" 
                                                    data-value="<?php echo htmlspecialchars($coupon['discount_value']); ?>" 
                                                    data-min="<?php echo htmlspecialchars($coupon['min_purchase']); ?>" 
                                                    data-max="<?php echo $coupon['max_uses'] !== null ? htmlspecialchars($coupon['max_uses']) : ''; ?>" 
                                                    data-start="<?php echo $coupon['start_date'] ? htmlspecialchars($coupon['start_date']) : ''; ?>" 
                                                    data-end="<?php echo $coupon['end_date'] ? htmlspecialchars($coupon['end_date']) : ''; ?>" 
                                                    data-active="<?php echo $coupon['active'] ? '1' : '0'; ?>" 
                                                    title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger delete-coupon" data-id="<?php echo $coupon['id']; ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Coupon Modal -->
<div class="modal fade" id="addCouponModal" tabindex="-1" aria-labelledby="addCouponModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCouponModalLabel">Add New Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="save_coupon.php" method="post">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="coupon_code" class="form-label">Coupon Code*</label>
                            <input type="text" class="form-control" id="coupon_code" name="coupon_code" required>
                            <div class="form-text">Enter a unique code for this coupon (e.g., SUMMER2025)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="discount_type" class="form-label">Discount Type*</label>
                            <select class="form-select" id="discount_type" name="discount_type" required>
                                <option value="percentage">Percentage Discount</option>
                                <option value="fixed">Fixed Amount Discount</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="discount_value" class="form-label">Discount Value*</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control" id="discount_value" name="discount_value" required>
                                <span class="input-group-text discount-symbol">%</span>
                            </div>
                            <div class="form-text">For percentage, enter a value between 0-100. For fixed amount, enter the dollar value.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="min_purchase" class="form-label">Minimum Purchase</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="min_purchase" name="min_purchase" value="0">
                            </div>
                            <div class="form-text">Minimum order value required to use this coupon. Set to 0 for no minimum.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="max_uses" class="form-label">Maximum Uses</label>
                            <input type="number" min="0" class="form-control" id="max_uses" name="max_uses">
                            <div class="form-text">Maximum number of times this coupon can be used. Leave blank for unlimited.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="active" name="active" value="1" checked>
                                <label class="form-check-label" for="active">Active</label>
                            </div>
                            <div class="form-text">Enable or disable this coupon.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date">
                            <div class="form-text">When this coupon becomes valid. Leave blank for immediate validity.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date">
                            <div class="form-text">When this coupon expires. Leave blank for no expiration.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Coupon Modal -->
<div class="modal fade" id="editCouponModal" tabindex="-1" aria-labelledby="editCouponModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCouponModalLabel">Edit Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="save_coupon.php" method="post">
                <input type="hidden" name="coupon_id" id="edit_coupon_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_coupon_code" class="form-label">Coupon Code*</label>
                            <input type="text" class="form-control" id="edit_coupon_code" name="coupon_code" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_discount_type" class="form-label">Discount Type*</label>
                            <select class="form-select" id="edit_discount_type" name="discount_type" required>
                                <option value="percentage">Percentage Discount</option>
                                <option value="fixed">Fixed Amount Discount</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_discount_value" class="form-label">Discount Value*</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control" id="edit_discount_value" name="discount_value" required>
                                <span class="input-group-text edit-discount-symbol">%</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_min_purchase" class="form-label">Minimum Purchase</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="edit_min_purchase" name="min_purchase">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_max_uses" class="form-label">Maximum Uses</label>
                            <input type="number" min="0" class="form-control" id="edit_max_uses" name="max_uses">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="edit_active" name="active" value="1">
                                <label class="form-check-label" for="edit_active">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="edit_start_date" name="start_date">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edit_end_date" name="end_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Coupon Modal -->
<div class="modal fade" id="deleteCouponModal" tabindex="-1" aria-labelledby="deleteCouponModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCouponModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this coupon? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteCouponForm" method="post" action="delete_coupon.php">
                    <input type="hidden" name="coupon_id" id="delete_coupon_id" value="">
                    <button type="submit" class="btn btn-danger">Delete Coupon</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle discount type change to update symbol
    const discountTypeSelect = document.getElementById('discount_type');
    const discountSymbol = document.querySelector('.discount-symbol');
    
    if (discountTypeSelect && discountSymbol) {
        discountTypeSelect.addEventListener('change', function() {
            discountSymbol.textContent = this.value === 'percentage' ? '%' : '$';
        });
    }
    
    // Handle edit discount type change
    const editDiscountTypeSelect = document.getElementById('edit_discount_type');
    const editDiscountSymbol = document.querySelector('.edit-discount-symbol');
    
    if (editDiscountTypeSelect && editDiscountSymbol) {
        editDiscountTypeSelect.addEventListener('change', function() {
            editDiscountSymbol.textContent = this.value === 'percentage' ? '%' : '$';
        });
    }
    
    // Handle edit coupon buttons
    const editButtons = document.querySelectorAll('.edit-coupon');
    const editModal = new bootstrap.Modal(document.getElementById('editCouponModal'));
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const code = this.getAttribute('data-code');
            const type = this.getAttribute('data-type');
            const value = this.getAttribute('data-value');
            const min = this.getAttribute('data-min');
            const max = this.getAttribute('data-max');
            const start = this.getAttribute('data-start');
            const end = this.getAttribute('data-end');
            const active = this.getAttribute('data-active') === '1';
            
            document.getElementById('edit_coupon_id').value = id;
            document.getElementById('edit_coupon_code').value = code;
            document.getElementById('edit_discount_type').value = type;
            document.getElementById('edit_discount_value').value = value;
            document.getElementById('edit_min_purchase').value = min;
            document.getElementById('edit_max_uses').value = max;
            document.getElementById('edit_start_date').value = start ? start.split(' ')[0] : '';
            document.getElementById('edit_end_date').value = end ? end.split(' ')[0] : '';
            document.getElementById('edit_active').checked = active;
            
            // Update the symbol
            document.querySelector('.edit-discount-symbol').textContent = type === 'percentage' ? '%' : '$';
            
            editModal.show();
        });
    });
    
    // Handle delete coupon buttons
    const deleteButtons = document.querySelectorAll('.delete-coupon');
    const deleteCouponIdInput = document.getElementById('delete_coupon_id');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteCouponModal'));
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const couponId = this.getAttribute('data-id');
            deleteCouponIdInput.value = couponId;
            deleteModal.show();
        });
    });
});
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>