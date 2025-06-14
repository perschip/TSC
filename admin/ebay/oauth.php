<?php
// admin/ebay/oauth.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Get credentials
$appId = getSetting('ebay_app_id');
$certId = getSetting('ebay_cert_id');
$ruName = getSetting('ebay_ru_name');
$sandbox = (bool)getSetting('ebay_sandbox_mode');

// Validate credentials
if (empty($appId) || empty($certId) || empty($ruName)) {
    $_SESSION['error_message'] = 'Please configure your eBay API credentials first.';
    header('Location: settings.php');
    exit;
}

// Generate state parameter
$state = bin2hex(random_bytes(16));
$_SESSION['ebay_oauth_state'] = $state;

// Build auth URL
$baseUrl = 'https://auth.ebay.com/oauth2/authorize';
$params = [
    'client_id' => $appId,
    'response_type' => 'code',
    'redirect_uri' => $ruName,
    'scope' => implode(' ', [
        'https://api.ebay.com/oauth/api_scope',
        'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly',
        'https://api.ebay.com/oauth/api_scope/sell.marketing',
        'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
        'https://api.ebay.com/oauth/api_scope/sell.inventory',
        'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
        'https://api.ebay.com/oauth/api_scope/sell.account',
        'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
        'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
        'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
        'https://api.ebay.com/oauth/api_scope/sell.finances',
        'https://api.ebay.com/oauth/api_scope/sell.payment.dispute',
        'https://api.ebay.com/oauth/api_scope/commerce.identity.readonly',
        'https://api.ebay.com/oauth/api_scope/sell.reputation',
        'https://api.ebay.com/oauth/api_scope/sell.reputation.readonly',
        'https://api.ebay.com/oauth/api_scope/commerce.notification.subscription',
        'https://api.ebay.com/oauth/api_scope/commerce.notification.subscription.readonly',
        'https://api.ebay.com/oauth/api_scope/sell.stores',
        'https://api.ebay.com/oauth/api_scope/sell.stores.readonly',
        'https://api.ebay.com/oauth/scope/sell.edelivery'
    ]),
    'state' => $state
];

$authUrl = $baseUrl . '?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;
