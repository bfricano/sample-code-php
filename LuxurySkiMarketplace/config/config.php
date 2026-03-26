<?php
/**
 * Kylan's Luxury Ski Marketplace - Configuration
 */

// Authorize.Net API credentials (sandbox/test)
define('AUTHORIZENET_API_LOGIN_ID', '556KThWQ6vf2');
define('AUTHORIZENET_TRANSACTION_KEY', '9ac2932kQ7kN2Wzq');
define('AUTHORIZENET_ENVIRONMENT', 'SANDBOX');

// Marketplace settings
define('MARKETPLACE_NAME', 'Kypre');
define('MARKETPLACE_TAGLINE', 'Luxury Women\'s Ski Fashion | Deer Valley & Beyond');
define('CURRENCY', 'USD');
define('TAX_RATE', 0.08);
define('SHIPPING_FLAT_RATE', 15.00);
define('FREE_SHIPPING_THRESHOLD', 500.00);

// Data storage paths
define('DATA_DIR', __DIR__ . '/../data');
define('PRODUCTS_FILE', DATA_DIR . '/products.json');
define('ORDERS_FILE', DATA_DIR . '/orders.json');
define('CART_SESSION_KEY', 'luxury_ski_cart');

// Marketplace categories
define('CATEGORIES', json_encode([
    'ski-collection'    => "Girlfriend's Ski Collection",
    'goldbergh'         => 'Goldbergh Luxury',
    'ski-jackets'       => 'Ski Jackets & Outerwear',
    'ski-pants'         => 'Ski Pants & Salopettes',
    'base-layers'       => 'Base Layers & Thermals',
    'accessories'       => 'Accessories & Gloves',
    'skis-equipment'    => 'Skis & Equipment',
    'deer-valley'       => 'Deer Valley Collection',
]));
