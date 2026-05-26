<?php
require_once __DIR__ . '/config.php';

$name = basename(str_replace('\\', '/', (string) ($_GET['name'] ?? '')));
$fallbackPath = dirname(__DIR__) . '/Image/logo.png';

function ttl_send_image_file(string $path, string $fallbackMime = 'image/png'): void
{
    header('Content-Type: ' . (mime_content_type($path) ?: $fallbackMime));
    header('Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($name === '') {
    ttl_send_image_file($fallbackPath);
}

$localPath = dirname(__DIR__) . '/Image/uploads/products/' . $name;
if (is_file($localPath)) {
    ttl_send_image_file($localPath);
}

$conn = get_db_connection();
if ($conn instanceof TTLPostgresConnection) {
    $image = $conn->getProductImage($name);
    if ($image !== null && is_string($image['data'] ?? null)) {
        $binary = base64_decode((string) $image['data'], true);
        if ($binary !== false) {
            header('Content-Type: ' . ((string) ($image['mime'] ?? 'image/png')));
            header('Cache-Control: public, max-age=300');
            header('X-Content-Type-Options: nosniff');
            echo $binary;
            exit;
        }
    }
}

ttl_send_image_file($fallbackPath);
