<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Define directories
$templates_dir = '../assets/templates/';
$exports_dir = '../../exports/';
$images_dir = '../../uploads/ebay/';

// Create exports directory if it doesn't exist
if (!file_exists($exports_dir)) {
    mkdir($exports_dir, 0755, true);
}

// Initialize variables
$message = '';
$message_type = '';
$template_data = [];
$available_templates = [
    'default' => 'Default eBay Template',
    'sports_cards' => 'Sports Cards Template',
    'pokemon_cards' => 'Pokémon Cards Template'
];

// Handle template loading
if (isset($_POST['action']) && $_POST['action'] === 'load_template') {
    if (isset($_POST['template']) && !empty($_POST['template'])) {
        $template_name = $_POST['template'];
        $template_file = $templates_dir . $template_name . '_template.csv';
        
        if (file_exists($template_file)) {
            // Read the CSV file
            $file = fopen($template_file, 'r');
            if ($file) {
                // Get headers with comma delimiter for input
                $headers = fgetcsv($file, 0, ",");
                
                // Get sample data if available with comma delimiter for input
                $sample = fgetcsv($file, 0, ",");
                
                fclose($file);
                
                // Store template data
                $template_data = [
                    'name' => $available_templates[$template_name],
                    'headers' => $headers,
                    'sample' => $sample
                ];
            } else {
                $message = "Failed to open template file.";
                $message_type = 'danger';
            }
        } else {
            $message = "Template file not found.";
            $message_type = 'danger';
        }
    } else {
        $message = "No template selected.";
        $message_type = 'danger';
    }
}

// Handle CSV generation
if (isset($_POST['action']) && $_POST['action'] === 'generate_csv') {
    if (isset($_POST['csv_data']) && !empty($_POST['csv_data'])) {
        $csv_data = json_decode($_POST['csv_data'], true);
        
        if (is_array($csv_data) && isset($csv_data['headers']) && isset($csv_data['rows'])) {
            // Generate a unique filename
            $filename = 'ebay_listings_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = $exports_dir . $filename;
            
            // Create CSV file
            $fp = fopen($filepath, 'w');
            
            // Write the required 4 header lines
            fwrite($fp, "#INFO\tVersion=0.0.2\tTemplate= eBay-draft-listings-template_US\n");
            fwrite($fp, "#INFO Action and Category ID are required fields. 1) Set Action to Draft 2) Please find the category ID for your listings here: https://pages.ebay.com/sellerinformation/news/categorychanges.html\n");
            fwrite($fp, "#INFO After you've successfully uploaded your draft from the Seller Hub Reports tab, complete your drafts to active listings here: https://www.ebay.com/sh/lst/drafts\n");
            fwrite($fp, "#INFO\n");
            
            // Write the exact header format required by eBay
            fwrite($fp, "Action(SiteID=US|Country=US|Currency=USD|Version=1193|CC=UTF-8)\tCustom label (SKU)\tCategory ID\tTitle\tUPC\tPrice\tQuantity\tItem photo URL\tCondition ID\tDescription\tFormat\tShipping service\tShipping service cost\tShipping service additional cost\tShipping service option\tShipping service priority\n");
            
            // Write rows with tab delimiter
            foreach ($csv_data['rows'] as $row) {
                $row_array = [];
                foreach ($csv_data['headers'] as $header) {
                    $row_array[] = isset($row[$header]) ? $row[$header] : '';
                }
                fputcsv($fp, $row_array, "\t", '"', '\\');
            }
            
            fclose($fp);
            
            $download_url = '/exports/' . $filename;
            $message = "CSV file generated successfully. <a href='$download_url' class='alert-link' download>Download CSV</a>";
            $message_type = 'success';
        } else {
            $message = "Invalid CSV data format.";
            $message_type = 'danger';
        }
    } else {
        $message = "No CSV data provided.";
        $message_type = 'danger';
    }
}

// Get list of available images for the image selector
$images = [];
if (file_exists($images_dir) && is_dir($images_dir)) {
    $files = scandir($images_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($images_dir . $file)) {
            $file_path = $images_dir . $file;
            $file_size = filesize($file_path);
            $file_date = filemtime($file_path);
            
            $images[] = [
                'name' => $file,
                'size' => formatFileSize($file_size),
                'date' => date('Y-m-d H:i:s', $file_date),
                'url' => '/uploads/ebay/' . $file,
                'full_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                              "://$_SERVER[HTTP_HOST]/uploads/ebay/" . $file
            ];
        }
    }
    
    // Sort by newest first
    usort($images, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// Function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Page variables
$page_title = 'eBay CSV Creator';
$use_datatables = true;

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">eBay CSV Creator</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/admin/ebay/image_uploader.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-images me-1"></i> Image Uploader
                    </a>
                    <a href="/admin/ebay/settings.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-cog me-1"></i> eBay Settings
                    </a>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Template Selection Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>Select Template</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Choose a template to start creating your eBay CSV file. We have specialized templates for different types of listings.</p>
                    
                    <form id="load-template-form" action="" method="post">
                        <input type="hidden" name="action" value="load_template">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label for="template-select" class="form-label">Template</label>
                                <select class="form-select" id="template-select" name="template" required>
                                    <option value="">-- Select a template --</option>
                                    <?php foreach ($available_templates as $key => $name): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check me-1"></i> Load Template
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- CSV Editor Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>CSV Editor</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($template_data)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6>Template: <strong><?php echo htmlspecialchars($template_data['name']); ?></strong></h6>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#editorModal" class="btn btn-primary">
                            <i class="fas fa-expand me-1"></i> Open Full-Screen Editor
                        </a>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Click the button above to open the full-screen editor for a better editing experience.
                    </div>
                    <form id="generate-csv-form" action="" method="post">
                        <input type="hidden" name="action" value="generate_csv">
                        <input type="hidden" name="csv_data" id="csv-data-input">
                    </form>
                    <?php else: ?>
                    <div class="alert alert-info">
                        Please select a template to start creating your CSV file.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Full Screen Editor Modal -->
            <?php if (!empty($template_data)): ?>
            <div class="modal fade" id="editorModal" tabindex="-1" aria-labelledby="editorModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title" id="editorModalLabel">
                                <i class="fas fa-edit me-2"></i>Editing: <?php echo htmlspecialchars($template_data['name']); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="container-fluid p-3">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4>eBay CSV Editor</h4>
                                        <p class="text-muted">Create your eBay listings with the form below. Click "Generate CSV" when you're done.</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button type="button" onclick="addNewRow()" class="btn btn-success me-2">
                                            <i class="fas fa-plus me-1"></i> Add Row
                                        </button>
                                        <button type="button" onclick="generateCSVFile()" class="btn btn-primary">
                                            <i class="fas fa-file-download me-1"></i> Generate CSV
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered" id="csv-editor-table">
                                        <thead class="table-dark">
                                            <tr>
                                                <?php foreach ($template_data['headers'] as $header): ?>
                                                <th><?php echo htmlspecialchars($header); ?></th>
                                                <?php endforeach; ?>
                                                <th style="width: 100px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="csv-table-body">
                                            <?php if (isset($template_data['sample'])): ?>
                                            <tr class="csv-row" data-row="0">
                                                <?php foreach ($template_data['headers'] as $index => $header): ?>
                                                <td>
                                                    <?php if ($header === 'Item photo URL'): ?>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="<?php echo isset($template_data['sample'][$index]) ? htmlspecialchars($template_data['sample'][$index]) : ''; ?>">
                                                        <button class="btn btn-outline-secondary select-image-btn" type="button" onclick="openImageSelector(this)">
                                                            <i class="fas fa-image"></i>
                                                        </button>
                                                    </div>
                                                    <?php else: ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="<?php echo isset($template_data['sample'][$index]) ? htmlspecialchars($template_data['sample'][$index]) : ''; ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <?php endforeach; ?>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger remove-row-btn" onclick="removeRow(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <tr class="csv-row" data-row="0">
                                                <?php foreach ($template_data['headers'] as $header): ?>
                                                <td>
                                                    <?php if ($header === 'Item photo URL'): ?>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="">
                                                        <button class="btn btn-outline-secondary select-image-btn" type="button" onclick="openImageSelector(this)">
                                                            <i class="fas fa-image"></i>
                                                        </button>
                                                    </div>
                                                    <?php elseif ($header === 'Quantity'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="1">
                                                    <?php elseif ($header === 'Category ID' && $template_name === 'sports_cards'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="261328">
                                                    <?php elseif ($header === 'Category ID' && $template_name === 'pokemon_cards'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="183454">
                                                    <?php elseif ($header === 'Condition ID'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="3000">
                                                    <?php elseif ($header === 'Format'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="FixedPrice">
                                                    <?php elseif ($header === 'Shipping service'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="USPSGroundAdvantage">
                                                    <?php elseif ($header === 'Shipping service cost' || $header === 'Shipping service additional cost' || $header === 'Shipping service option'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="Calculated">
                                                    <?php elseif ($header === 'Shipping service priority'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="1">
                                                    <?php elseif ($header === 'Action(SiteID=US|Country=US|Currency=USD|Version=1193|CC=UTF-8)'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="Add">
                                                    <?php elseif ($header === 'Custom label (SKU)' && $template_name === 'sports_cards'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="TSC-">
                                                    <?php elseif ($header === 'Custom label (SKU)' && $template_name === 'pokemon_cards'): ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="TSC-PKM-">
                                                    <?php else: ?>
                                                    <input type="text" class="form-control form-control-sm csv-input" data-header="<?php echo htmlspecialchars($header); ?>" value="">
                                                    <?php endif; ?>
                                                </td>
                                                <?php endforeach; ?>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger remove-row-btn" onclick="removeRow(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" onclick="generateCSVFile()" class="btn btn-primary">
                                <i class="fas fa-file-download me-1"></i> Generate CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- eBay CSV Format Guide -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>eBay CSV Format Guide</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb me-2"></i>Important Fields for eBay CSV Files:</h6>
                        <ul>
                            <li><strong>Action:</strong> Add, Revise, Relist, End, etc.</li>
                            <li><strong>Custom label (SKU):</strong> Your internal reference number</li>
                            <li><strong>Category ID:</strong> eBay category number (e.g., 261328 for Sports Trading Cards)</li>
                            <li><strong>Title:</strong> Your listing title (80 characters max)</li>
                            <li><strong>UPC:</strong> Universal Product Code (if applicable)</li>
                            <li><strong>Price:</strong> Listing price</li>
                            <li><strong>Quantity:</strong> Number of items</li>
                            <li><strong>Item photo URL:</strong> URL to your item image(s)</li>
                            <li><strong>Condition ID:</strong> 1000=New, 3000=Used, etc.</li>
                            <li><strong>Description:</strong> Full item description</li>
                            <li><strong>Format:</strong> FixedPrice or Auction</li>
                        </ul>
                        <p><strong>Note:</strong> For multiple images, separate each URL with a vertical bar (|) character.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Image Selection Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Select Images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Select one or more images to include in your listing. Multiple images will be separated by the | character.</p>
                
                <?php if (empty($images)): ?>
                    <div class="alert alert-warning">
                        No images found. Please upload images using the <a href="/admin/ebay/image_uploader.php">Image Uploader</a> first.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="images-table">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Preview</th>
                                    <th>File Name</th>
                                    <th>URL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($images as $image): ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input image-checkbox" type="checkbox" value="<?php echo htmlspecialchars($image['full_url']); ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($image['url']); ?>" alt="<?php echo htmlspecialchars($image['name']); ?>" class="img-thumbnail" style="max-height: 50px; max-width: 50px;">
                                    </td>
                                    <td><?php echo htmlspecialchars($image['name']); ?></td>
                                    <td>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($image['full_url']); ?>" readonly>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="copyImageUrl('<?php echo htmlspecialchars($image['full_url']); ?>')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="selectImages()">Select Images</button>
            </div>
        </div>
    </div>
</div>

<!-- Success Alert Modal -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="alertModalLabel">Success</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="alertMessage">Operation completed successfully!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variable to store the current target input for image selection
var currentImageInput = null;

// Initialize when the document is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable for images
    if (typeof $.fn.DataTable !== 'undefined') {
        $("#images-table").DataTable({
            "pageLength": 10,
            "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]]
        });
    }
    
    // Initialize template select change
    var templateSelect = document.getElementById('template-select');
    if (templateSelect) {
        templateSelect.addEventListener('change', function() {
            if (this.value) {
                document.getElementById('load-template-form').submit();
            }
        });
    }
});

// Function to add a new row to the CSV table
function addNewRow() {
    // Get the first row as a template
    var firstRow = document.querySelector('.csv-row');
    if (!firstRow) return;
    
    // Clone the row
    var newRow = firstRow.cloneNode(true);
    
    // Get the current row count
    var rowCount = document.querySelectorAll('.csv-row').length;
    
    // Update the row attributes
    newRow.setAttribute('data-row', rowCount);
    
    // Clear input values
    var inputs = newRow.querySelectorAll('input');
    inputs.forEach(function(input) {
        if (input.getAttribute('data-header') === 'Quantity') {
            input.value = '1';
        } else {
            input.value = '';
        }
    });
    
    // Add the new row to the table
    document.getElementById('csv-table-body').appendChild(newRow);
    
    // Show success message
    showAlert('New row added successfully!');
}

// Function to remove a row
function removeRow(button) {
    var row = button.closest('tr');
    var rows = document.querySelectorAll('.csv-row');
    
    if (rows.length > 1) {
        row.remove();
        renumberRows();
        showAlert('Row removed successfully!');
    } else {
        alert('You must have at least one row.');
    }
}

// Function to renumber rows after deletion
function renumberRows() {
    var rows = document.querySelectorAll('.csv-row');
    rows.forEach(function(row, index) {
        row.setAttribute('data-row', index);
    });
}

// Function to open the image selector
function openImageSelector(button) {
    // Store the input field that will receive the selected image URLs
    currentImageInput = button.previousElementSibling;
    
    // Clear any previously selected checkboxes
    var checkboxes = document.querySelectorAll('.image-checkbox');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });
    
    // If the input already has values, check the corresponding checkboxes
    if (currentImageInput.value) {
        var urls = currentImageInput.value.split('|');
        urls.forEach(function(url) {
            checkboxes.forEach(function(checkbox) {
                if (checkbox.value === url.trim()) {
                    checkbox.checked = true;
                }
            });
        });
    }
    
    // Show the modal
    var imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
    imageModal.show();
}

// Function to select images from the modal
function selectImages() {
    if (!currentImageInput) return;
    
    var selectedUrls = [];
    var checkboxes = document.querySelectorAll('.image-checkbox:checked');
    
    checkboxes.forEach(function(checkbox) {
        selectedUrls.push(checkbox.value);
    });
    
    if (selectedUrls.length > 0) {
        currentImageInput.value = selectedUrls.join('|');
        
        // Hide the modal
        var imageModal = bootstrap.Modal.getInstance(document.getElementById('imageModal'));
        if (imageModal) {
            imageModal.hide();
        }
        
        // Show success message
        showAlert('Images selected successfully!');
    } else {
        alert('Please select at least one image.');
    }
}

// Function to copy an image URL
function copyImageUrl(url) {
    // Create a temporary input element
    var tempInput = document.createElement('input');
    tempInput.value = url;
    document.body.appendChild(tempInput);
    
    // Select and copy the text
    tempInput.select();
    document.execCommand('copy');
    
    // Remove the temporary element
    document.body.removeChild(tempInput);
    
    // Show success message
    showAlert('URL copied to clipboard!');
}

// Function to generate the CSV file
function generateCSVFile() {
    var csvData = {
        headers: [],
        rows: []
    };
    
    // Get headers
    var headerCells = document.querySelectorAll('#csv-editor-table thead th');
    headerCells.forEach(function(cell) {
        var headerText = cell.textContent.trim();
        if (headerText !== 'Actions') {
            csvData.headers.push(headerText);
        }
    });
    
    // Get row data
    var rows = document.querySelectorAll('.csv-row');
    rows.forEach(function(row) {
        var rowData = {};
        var inputs = row.querySelectorAll('.csv-input');
        inputs.forEach(function(input) {
            var header = input.getAttribute('data-header');
            var value = input.value;
            rowData[header] = value;
        });
        csvData.rows.push(rowData);
    });
    
    // Set the CSV data in the hidden input
    document.getElementById('csv-data-input').value = JSON.stringify(csvData);
    
    // Submit the form
    document.getElementById('generate-csv-form').submit();
}

// Function to show an alert message
function showAlert(message) {
    document.getElementById('alertMessage').textContent = message;
    var alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
    alertModal.show();
    
    // Auto-hide after 2 seconds
    setTimeout(function() {
        alertModal.hide();
    }, 2000);
}

// Initialize template select change
document.addEventListener('DOMContentLoaded', function() {
    var templateSelect = document.getElementById('template-select');
    if (templateSelect) {
        templateSelect.addEventListener('change', function() {
            if (this.value) {
                document.getElementById('load-template-form').submit();
            }
        });
    }
});
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
