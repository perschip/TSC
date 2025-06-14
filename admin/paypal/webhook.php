<?php
// admin/paypal/webhook.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Log webhook events for debugging
function logWebhookEvent($event_type, $data, $status = 'received') {
    $log_file = __DIR__ . '/webhook_logs.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$status}] {$event_type}: " . json_encode($data) . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Get PayPal settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM paypal_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    logWebhookEvent('ERROR', ['message' => 'Failed to load settings: ' . $e->getMessage()], 'error');
    http_response_code(500);
    exit;
}

// Receive webhook payload
$payload = file_get_contents('php://input');
$headers = getallheaders();

// Verify webhook signature (in a production environment)
// This would require the PayPal SDK to properly verify the signature
// For now, we'll just log the event and process it

// Parse the payload
$event_json = json_decode($payload, true);

if (!$event_json || !isset($event_json['event_type'])) {
    logWebhookEvent('INVALID_PAYLOAD', ['payload' => $payload], 'error');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

// Log the event
$event_type = $event_json['event_type'];
logWebhookEvent($event_type, $event_json);

// Process different event types
switch ($event_type) {
    case 'CHECKOUT.ORDER.APPROVED':
        processOrderApproved($event_json, $pdo);
        break;
        
    case 'PAYMENT.CAPTURE.COMPLETED':
        processPaymentCompleted($event_json, $pdo);
        break;
        
    case 'PAYMENT.CAPTURE.REFUNDED':
        processPaymentRefunded($event_json, $pdo);
        break;
        
    default:
        // Just log other event types
        logWebhookEvent($event_type, $event_json, 'unprocessed');
        break;
}

// Return success response
http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Webhook received']);
exit;

/**
 * Process an approved order
 */
function processOrderApproved($event_data, $pdo) {
    try {
        if (!isset($event_data['resource']['id'])) {
            logWebhookEvent('MISSING_ORDER_ID', $event_data, 'error');
            return;
        }
        
        $paypal_order_id = $event_data['resource']['id'];
        
        // Check if order already exists
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE paypal_order_id = :paypal_order_id");
        $stmt->execute([':paypal_order_id' => $paypal_order_id]);
        
        if ($stmt->rowCount() > 0) {
            logWebhookEvent('ORDER_ALREADY_EXISTS', ['paypal_order_id' => $paypal_order_id], 'skipped');
            return;
        }
        
        // Extract order details
        $resource = $event_data['resource'];
        $payer = $resource['payer'] ?? [];
        $purchase_units = $resource['purchase_units'] ?? [];
        
        if (empty($purchase_units)) {
            logWebhookEvent('MISSING_PURCHASE_UNITS', $event_data, 'error');
            return;
        }
        
        $purchase_unit = $purchase_units[0];
        $shipping = $purchase_unit['shipping'] ?? [];
        $amount = $purchase_unit['amount'] ?? [];
        
        // Create order reference
        $order_reference = 'TSC-' . date('Ymd') . '-' . substr(uniqid(), -6);
        
        // Prepare customer info
        $customer_name = ($payer['name']['given_name'] ?? '') . ' ' . ($payer['name']['surname'] ?? '');
        $customer_email = $payer['email_address'] ?? '';
        
        // Prepare shipping address
        $shipping_address = '';
        if (!empty($shipping['address'])) {
            $address = $shipping['address'];
            $shipping_address = json_encode([
                'address_line_1' => $address['address_line_1'] ?? '',
                'address_line_2' => $address['address_line_2'] ?? '',
                'admin_area_2' => $address['admin_area_2'] ?? '', // City
                'admin_area_1' => $address['admin_area_1'] ?? '', // State
                'postal_code' => $address['postal_code'] ?? '',
                'country_code' => $address['country_code'] ?? ''
            ]);
        }
        
        // Prepare amount details
        $total_amount = $amount['value'] ?? 0;
        $shipping_cost = $amount['breakdown']['shipping']['value'] ?? 0;
        $tax_amount = $amount['breakdown']['tax_total']['value'] ?? 0;
        
        // Create order record
        $stmt = $pdo->prepare("INSERT INTO orders (
            order_reference, paypal_order_id, paypal_payer_id, 
            customer_name, customer_email, shipping_address,
            shipping_cost, tax_amount, total_amount, status, payment_status
        ) VALUES (
            :order_reference, :paypal_order_id, :paypal_payer_id,
            :customer_name, :customer_email, :shipping_address,
            :shipping_cost, :tax_amount, :total_amount, 'pending', 'pending'
        )");
        
        $stmt->execute([
            ':order_reference' => $order_reference,
            ':paypal_order_id' => $paypal_order_id,
            ':paypal_payer_id' => $payer['payer_id'] ?? '',
            ':customer_name' => $customer_name,
            ':customer_email' => $customer_email,
            ':shipping_address' => $shipping_address,
            ':shipping_cost' => $shipping_cost,
            ':tax_amount' => $tax_amount,
            ':total_amount' => $total_amount
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Log success
        logWebhookEvent('ORDER_CREATED', [
            'order_id' => $order_id,
            'order_reference' => $order_reference,
            'paypal_order_id' => $paypal_order_id
        ], 'success');
        
    } catch (Exception $e) {
        logWebhookEvent('ORDER_PROCESSING_ERROR', ['message' => $e->getMessage()], 'error');
    }
}

/**
 * Process a completed payment
 */
function processPaymentCompleted($event_data, $pdo) {
    try {
        if (!isset($event_data['resource']['id'])) {
            logWebhookEvent('MISSING_PAYMENT_ID', $event_data, 'error');
            return;
        }
        
        $payment_id = $event_data['resource']['id'];
        $resource = $event_data['resource'];
        
        // Get the order ID from the payment
        $paypal_order_id = $resource['supplementary_data']['related_ids']['order_id'] ?? 
                          ($resource['links'][1]['href'] ?? '');
        
        if (empty($paypal_order_id)) {
            // Try to extract from custom field or links
            if (preg_match('/\/([A-Z0-9]+)$/', $paypal_order_id, $matches)) {
                $paypal_order_id = $matches[1];
            } else {
                logWebhookEvent('CANNOT_DETERMINE_ORDER_ID', $event_data, 'error');
                return;
            }
        }
        
        // Update the order status
        $stmt = $pdo->prepare("UPDATE orders SET 
            payment_status = 'completed', 
            status = 'processing',
            updated_at = CURRENT_TIMESTAMP
            WHERE paypal_order_id = :paypal_order_id");
            
        $stmt->execute([':paypal_order_id' => $paypal_order_id]);
        
        if ($stmt->rowCount() === 0) {
            logWebhookEvent('ORDER_NOT_FOUND', ['paypal_order_id' => $paypal_order_id], 'error');
            return;
        }
        
        // Log success
        logWebhookEvent('PAYMENT_COMPLETED', [
            'payment_id' => $payment_id,
            'paypal_order_id' => $paypal_order_id
        ], 'success');
        
    } catch (Exception $e) {
        logWebhookEvent('PAYMENT_PROCESSING_ERROR', ['message' => $e->getMessage()], 'error');
    }
}

/**
 * Process a refunded payment
 */
function processPaymentRefunded($event_data, $pdo) {
    try {
        if (!isset($event_data['resource']['id'])) {
            logWebhookEvent('MISSING_REFUND_ID', $event_data, 'error');
            return;
        }
        
        $refund_id = $event_data['resource']['id'];
        $resource = $event_data['resource'];
        
        // Get the order ID from the refund
        $paypal_order_id = $resource['supplementary_data']['related_ids']['order_id'] ?? '';
        
        if (empty($paypal_order_id)) {
            // Try to extract from links
            $links = $resource['links'] ?? [];
            foreach ($links as $link) {
                if ($link['rel'] === 'up' && preg_match('/\/([A-Z0-9]+)$/', $link['href'], $matches)) {
                    $paypal_order_id = $matches[1];
                    break;
                }
            }
            
            if (empty($paypal_order_id)) {
                logWebhookEvent('CANNOT_DETERMINE_ORDER_ID_FOR_REFUND', $event_data, 'error');
                return;
            }
        }
        
        // Update the order status
        $stmt = $pdo->prepare("UPDATE orders SET 
            payment_status = 'refunded', 
            status = 'refunded',
            updated_at = CURRENT_TIMESTAMP,
            notes = CONCAT(IFNULL(notes, ''), '\nRefund processed: " . date('Y-m-d H:i:s') . " - Refund ID: " . $refund_id . "')
            WHERE paypal_order_id = :paypal_order_id");
            
        $stmt->execute([':paypal_order_id' => $paypal_order_id]);
        
        if ($stmt->rowCount() === 0) {
            logWebhookEvent('ORDER_NOT_FOUND_FOR_REFUND', ['paypal_order_id' => $paypal_order_id], 'error');
            return;
        }
        
        // Log success
        logWebhookEvent('PAYMENT_REFUNDED', [
            'refund_id' => $refund_id,
            'paypal_order_id' => $paypal_order_id
        ], 'success');
        
    } catch (Exception $e) {
        logWebhookEvent('REFUND_PROCESSING_ERROR', ['message' => $e->getMessage()], 'error');
    }
}
?>
