<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Define the base upload directory
$base_upload_dir = '../../uploads/ebay/';
$base_web_path = '/uploads/ebay/';

// Initialize variables
$message = '';
$message_type = '';
$uploaded_files = [];
$current_folder = '';
$parent_folder = '';

// Handle folder navigation
if (isset($_GET['folder']) && !empty($_GET['folder'])) {
    $current_folder = trim($_GET['folder'], '/');
    
    // Security check - prevent directory traversal
    if (strpos($current_folder, '..') !== false) {
        $current_folder = '';
    } else {
        // Create parent folder path for navigation
        $folder_parts = explode('/', $current_folder);
        if (count($folder_parts) > 1) {
            array_pop($folder_parts);
            $parent_folder = implode('/', $folder_parts);
        }
    }
}

// Current upload directory and web path
$upload_dir = $base_upload_dir . $current_folder . '/';
$web_path = $base_web_path . $current_folder . '/';

// Handle folder creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    if (isset($_POST['folder_name']) && !empty($_POST['folder_name'])) {
        $folder_name = preg_replace('/[^a-zA-Z0-9\-\_]/', '_', $_POST['folder_name']);
        $new_folder_path = $upload_dir . $folder_name;
        
        if (!file_exists($new_folder_path)) {
            if (mkdir($new_folder_path, 0755, true)) {
                $message = "Folder '$folder_name' created successfully.";
                $message_type = 'success';
            } else {
                $message = "Failed to create folder '$folder_name'.";
                $message_type = 'danger';
            }
        } else {
            $message = "Folder '$folder_name' already exists.";
            $message_type = 'warning';
        }
    } else {
        $message = "Please enter a folder name.";
        $message_type = 'danger';
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images']) && isset($_POST['action']) && $_POST['action'] === 'upload_images') {
    // Create the directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Loop through each uploaded file
    $files = $_FILES['images'];
    $file_count = count($files['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] === 0) {
            $file_name = $files['name'][$i];
            $file_tmp = $files['tmp_name'][$i];
            $file_size = $files['size'][$i];
            $file_type = $files['type'][$i];
            
            // Generate a unique filename to prevent overwriting
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $unique_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\-\.]/', '_', $file_name);
            
            // Check if file is an image
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file_type, $allowed_types)) {
                $message .= "File '$file_name' is not an allowed image type. Only JPG, PNG, GIF, and WEBP are allowed.<br>";
                $message_type = 'danger';
                continue;
            }
            
            // Check file size (limit to 5MB)
            if ($file_size > 5 * 1024 * 1024) {
                $message .= "File '$file_name' exceeds the 5MB size limit.<br>";
                $message_type = 'danger';
                continue;
            }
            
            // Move the file to the upload directory
            $destination = $upload_dir . $unique_name;
            if (move_uploaded_file($file_tmp, $destination)) {
                // Add to the list of successfully uploaded files
                $uploaded_files[] = [
                    'original_name' => $file_name,
                    'unique_name' => $unique_name,
                    'size' => formatFileSize($file_size),
                    'url' => $web_path . $unique_name,
                    'full_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                                  "://$_SERVER[HTTP_HOST]" . $web_path . $unique_name
                ];
            } else {
                $message .= "Failed to upload file '$file_name'.<br>";
                $message_type = 'danger';
            }
        } else {
            $message .= "Error uploading file: " . getUploadErrorMessage($files['error'][$i]) . "<br>";
            $message_type = 'danger';
        }
    }
    
    if (count($uploaded_files) > 0) {
        $message = count($uploaded_files) . " file(s) uploaded successfully.";
        $message_type = 'success';
    }
}

// Handle file deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $file_to_delete = $_GET['delete'];
    
    // Security check - prevent directory traversal
    if (strpos($file_to_delete, '..') !== false) {
        $message = "Invalid file name.";
        $message_type = 'danger';
    } else {
        $file_path = $upload_dir . $file_to_delete;
        
        if (file_exists($file_path) && is_file($file_path)) {
            if (unlink($file_path)) {
                $message = "File deleted successfully.";
                $message_type = 'success';
            } else {
                $message = "Failed to delete file.";
                $message_type = 'danger';
            }
        } else {
            $message = "File not found.";
            $message_type = 'danger';
        }
    }
}

// Handle mass deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mass_delete') {
    if (isset($_POST['selected_files']) && !empty($_POST['selected_files'])) {
        $selected_files = json_decode($_POST['selected_files'], true);
        $deleted_count = 0;
        $failed_count = 0;
        
        foreach ($selected_files as $file) {
            // Security check - prevent directory traversal
            if (strpos($file, '..') !== false) {
                $failed_count++;
                continue;
            }
            
            $file_path = $upload_dir . $file;
            
            if (file_exists($file_path) && is_file($file_path)) {
                if (unlink($file_path)) {
                    $deleted_count++;
                } else {
                    $failed_count++;
                }
            } else {
                $failed_count++;
            }
        }
        
        if ($deleted_count > 0) {
            $message = "$deleted_count file(s) deleted successfully.";
            if ($failed_count > 0) {
                $message .= " $failed_count file(s) could not be deleted.";
            }
            $message_type = 'success';
        } else {
            $message = "No files were deleted. Please try again.";
            $message_type = 'danger';
        }
    } else {
        $message = "No files selected for deletion.";
        $message_type = 'warning';
    }
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

// Function to get upload error message
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}

// Get list of folders and files in the current directory
$folders = [];
$existing_images = [];

// Create the base directory if it doesn't exist
if (!file_exists($base_upload_dir)) {
    mkdir($base_upload_dir, 0755, true);
}

// Create the current directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Scan the directory
if (is_dir($upload_dir)) {
    $items = scandir($upload_dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $item_path = $upload_dir . $item;
        
        if (is_dir($item_path)) {
            // Count files in the folder
            $file_count = count(array_filter(scandir($item_path), function($file) use ($item_path) {
                return is_file($item_path . '/' . $file);
            })) - 2; // Subtract . and ..
            
            $folders[] = [
                'name' => $item,
                'path' => ($current_folder ? $current_folder . '/' : '') . $item,
                'file_count' => $file_count,
                'date' => date('Y-m-d H:i:s', filemtime($item_path))
            ];
        } else {
            // Get file information
            $file_size = filesize($item_path);
            $file_date = filemtime($item_path);
            
            $existing_images[] = [
                'name' => $item,
                'size' => formatFileSize($file_size),
                'date' => date('Y-m-d H:i:s', $file_date),
                'url' => $web_path . $item,
                'full_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                              "://$_SERVER[HTTP_HOST]" . $web_path . $item
            ];
        }
    }
    
    // Sort folders by name
    usort($folders, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    
    // Sort images by newest first
    usort($existing_images, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// Create breadcrumb navigation
$breadcrumbs = [];
$breadcrumbs[] = ['name' => 'Root', 'path' => ''];

if ($current_folder) {
    $path_parts = explode('/', $current_folder);
    $current_path = '';
    
    foreach ($path_parts as $part) {
        $current_path .= ($current_path ? '/' : '') . $part;
        $breadcrumbs[] = ['name' => $part, 'path' => $current_path];
    }
}

// Page variables
$page_title = 'eBay Image Uploader';
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
                <h1 class="h2">eBay Image Uploader</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/admin/ebay/csv_creator.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-file-csv me-1"></i> CSV Creator
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
            
            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <?php if ($index === count($breadcrumbs) - 1): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($crumb['name']); ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="?folder=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
            
            <div class="row mb-4">
                <!-- Create Folder Form -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-folder-plus me-2"></i>Create New Folder</h5>
                        </div>
                        <div class="card-body">
                            <form action="?folder=<?php echo urlencode($current_folder); ?>" method="post">
                                <input type="hidden" name="action" value="create_folder">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="folder_name" placeholder="Enter folder name" required>
                                    <button type="submit" class="btn btn-primary">Create Folder</button>
                                </div>
                                <small class="form-text text-muted">Create folders to organize your images (e.g., "Sports Cards", "Baseball", "Football", etc.)</small>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Upload Form -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload Images</h5>
                        </div>
                        <div class="card-body">
                            <form action="?folder=<?php echo urlencode($current_folder); ?>" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_images">
                                <div class="mb-3">
                                    <label for="images" class="form-label">Select Images</label>
                                    <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*" required>
                                    <small class="form-text text-muted">Allowed formats: JPG, PNG, GIF, WEBP. Max size: 5MB per file.</small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-1"></i> Upload Images
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Folders List -->
            <?php if (!empty($folders)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-folder me-2"></i>Folders</h5>
                </div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-3 g-4">
                        <?php foreach ($folders as $folder): ?>
                        <div class="col">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-folder me-2 text-warning"></i>
                                        <a href="?folder=<?php echo urlencode($folder['path']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($folder['name']); ?>
                                        </a>
                                    </h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <?php echo $folder['file_count']; ?> files<br>
                                            Created: <?php echo $folder['date']; ?>
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Images List -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-images me-2"></i>Images <?php echo $current_folder ? 'in ' . htmlspecialchars($current_folder) : ''; ?></h5>
                    <div>
                        <button type="button" id="mass-delete-btn" class="btn btn-danger btn-sm me-2" style="display: none;">
                            <i class="fas fa-trash me-1"></i> Delete Selected (<span id="selected-count">0</span>)
                        </button>
                        <span class="badge bg-primary"><?php echo count($existing_images); ?> images</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($existing_images)): ?>
                        <div class="alert alert-info">
                            No images found in this folder. Upload some images using the form above.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="images-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="select-all">
                                            </div>
                                        </th>
                                        <th>Preview</th>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Date</th>
                                        <th>URL</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($existing_images as $image): ?>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input file-checkbox" type="checkbox" value="<?php echo htmlspecialchars($image['name']); ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#previewModal" data-image="<?php echo htmlspecialchars($image['url']); ?>" class="preview-link">
                                                <img src="<?php echo htmlspecialchars($image['url']); ?>" alt="<?php echo htmlspecialchars($image['name']); ?>" class="img-thumbnail" style="max-height: 50px; max-width: 50px;">
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($image['name']); ?></td>
                                        <td><?php echo $image['size']; ?></td>
                                        <td><?php echo $image['date']; ?></td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control url-input" value="<?php echo htmlspecialchars($image['full_url']); ?>" readonly>
                                                <button class="btn btn-outline-secondary copy-btn" type="button" data-clipboard-text="<?php echo htmlspecialchars($image['full_url']); ?>" data-bs-toggle="tooltip" title="Copy URL">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-primary preview-btn" data-bs-toggle="modal" data-bs-target="#previewModal" data-image="<?php echo htmlspecialchars($image['url']); ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="?folder=<?php echo urlencode($current_folder); ?>&delete=<?php echo urlencode($image['name']); ?>" class="btn btn-danger delete-btn" onclick="return confirm('Are you sure you want to delete this image?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Mass Delete Form -->
            <form id="mass-delete-form" action="?folder=<?php echo urlencode($current_folder); ?>" method="post" style="display: none;">
                <input type="hidden" name="action" value="mass_delete">
                <input type="hidden" name="selected_files" id="selected-files-input">
            </form>

            <!-- Confirm Mass Delete Modal -->
            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete <span id="delete-count">0</span> selected image(s)?</p>
                            <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirm-delete-btn">
                                <i class="fas fa-trash me-1"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Image Preview Modal -->
            <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="previewModalLabel">Image Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="" id="preview-image" class="img-fluid" alt="Preview">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="copy-preview-url">
                                <i class="fas fa-copy me-1"></i> Copy URL
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- eBay Image Guide -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>eBay Image Guidelines</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb me-2"></i>Tips for eBay Images:</h6>
                        <ul>
                            <li>eBay recommends images be at least 500 pixels on the longest side</li>
                            <li>Use a white or neutral background for best results</li>
                            <li>Ensure good lighting to show accurate colors</li>
                            <li>For multiple images in a listing, separate URLs with the | character</li>
                            <li>You can include up to 12 images per listing</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.8/dist/clipboard.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize mass delete functionality
    var selectedFiles = [];
    var selectAllCheckbox = document.getElementById('select-all');
    var fileCheckboxes = document.querySelectorAll('.file-checkbox');
    var massDeleteBtn = document.getElementById('mass-delete-btn');
    var selectedCountSpan = document.getElementById('selected-count');
    var deleteCountSpan = document.getElementById('delete-count');
    var confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    var massDeleteForm = document.getElementById('mass-delete-form');
    var selectedFilesInput = document.getElementById('selected-files-input');
    
    // Handle select all checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            var isChecked = this.checked;
            
            fileCheckboxes.forEach(function(checkbox) {
                checkbox.checked = isChecked;
            });
            
            updateSelectedFiles();
        });
    }
    
    // Handle individual file checkboxes
    fileCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            updateSelectedFiles();
            
            // Update select all checkbox state
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            } else {
                // Check if all checkboxes are checked
                var allChecked = true;
                fileCheckboxes.forEach(function(cb) {
                    if (!cb.checked) {
                        allChecked = false;
                    }
                });
                
                selectAllCheckbox.checked = allChecked;
            }
        });
    });
    
    // Update selected files array and UI
    function updateSelectedFiles() {
        selectedFiles = [];
        
        fileCheckboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                selectedFiles.push(checkbox.value);
            }
        });
        
        // Update UI
        selectedCountSpan.textContent = selectedFiles.length;
        deleteCountSpan.textContent = selectedFiles.length;
        
        if (selectedFiles.length > 0) {
            massDeleteBtn.style.display = 'inline-block';
        } else {
            massDeleteBtn.style.display = 'none';
        }
    }
    
    // Handle mass delete button click
    if (massDeleteBtn) {
        massDeleteBtn.addEventListener('click', function() {
            if (selectedFiles.length > 0) {
                var confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
                confirmDeleteModal.show();
            }
        });
    }
    
    // Handle confirm delete button click
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (selectedFiles.length > 0) {
                selectedFilesInput.value = JSON.stringify(selectedFiles);
                massDeleteForm.submit();
            }
        });
    }
    
    // Initialize clipboard.js
    var clipboard = new ClipboardJS('.copy-btn');
    
    clipboard.on('success', function(e) {
        // Show tooltip with success message
        var tooltip = bootstrap.Tooltip.getInstance(e.trigger);
        if (tooltip) {
            tooltip.dispose();
        }
        
        // Create a new tooltip
        var newTooltip = new bootstrap.Tooltip(e.trigger, {
            title: 'Copied!',
            trigger: 'manual'
        });
        
        // Show the tooltip
        newTooltip.show();
        
        // Hide the tooltip after 1 second
        setTimeout(function() {
            newTooltip.hide();
        }, 1000);
        
        e.clearSelection();
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle image preview
    document.querySelectorAll('.preview-btn, .preview-link').forEach(function(element) {
        element.addEventListener('click', function() {
            var imageUrl = this.getAttribute('data-image');
            document.getElementById('preview-image').src = imageUrl;
            document.getElementById('copy-preview-url').setAttribute('data-clipboard-text', window.location.protocol + '//' + window.location.host + imageUrl);
        });
    });
    
    // Initialize copy button for preview modal
    var previewClipboard = new ClipboardJS('#copy-preview-url');
    
    previewClipboard.on('success', function(e) {
        var button = e.trigger;
        var originalText = button.innerHTML;
        
        button.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
        
        setTimeout(function() {
            button.innerHTML = originalText;
        }, 1000);
        
        e.clearSelection();
    });
    
    // Initialize DataTable
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#images-table').DataTable({
            "pageLength": 25,
            "order": [[3, "desc"]], // Sort by date column descending
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]]
        });
    }
});
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
