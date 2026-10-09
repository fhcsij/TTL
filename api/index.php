<?php
// Keep the existing API URLs while deploying a single PHP function on Hobby.
$allowedEndpoints = [
    'add_product.php', 'add_to_cart.php', 'avatar_image.php',
    'cancel_complaint.php', 'checkout.php', 'clear_cart.php',
    'create_order.php', 'delete_product.php', 'donate_product.php',
    'edit_product.php', 'get_all_products.php', 'get_cart_items.php',
    'get_categories.php', 'get_donated_products.php', 'get_order_history.php',
    'get_orders_items.php', 'get_user_donated_products.php',
    'get_user_info.php', 'get_user_products.php', 'login.php',
    'product_image.php', 'register.php', 'remove_from_cart.php',
    'submit_complaint.php', 'upload_avatar.php',
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$endpoint = '';
if (is_string($path) && preg_match('#^/(?:php|api)/([a-z_]+\.php)$#', $path, $matches)) {
    $endpoint = $matches[1];
}
if ($endpoint === 'index.php') {
    $endpoint = $_GET['__ttl_endpoint'] ?? '';
}

if (!is_string($endpoint) || !in_array($endpoint, $allowedEndpoints, true)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'API endpoint not found.']);
    exit;
}

unset($_GET['__ttl_endpoint']);
require __DIR__ . '/../TTL-deploy/php/' . $endpoint;
