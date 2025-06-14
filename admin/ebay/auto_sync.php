<?php
/**
 * eBay Auto-Sync Script
 * This file is meant to be called by a cron job to automatically sync eBay listings
 * based on the configured interval
 */

// Set execution time limit to 10 minutes for large syncs
set_time_limit(600);

// Increase memory limit if needed
ini_set('memory_limit', '256M');

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'sync_helper.php';
require_once 'token_refresh.php';

// Check if auto-sync is enabled
$auto_sync_enabled = (bool)getSetting('ebay_auto_sync', 0);
if (!$auto_sync_enabled) {
    echo "Auto-sync is disabled. Exiting.\n";
    exit;
}

// Check if it's time to sync based on the interval and last sync time
$sync_interval = (int)getSetting('ebay_sync_interval', 60); // Default to 60 minutes
$last_sync = getSetting('ebay_last_sync', '');

if (!empty($last_sync)) {
    $last_sync_time = strtotime($last_sync);
    $time_since_last_sync = time() - $last_sync_time;
    $interval_seconds = $sync_interval * 60;
    
    if ($time_since_last_sync < $interval_seconds) {
        echo "Not time to sync yet. Last sync was " . round($time_since_last_sync / 60) . " minutes ago. Interval is set to $sync_interval minutes.\n";
        exit;
    }
}

// Get seller ID
$seller_id = getSetting('ebay_seller_id', '');
if (empty($seller_id)) {
    echo "eBay seller ID is not configured. Exiting.\n";
    exit;
}

// Log that we're starting the auto-sync
error_log("Starting eBay auto-sync at " . date('Y-m-d H:i:s'));

// Check if another sync is already running to prevent overlaps
$lock_file = sys_get_temp_dir() . '/ebay_sync_lock.tmp';
$running = false;

if (file_exists($lock_file)) {
    $lock_time = filemtime($lock_file);
    // If lock file is older than 30 minutes, it's probably a stale lock
    if (time() - $lock_time < 1800) {
        $running = true;
        error_log("eBay sync already running (lock file exists from " . date('Y-m-d H:i:s', $lock_time) . ")");
    } else {
        error_log("Removing stale eBay sync lock file from " . date('Y-m-d H:i:s', $lock_time));
        @unlink($lock_file);
    }
}

if ($running) {
    echo "Another sync process is already running. Exiting.\n";
    exit;
}

// Create lock file
file_put_contents($lock_file, date('Y-m-d H:i:s'));

try {
    // First ensure we have a valid token
    if (!ensureValidEbayAccessToken()) {
        error_log("eBay auto-sync: Attempting token refresh before sync");
        // Try with forced refresh
        if (!ensureValidEbayAccessToken(true)) {
            throw new Exception("Failed to refresh eBay access token before sync");
        }
    }
    
    // Run the smart sync
    $sync_result = smartEbaySync($pdo);
    
    if ($sync_result['success']) {
        echo "Auto-sync completed successfully: {$sync_result['updated']} updated, {$sync_result['new']} new, {$sync_result['unchanged']} unchanged, {$sync_result['preserved']} preserved\n";
        echo "Sync duration: {$sync_result['duration']} seconds\n";
        error_log("eBay auto-sync completed successfully: {$sync_result['message']}");
    } else {
        echo "Auto-sync failed: {$sync_result['error']}\n";
        error_log("eBay auto-sync failed: {$sync_result['error']}");
    }
} catch (Exception $e) {
    echo "Error during auto-sync: " . $e->getMessage() . "\n";
    error_log("Error during eBay auto-sync: " . $e->getMessage());
    
    // Notify admin about the error
    try {
        $pdo->prepare("INSERT INTO admin_notifications (title, message, type, is_read) VALUES (:title, :message, 'error', 0)")
            ->execute([
                'title' => 'eBay Sync Error',
                'message' => 'The automatic eBay sync failed: ' . $e->getMessage()
            ]);
    } catch (Exception $notifyError) {
        // Silent fail if notification can't be created
    }
} finally {
    // Always remove lock file when done
    @unlink($lock_file);
}
?>
