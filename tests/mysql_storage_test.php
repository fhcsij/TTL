<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Child processes use separate PHP sessions and separate database connections.
$mode = $argv[1] ?? '';
if (str_starts_with($mode, '--session-')) {
    require_once __DIR__ . '/../TTL-deploy/php/config.php';
    session_id('ttl-mysql-storage-test');
    if (!session_start()) {
        exit(1);
    }
    if ($mode === '--session-write') {
        $_SESSION = ['id' => 42, 'name' => '測試會員', 'role' => 'buyer', 'count' => 0];
    } elseif ($mode === '--session-increment') {
        $count = $_SESSION['count'];
        usleep(200000);
        $_SESSION['count'] = $count + 1;
    }
    $session = $_SESSION;
    session_write_close();
    echo json_encode($session, JSON_UNESCAPED_UNICODE);
    exit;
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function startWorker(string $mode): array
{
    $process = proc_open([PHP_BINARY, __FILE__, $mode], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes);
    check(is_resource($process), 'Unable to start session worker.');
    fclose($pipes[0]);
    return [$process, $pipes];
}

function finishWorker(array $worker): array
{
    [$process, $pipes] = $worker;
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    check(proc_close($process) === 0 && $error === '', 'Session worker failed: ' . $error);
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('TEST_DB_PORT') ?: 33417);
$user = getenv('TEST_DB_USER') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: '';
$database = 'ttl_storage_test_' . bin2hex(random_bytes(8));
$admin = new mysqli($host, $user, $password, '', $port);
$admin->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin");
$temporaryImage = null;

try {
    foreach (['DB_DRIVER' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => (string) $port,
        'DB_USER' => $user, 'DB_PASSWORD' => $password, 'DB_NAME' => $database,
        'DB_SSL' => 'false', 'DB_SESSION_STORAGE' => 'database'] as $key => $value) {
        putenv($key . '=' . $value);
    }
    require_once __DIR__ . '/../TTL-deploy/php/config.php';
    $connection = get_db_connection();
    check($connection instanceof TTLMySQLConnection, 'MySQL storage connection was not selected.');

    $binary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
    check(is_string($binary), 'Invalid image fixture.');
    $temporaryImage = tempnam(sys_get_temp_dir(), 'ttl-image-');
    file_put_contents($temporaryImage, $binary);
    $file = ['name' => '測試.png', 'tmp_name' => $temporaryImage, 'error' => UPLOAD_ERR_OK];
    $product = ttl_store_product_upload($connection, $file, 'test_');
    $avatar = ttl_store_avatar_upload($connection, $file, 42);
    check($product['success'] && $avatar['success'], 'Image upload storage failed.');

    $secondConnection = get_db_connection();
    foreach ([['getProductImage', $product['filename']], ['getUserImage', $avatar['filename']]] as [$method, $name]) {
        $image = $secondConnection->$method($name);
        check($image !== null && $image['mime'] === 'image/png'
            && base64_decode($image['data'], true) === $binary, 'Image was not preserved across connections.');
    }
    check($secondConnection->getProductImage('missing.png') === null, 'Missing image lookup failed.');
    check($connection->storeUserImage($avatar['filename'], 'image/webp', base64_encode('replacement')), 'Avatar replacement failed.');
    check($secondConnection->getUserImage($avatar['filename'])['data'] === base64_encode('replacement'), 'Avatar upsert did not replace the stored image.');
    check(str_starts_with(ttl_product_image_url($product['filename'], false), 'php/product_image.php?name='), 'Product URL did not use the shared image endpoint.');
    check(str_starts_with(ttl_avatar_url($avatar['filename']), 'php/avatar_image.php?name='), 'Avatar URL did not use the shared image endpoint.');

    finishWorker(startWorker('--session-write'));
    $session = finishWorker(startWorker('--session-read'));
    check($session['id'] === 42 && $session['name'] === '測試會員' && $session['role'] === 'buyer', 'Login state did not survive a separate PHP process.');
    $first = startWorker('--session-increment');
    $second = startWorker('--session-increment');
    finishWorker($first);
    finishWorker($second);
    check(finishWorker(startWorker('--session-read'))['count'] === 2, 'Concurrent session requests lost an update.');

    $handler = new TTLMySQLSessionHandler($secondConnection);
    check($handler->open('', 'PHPSESSID'), 'Session table initialization failed.');
    check($handler->write('old-test-session', 'expired'), 'Unable to prepare GC fixture.');
    $secondConnection->query("UPDATE app_sessions SET updated_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY)
        WHERE id = 'old-test-session'");
    check($handler->gc(1440) === 1, 'Session garbage collection did not remove the expired row.');
    check($handler->read('ttl-mysql-storage-test') !== '', 'Garbage collection removed the active session.');
    check($handler->destroy('ttl-mysql-storage-test') && $handler->close(), 'Session destruction failed.');
    check((int) $secondConnection->query("SELECT COUNT(*) AS count FROM app_sessions WHERE id = 'ttl-mysql-storage-test'")->fetch_assoc()['count'] === 0, 'Destroyed session was still stored.');

    echo "PASS: uploads, cross-connection image reads, avatar replacement, cross-process login, concurrent sessions, expiry and logout.\n";
} finally {
    if ($temporaryImage !== null && is_file($temporaryImage)) {
        unlink($temporaryImage);
    }
    // This name is generated by the test itself, never supplied by the environment.
    $admin->query("DROP DATABASE `$database`");
    $admin->close();
}
