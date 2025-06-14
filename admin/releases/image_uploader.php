<?php
// Product Release Calendar - Image Uploader
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/releases/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$message = '';
$image_url = '';

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    
    // Check for errors
    if ($file['error'] === 0) {
        $filename = $file['name'];
        $tmp_name = $file['tmp_name'];
        $file_size = $file['size'];
        $file_type = $file['type'];
        
        // Get file extension
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Check if file is an image
        if (in_array($file_ext, $allowed_exts)) {
            // Generate unique filename
            $new_filename = uniqid('release_') . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($tmp_name, $destination)) {
                // Get the URL
                $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                $relative_path = '/uploads/releases/' . $new_filename;
                $image_url = $site_url . $relative_path;
                
                $message = '<div class="alert alert-success">Image uploaded successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Failed to move uploaded file.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Invalid file type. Allowed types: ' . implode(', ', $allowed_exts) . '</div>';
        }
    } else {
        $error_messages = [
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk',
            8 => 'A PHP extension stopped the file upload'
        ];
        
        $error_code = $file['error'];
        $error_message = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Unknown error';
        
        $message = '<div class="alert alert-danger">Upload error: ' . $error_message . '</div>';
    }
}

// Simplified header for popup
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Release Image Uploader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fc;
        }
        .upload-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .image-preview {
            width: 100%;
            height: 200px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .no-image {
            color: #aaa;
            text-align: center;
        }
        .url-display {
            word-break: break-all;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <h2 class="mb-4 text-primary">Product Release Image Uploader</h2>
        
        <?php echo $message; ?>
        
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="image-preview mb-3" id="imagePreview">
                    <?php if (!empty($image_url)): ?>
                        <img src="<?php echo htmlspecialchars($image_url); ?>" alt="Uploaded Image">
                    <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-image fa-3x mb-2"></i>
                            <p>Image preview will appear here</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="post" enctype="multipart/form-data" class="mb-4">
                    <div class="mb-3">
                        <label for="image" class="form-label">Select Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                        <div class="form-text">Max file size: 2MB. Supported formats: JPG, PNG, GIF, WEBP</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i> Upload Image
                    </button>
                </form>
                
                <?php if (!empty($image_url)): ?>
                    <div class="mb-3">
                        <label class="form-label">Image URL</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="imageUrl" value="<?php echo htmlspecialchars($image_url); ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" id="copyBtn">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" id="useImageBtn">
                            <i class="fas fa-check me-1"></i> Use This Image
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">
                <i class="fas fa-times me-1"></i> Close Window
            </button>
        </div>
    </div>
    
    <script>
        // Preview image before upload
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Image Preview">`;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Copy URL to clipboard
        document.getElementById('copyBtn')?.addEventListener('click', function() {
            const imageUrl = document.getElementById('imageUrl');
            imageUrl.select();
            document.execCommand('copy');
            
            // Show feedback
            this.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-copy"></i>';
            }, 2000);
        });
        
        // Use image in parent window
        document.getElementById('useImageBtn')?.addEventListener('click', function() {
            const imageUrl = document.getElementById('imageUrl').value;
            if (window.opener && !window.opener.closed) {
                if (typeof window.opener.receiveImageUrl === 'function') {
                    window.opener.receiveImageUrl(imageUrl);
                    window.close();
                } else {
                    alert('The parent window does not have a receiveImageUrl function.');
                }
            } else {
                alert('The parent window is not available.');
            }
        });
    </script>
</body>
</html>
