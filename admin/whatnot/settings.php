<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Function to remove emojis and special characters
function removeEmojis($text) {
    // Pattern to match emojis and other special characters
    $pattern = '/[\x{1F600}-\x{1F64F}|\x{1F300}-\x{1F5FF}|\x{1F680}-\x{1F6FF}|\x{2600}-\x{26FF}|\x{2700}-\x{27BF}]/u';
    return preg_replace($pattern, '', $text);
}

// Check if API key is being saved
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_api_settings') {
    $whatnot_username = trim($_POST['whatnot_username']);
    $whatnot_api_key = trim($_POST['whatnot_api_key']);
    $whatnot_api_secret = trim($_POST['whatnot_api_secret']);
    $auto_check_interval = (int)$_POST['auto_check_interval'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($whatnot_username)) {
        $errors[] = 'Whatnot username is required';
    }
    
    // Save settings if no errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Check if settings exist
            $check_query = "SELECT COUNT(*) as count FROM site_settings WHERE setting_key IN ('whatnot_username', 'whatnot_api_key', 'whatnot_api_secret', 'whatnot_check_interval')";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->execute();
            $settings_exist = $check_stmt->fetch()['count'] > 0;
            
            // Define settings to update
            $settings = [
                'whatnot_username' => $whatnot_username,
                'whatnot_api_key' => $whatnot_api_key,
                'whatnot_api_secret' => $whatnot_api_secret,
                'whatnot_check_interval' => $auto_check_interval
            ];
            
            foreach ($settings as $key => $value) {
                if ($settings_exist) {
                    // Update existing setting
                    $update_query = "UPDATE site_settings SET setting_value = :value WHERE setting_key = :key";
                    $update_stmt = $pdo->prepare($update_query);
                    $update_stmt->bindParam(':key', $key);
                    $update_stmt->bindParam(':value', $value);
                    $update_stmt->execute();
                } else {
                    // Insert new setting
                    $insert_query = "INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)";
                    $insert_stmt = $pdo->prepare($insert_query);
                    $insert_stmt->bindParam(':key', $key);
                    $insert_stmt->bindParam(':value', $value);
                    $insert_stmt->execute();
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $_SESSION['success_message'] = 'Whatnot API settings saved successfully!';
            
            // Redirect to refresh the page
            header('Location: settings.php');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Check if manual stream update is being saved
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stream') {
    // Clean input data - remove emojis and special characters
    $is_live = isset($_POST['is_live']) && $_POST['is_live'] === '1' ? 1 : 0;
    $stream_title = removeEmojis(trim($_POST['stream_title'])); // Clean emojis from title
    $stream_url = trim($_POST['stream_url']);
    $scheduled_time = !empty($_POST['scheduled_time']) ? $_POST['scheduled_time'] : null;
    
    // Validate inputs
    $errors = [];
    
    if (empty($stream_title)) {
        $errors[] = 'Stream title is required';
    }
    
    if ($is_live && empty($stream_url)) {
        $errors[] = 'Stream URL is required for live streams';
    }
    
    if (!$is_live && empty($scheduled_time)) {
        $errors[] = 'Scheduled time is required for upcoming streams';
    }
    
    // Save stream status if no errors
    if (empty($errors)) {
        try {
            // Insert stream status
            $query = "INSERT INTO whatnot_status (is_live, stream_title, stream_url, scheduled_time, last_checked) 
                      VALUES (:is_live, :stream_title, :stream_url, :scheduled_time, NOW())";
                      
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':is_live', $is_live, PDO::PARAM_INT);
            $stmt->bindParam(':stream_title', $stream_title);
            $stmt->bindParam(':stream_url', $stream_url);
            $stmt->bindParam(':scheduled_time', $scheduled_time);
            $stmt->execute();
            
            // Set success message
            $_SESSION['success_message'] = 'Stream status updated successfully!';
            
            // Redirect to refresh the page
            header('Location: settings.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Add a new stream to the schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_scheduled_stream') {
    // Clean input data - remove emojis and special characters
    $stream_title = removeEmojis(trim($_POST['schedule_stream_title'])); 
    $scheduled_time = !empty($_POST['schedule_time']) ? $_POST['schedule_time'] : null;
    
    // Validate inputs
    $errors = [];
    
    if (empty($stream_title)) {
        $errors[] = 'Stream title is required';
    }
    
    if (empty($scheduled_time)) {
        $errors[] = 'Scheduled time is required for upcoming streams';
    }
    
    // Save scheduled stream if no errors
    if (empty($errors)) {
        try {
            // Insert stream status (as not live)
            $query = "INSERT INTO whatnot_status (is_live, stream_title, stream_url, scheduled_time, last_checked) 
                      VALUES (0, :stream_title, '', :scheduled_time, NOW())";
                      
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':stream_title', $stream_title);
            $stmt->bindParam(':scheduled_time', $scheduled_time);
            $stmt->execute();
            
            // Set success message
            $_SESSION['success_message'] = 'New stream scheduled successfully!';
            
            // Redirect to refresh the page
            header('Location: settings.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Delete a scheduled stream
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_scheduled_stream') {
    $stream_id = (int)$_POST['stream_id'];
    
    try {
        // Delete the stream
        $query = "DELETE FROM whatnot_status WHERE id = :id AND is_live = 0";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id', $stream_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Set success message
        $_SESSION['success_message'] = 'Scheduled stream removed successfully!';
        
        // Redirect to refresh the page
        header('Location: settings.php');
        exit;
        
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Check if ending stream
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['end_stream'])) {
    try {
        // Get current status
        $status_query = "SELECT * FROM whatnot_status ORDER BY id DESC LIMIT 1";
        $status_stmt = $pdo->prepare($status_query);
        $status_stmt->execute();
        $currentStatus = $status_stmt->fetch();
        
        if ($currentStatus && $currentStatus['is_live']) {
            // Clean title from emojis
            $stream_title = removeEmojis($currentStatus['stream_title']);
            
            // Insert new status with is_live set to 0
            $query = "INSERT INTO whatnot_status (is_live, stream_title, stream_url, scheduled_time, last_checked) 
                      VALUES (0, :stream_title, '', NULL, NOW())";
                      
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':stream_title', $stream_title);
            $stmt->execute();
            
            // Set success message
            $_SESSION['success_message'] = 'Stream ended successfully!';
        }
        
        // Redirect to refresh the page
        header('Location: settings.php');
        exit;
        
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Get current Whatnot API settings
$settings_query = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('whatnot_username', 'whatnot_api_key', 'whatnot_api_secret', 'whatnot_check_interval')";
$settings_stmt = $pdo->prepare($settings_query);
$settings_stmt->execute();
$settings_result = $settings_stmt->fetchAll();

$settings = [];
foreach ($settings_result as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get current Whatnot status
try {
    $status_query = "SELECT * FROM whatnot_status ORDER BY id DESC LIMIT 1";
    $status_stmt = $pdo->prepare($status_query);
    $status_stmt->execute();
    $whatnot_status = $status_stmt->fetch();
} catch (PDOException $e) {
    // If table doesn't exist, set status to null
    $whatnot_status = null;
    $errors[] = 'Status table error: ' . $e->getMessage();
}

// Get all upcoming scheduled streams
try {
    $scheduled_query = "SELECT * FROM whatnot_status 
                       WHERE is_live = 0 
                       AND scheduled_time IS NOT NULL 
                       AND scheduled_time > NOW() 
                       ORDER BY scheduled_time ASC";
    $scheduled_stmt = $pdo->prepare($scheduled_query);
    $scheduled_stmt->execute();
    $scheduled_streams = $scheduled_stmt->fetchAll();
} catch (PDOException $e) {
    $scheduled_streams = [];
    $errors[] = 'Scheduled streams error: ' . $e->getMessage();
}

// Page variables
$page_title = 'Whatnot Integration Settings';

// Extra scripts
$extra_scripts = '
<script>
// Toggle fields based on stream status
document.addEventListener("DOMContentLoaded", function() {
    const streamStatus = document.querySelectorAll("input[name=\'is_live\']");
    const liveFields = document.querySelectorAll(".live-field");
    const upcomingFields = document.querySelectorAll(".upcoming-field");
    
    function toggleFields() {
        const isLive = document.querySelector("#status_live").checked;
        
        liveFields.forEach(field => {
            field.style.display = isLive ? "block" : "none";
        });
        
        upcomingFields.forEach(field => {
            field.style.display = isLive ? "none" : "block";
        });
    }
    
    // Set initial state
    toggleFields();
    
    // Add event listeners
    streamStatus.forEach(radio => {
        radio.addEventListener("change", toggleFields);
    });
    
    // Initialize date/time pickers
    const datetimeInputs = document.querySelectorAll("input[type=\'datetime-local\']");
    if (datetimeInputs.length > 0) {
        // Set min date to now
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, "0");
        const day = String(now.getDate()).padStart(2, "0");
        const hours = String(now.getHours()).padStart(2, "0");
        const minutes = String(now.getMinutes()).padStart(2, "0");
        
        const nowString = `${year}-${month}-${day}T${hours}:${minutes}`;
        
        datetimeInputs.forEach(input => {
            if (!input.value) {
                input.value = nowString;
            }
            input.min = nowString;
        });
    }
    
    // Confirm delete
    const deleteButtons = document.querySelectorAll(".delete-stream-btn");
    deleteButtons.forEach(button => {
        button.addEventListener("click", function(e) {
            if (!confirm("Are you sure you want to delete this scheduled stream?")) {
                e.preventDefault();
            }
        });
    });
});
</script>
';

// Include admin header
include_once '../includes/header.php';
?>

<?php if (isset($errors) && !empty($errors)): ?>
    <div class="alert alert-warning">
        <h5><i class="fas fa-exclamation-triangle me-2"></i> Important Information</h5>
        <p>Your database is not properly configured to handle emoji characters. Please avoid using emojis in your stream titles.</p>
        <p>To fix this permanently, update your database by running this SQL command:</p>
        <pre>ALTER TABLE whatnot_status CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</pre>
        <p>Or use our <a href="/emoji_converter.php" class="alert-link">emoji converter tool</a> to fix this issue automatically.</p>
    </div>
<?php endif; ?>

<!-- Current Status Card -->
<?php if ($whatnot_status): ?>
    <div class="card dashboard-card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4>
                        <?php if ($whatnot_status['is_live']): ?>
                            <span class="whatnot-status-indicator status-live"></span> LIVE NOW
                        <?php elseif ($whatnot_status['scheduled_time'] && strtotime($whatnot_status['scheduled_time']) > time()): ?>
                            <span class="whatnot-status-indicator status-upcoming"></span> UPCOMING STREAM
                        <?php else: ?>
                            <span class="whatnot-status-indicator status-offline"></span> OFFLINE
                        <?php endif; ?>
                    </h4>
                    
                    <?php if ($whatnot_status['is_live'] || ($whatnot_status['scheduled_time'] && strtotime($whatnot_status['scheduled_time']) > time())): ?>
                        <h5><?php echo htmlspecialchars($whatnot_status['stream_title']); ?></h5>
                        
                        <?php if ($whatnot_status['scheduled_time']): ?>
                            <p>Scheduled for: <?php echo date('F j, Y \a\t g:i A', strtotime($whatnot_status['scheduled_time'])); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No active or upcoming streams.</p>
                    <?php endif; ?>
                    
                    <div class="small text-muted">Last updated: <?php echo date('F j, Y \a\t g:i A', strtotime($whatnot_status['last_checked'])); ?></div>
                </div>
                
                <div class="col-md-4 text-end">
                    <?php if ($whatnot_status['is_live']): ?>
                        <a href="<?php echo htmlspecialchars($whatnot_status['stream_url']); ?>" class="btn btn-success mb-2" target="_blank">
                            <i class="fas fa-external-link-alt me-1"></i> View Stream
                        </a>
                        <form method="post" class="d-inline-block">
                            <button type="submit" name="end_stream" value="1" class="btn btn-outline-danger" 
                                    onclick="return confirm('Are you sure you want to end the current stream?');">
                                <i class="fas fa-stop-circle me-1"></i> End Stream
                            </button>
                        </form>
                    <?php elseif ($whatnot_status['scheduled_time'] && strtotime($whatnot_status['scheduled_time']) > time()): ?>
                        <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars(getSetting('whatnot_username', 'tristate_cards')); ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-external-link-alt me-1"></i> Whatnot Profile
                        </a>
                    <?php else: ?>
                        <a href="#update-stream" class="btn btn-primary" data-bs-toggle="collapse">
                            <i class="fas fa-plus me-1"></i> Schedule New Stream
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <!-- API Settings -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold">Whatnot API Settings</h5>
            </div>
            <div class="card-body">
                <form action="settings.php" method="post">
                    <input type="hidden" name="action" value="save_api_settings">
                    
                    <div class="mb-3">
                        <label for="whatnot_username" class="form-label">Whatnot Username *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="whatnot_username" name="whatnot_username" 
                                   value="<?php echo isset($settings['whatnot_username']) ? htmlspecialchars($settings['whatnot_username']) : ''; ?>" required>
                        </div>
                        <div class="form-text">Your Whatnot username (without the @ symbol)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="whatnot_api_key" class="form-label">API Key</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="text" class="form-control" id="whatnot_api_key" name="whatnot_api_key" 
                                   value="<?php echo isset($settings['whatnot_api_key']) ? htmlspecialchars($settings['whatnot_api_key']) : ''; ?>">
                        </div>
                        <div class="form-text">Get this from your Whatnot developer account</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="whatnot_api_secret" class="form-label">API Secret</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="whatnot_api_secret" name="whatnot_api_secret" 
                                   value="<?php echo isset($settings['whatnot_api_secret']) ? htmlspecialchars($settings['whatnot_api_secret']) : ''; ?>">
                        </div>
                        <div class="form-text">Keep this secret and secure</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="auto_check_interval" class="form-label">Auto-Check Interval (minutes)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-clock"></i></span>
                            <input type="number" class="form-control" id="auto_check_interval" name="auto_check_interval" min="5" 
                                   value="<?php echo isset($settings['whatnot_check_interval']) ? htmlspecialchars($settings['whatnot_check_interval']) : '15'; ?>">
                        </div>
                        <div class="form-text">How often to check for stream status updates (minimum 5 minutes)</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save API Settings
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Schedule Stream List -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Upcoming Scheduled Streams</h5>
                <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#scheduleStreamForm" aria-expanded="false">
                    <i class="fas fa-plus"></i> Add Stream
                </button>
            </div>
            <div class="card-body">
                <!-- Add New Stream Form (Collapsed by default) -->
                <div class="collapse mb-4" id="scheduleStreamForm">
                    <div class="card card-body bg-light">
                        <h6 class="card-title">Schedule New Stream</h6>
                        <form action="settings.php" method="post">
                            <input type="hidden" name="action" value="add_scheduled_stream">
                            
                            <div class="mb-3">
                                <label for="schedule_stream_title" class="form-label">Stream Title * <small class="text-danger">(no emojis please)</small></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                    <input type="text" class="form-control" id="schedule_stream_title" name="schedule_stream_title" required
                                           placeholder="e.g., Baseball Card Breaks - Topps Series 2">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="schedule_time" class="form-label">Scheduled Time *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="datetime-local" class="form-control" id="schedule_time" name="schedule_time" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-calendar-plus me-1"></i> Schedule Stream
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Stream List -->
                <?php if (empty($scheduled_streams)): ?>
                    <div class="text-center py-4">
                        <div class="text-muted mb-3"><i class="fas fa-calendar-times fa-3x"></i></div>
                        <p>No upcoming streams scheduled</p>
                        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#scheduleStreamForm" aria-expanded="false">
                            <i class="fas fa-plus me-1"></i> Schedule Your First Stream
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Stream Title</th>
                                    <th>Scheduled Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scheduled_streams as $stream): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stream['stream_title']); ?></td>
                                        <td><?php echo date('M j, Y - g:i A', strtotime($stream['scheduled_time'])); ?></td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="delete_scheduled_stream">
                                                <input type="hidden" name="stream_id" value="<?php echo $stream['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger delete-stream-btn">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <button class="btn btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#scheduleStreamForm" aria-expanded="false">
                            <i class="fas fa-plus me-1"></i> Add Another Stream
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <!-- Manual Stream Update -->
        <div class="card shadow mb-4 collapse" id="update-stream">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold">Update Stream Status</h5>
            </div>
            <div class="card-body">
                <form action="settings.php" method="post">
                    <input type="hidden" name="action" value="update_stream">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold d-block">Stream Status</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="is_live" id="status_live" value="1" <?php echo (isset($_POST['is_live']) && $_POST['is_live'] === '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status_live">
                                <span class="badge bg-success"><i class="fas fa-circle me-1"></i> Live Now</span>
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="is_live" id="status_upcoming" value="0" <?php echo (!isset($_POST['is_live']) || $_POST['is_live'] === '0') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status_upcoming">
                                <span class="badge bg-primary"><i class="fas fa-calendar me-1"></i> Upcoming/Offline</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="stream_title" class="form-label">Stream Title * <small class="text-danger">(no emojis please)</small></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-heading"></i></span>
                            <input type="text" class="form-control" id="stream_title" name="stream_title" 
                                   value="<?php echo isset($_POST['stream_title']) ? htmlspecialchars($_POST['stream_title']) : ''; ?>" required
                                   placeholder="e.g., Baseball Card Breaks - Topps Series 2">
                        </div>
                    </div>
                    
                    <div class="mb-3 live-field">
                        <label for="stream_url" class="form-label">Stream URL</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-link"></i></span>
                            <input type="url" class="form-control" id="stream_url" name="stream_url" 
                                   value="<?php echo isset($_POST['stream_url']) ? htmlspecialchars($_POST['stream_url']) : ''; ?>"
                                   placeholder="https://www.whatnot.com/live/[your-stream-id]">
                        </div>
                        <div class="form-text">The direct URL to your live stream</div>
                    </div>
                    
                    <div class="mb-3 upcoming-field">
                        <label for="scheduled_time" class="form-label">Scheduled Time</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            <input type="datetime-local" class="form-control" id="scheduled_time" name="scheduled_time" 
                                   value="<?php echo isset($_POST['scheduled_time']) ? htmlspecialchars($_POST['scheduled_time']) : ''; ?>">
                        </div>
                        <div class="form-text">When your stream is scheduled to begin</div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Update Stream Status
                    </button>
                    
                    <?php if ($whatnot_status && $whatnot_status['is_live']): ?>
                        <button type="submit" name="end_stream" value="1" class="btn btn-outline-danger ms-2" 
                                onclick="return confirm('Are you sure you want to end the current stream?');">
                            <i class="fas fa-stop-circle me-1"></i> End Current Stream
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- What is Whatnot Card (Moved here from left side) -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold">What is Whatnot?</h5>
            </div>
            <div class="card-body">
                <p><a href="https://www.whatnot.com" target="_blank">Whatnot</a> is a live streaming platform for collectors and enthusiasts to buy, sell, and interact with their communities.</p>
                
                <h6 class="mt-3 mb-2">Why integrate with your website?</h6>
                <ul>
                    <li>Automatically show when you're live streaming</li>
                    <li>Promote upcoming streams</li>
                    <li>Drive more viewers to your Whatnot streams</li>
                    <li>Track conversions from your website to Whatnot</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i> Don't have a Whatnot account? 
                    <a href="https://www.whatnot.com/signup" target="_blank" class="alert-link">Sign up here</a> 
                    to start streaming your cards and collectibles!
                </div>
            </div>
        </div>
        
        <!-- Analytics Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold">Whatnot Analytics</h5>
            </div>
            <div class="card-body">
                <?php
                // Get click statistics
                try {
                    // Last 30 days
                    $last30_query = "SELECT COUNT(*) as count FROM whatnot_clicks WHERE click_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    $last30_stmt = $pdo->prepare($last30_query);
                    $last30_stmt->execute();
                    $last30_clicks = $last30_stmt->fetch()['count'];
                    
                    // All time
                    $alltime_query = "SELECT COUNT(*) as count FROM whatnot_clicks";
                    $alltime_stmt = $pdo->prepare($alltime_query);
                    $alltime_stmt->execute();
                    $alltime_clicks = $alltime_stmt->fetch()['count'];
                    
                    // Recent streams
                    $streams_query = "SELECT stream_id, COUNT(*) as clicks 
                                    FROM whatnot_clicks 
                                    WHERE stream_id > 0 
                                    GROUP BY stream_id 
                                    ORDER BY clicks DESC 
                                    LIMIT 3";
                    $streams_stmt = $pdo->prepare($streams_query);
                    $streams_stmt->execute();
                    $top_streams = $streams_stmt->fetchAll();
                } catch (PDOException $e) {
                    $last30_clicks = 0;
                    $alltime_clicks = 0;
                    $top_streams = [];
                }
                ?>
                
                <div class="row mb-4">
                    <div class="col-6">
                        <div class="text-center">
                            <div class="stat-value text-primary"><?php echo number_format($last30_clicks); ?></div>
                            <div class="stat-label">Last 30 Days</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="stat-value text-success"><?php echo number_format($alltime_clicks); ?></div>
                            <div class="stat-label">All Time</div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($top_streams)): ?>
                    <h6 class="fw-bold mb-3">Top Performing Streams</h6>
                    <ul class="list-group">
                        <?php foreach ($top_streams as $index => $stream): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Stream #<?php echo htmlspecialchars($stream['stream_id']); ?>
                                <span class="badge bg-primary rounded-pill"><?php echo number_format($stream['clicks']); ?> clicks</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="/admin/analytics/dashboard.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-chart-bar me-1"></i> View Full Analytics
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Tips Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold">Stream Success Tips</h5>
            </div>
            <div class="card-body">
                <div class="tip-list">
                    <div class="d-flex mb-3">
                        <div class="me-3 text-primary">
                            <i class="fas fa-calendar-alt fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Consistency is Key</h6>
                            <p class="mb-0 small">Stream on a regular schedule so your audience knows when to tune in.</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-3">
                        <div class="me-3 text-primary">
                            <i class="fas fa-bullhorn fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Promote in Advance</h6>
                            <p class="mb-0 small">Announce your streams at least 24-48 hours in advance across all platforms.</p>
                        </div>
                    </div>
                    
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-comments fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Engage Your Audience</h6>
                            <p class="mb-0 small">Respond to comments, ask questions, and create an interactive experience.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Help section -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h5 class="mb-0 fw-bold">Need Help?</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-bold">Common Questions</h6>
                <div class="accordion accordion-flush" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faqOne">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                How does the Whatnot integration work?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="faqOne" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                The integration displays your live stream status on your website automatically. When you're live, visitors can click through to watch. You can also promote upcoming streams and track click-through rates.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faqTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                Why can't I use emojis in my stream title?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="faqTwo" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Your database needs to be configured to handle emojis (UTF-8MB4 encoding). You can use our <a href="/emoji_converter.php">emoji converter tool</a> to fix this issue automatically. After running the tool, you'll be able to use emojis in your stream titles.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faqThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                How do I get API credentials from Whatnot?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="faqThree" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Currently, this feature is for manual updates only. The Whatnot API is not yet publicly available for most sellers. You can still use this integration by manually updating your stream status when you go live.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold">Troubleshooting Tips</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex">
                        <div class="me-3 text-danger">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div>
                            If your stream isn't showing on the homepage, try refreshing your cache or checking your stream status here.
                        </div>
                    </li>
                    <li class="list-group-item d-flex">
                        <div class="me-3 text-danger">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div>
                            Make sure your Whatnot username is entered correctly (case sensitive without the @ symbol).
                        </div>
                    </li>
                    <li class="list-group-item d-flex">
                        <div class="me-3 text-danger">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div>
                            If you're having database errors, contact your website administrator or use the <a href="/emergency_access.php">emergency admin access</a> tool.
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>