<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

function ttl_db_config(): array
{
    $config = [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'twotimelight',
        'port' => 3306,
    ];

    $localConfigPath = __DIR__ . '/config.local.php';
    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (is_array($localConfig)) {
            $config = array_merge($config, $localConfig);
        }
    }

    $envAliases = [
        'host' => ['DB_HOST', 'MYSQLHOST'],
        'username' => ['DB_USER', 'MYSQLUSER'],
        'password' => ['DB_PASSWORD', 'DB_PASS', 'MYSQLPASSWORD'],
        'database' => ['DB_NAME', 'MYSQLDATABASE'],
        'port' => ['DB_PORT', 'MYSQLPORT'],
    ];

    foreach ($envAliases as $key => $aliases) {
        foreach ($aliases as $alias) {
            $value = getenv($alias);
            if ($value !== false && $value !== '') {
                $config[$key] = $value;
                break;
            }
        }
    }

    $config['port'] = (int) $config['port'];

    return $config;
}

function get_db_connection(): mysqli
{
    $config = ttl_db_config();

    $conn = @new mysqli(
        (string) $config['host'],
        (string) $config['username'],
        (string) $config['password'],
        (string) $config['database'],
        (int) $config['port']
    );

    if ($conn->connect_errno || $conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed. Check DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, and DB_PORT.',
        ]);
        exit;
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}

function ttl_session_path_is_writable(string $savePath): bool
{
    if ($savePath === '') {
        return false;
    }

    $parts = explode(';', $savePath);
    $path = (string) end($parts);

    return is_dir($path) && is_writable($path);
}

function ttl_prepare_session_storage(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    if (ttl_session_path_is_writable((string) ini_get('session.save_path'))) {
        return;
    }

    $fallbackPath = dirname(__DIR__) . '/var/sessions';
    if (!is_dir($fallbackPath)) {
        @mkdir($fallbackPath, 0775, true);
    }

    if (is_dir($fallbackPath) && is_writable($fallbackPath)) {
        session_save_path($fallbackPath);
    }
}

ttl_prepare_session_storage();
