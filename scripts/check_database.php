<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../TTL-deploy/php/config.php';
$config = ttl_db_config();
if ($config['driver'] !== 'mysql') {
    fwrite(STDERR, "TiDB requires DB_DRIVER=mysql.\n");
    exit(1);
}
$connection = get_db_connection();
$result = $connection->query('SELECT TABLE_NAME AS name FROM information_schema.tables
    WHERE TABLE_SCHEMA = DATABASE()');
if ($result === false) {
    fwrite(STDERR, "Unable to inspect database tables.\n");
    exit(1);
}
$tables = array_column($result->fetch_all(MYSQLI_ASSOC), 'name');
$required = ['users', 'categories', 'products', 'cart', 'orders', 'order_items', 'order_complaints'];
$missing = array_values(array_diff($required, $tables));
echo json_encode([
    'connected' => true,
    'driver' => 'mysql',
    'missing_tables' => $missing,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($missing === [] ? 0 : 1);
