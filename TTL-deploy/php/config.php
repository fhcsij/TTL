<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

function ttl_db_config(): array
{
    $config = [
        'driver' => 'mysql',
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'twotimelight',
        'port' => 3306,
        'ssl' => false,
        'ssl_verify' => true,
        'ssl_ca' => '',
    ];

    $localConfigPath = __DIR__ . '/config.local.php';
    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (is_array($localConfig)) {
            $config = array_merge($config, $localConfig);
        }
    }

    $envAliases = [
        'driver' => ['DB_DRIVER', 'DATABASE_DRIVER'],
        'host' => ['DB_HOST', 'MYSQLHOST'],
        'username' => ['DB_USER', 'MYSQLUSER'],
        'password' => ['DB_PASSWORD', 'DB_PASS', 'MYSQLPASSWORD'],
        'database' => ['DB_NAME', 'MYSQLDATABASE'],
        'port' => ['DB_PORT', 'MYSQLPORT'],
        'ssl' => ['DB_SSL', 'MYSQL_SSL'],
        'ssl_verify' => ['DB_SSL_VERIFY', 'MYSQL_SSL_VERIFY'],
        'ssl_ca' => ['DB_SSL_CA', 'MYSQL_SSL_CA'],
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
    $config['ssl'] = ttl_config_bool($config['ssl']);
    $config['ssl_verify'] = ttl_config_bool($config['ssl_verify']);

    return $config;
}

function ttl_config_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on', 'required'], true);
}

class TTLPostgresResult
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;
    private int $index = 0;
    public int $num_rows = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): ?array
    {
        if ($this->index >= $this->num_rows) {
            return null;
        }

        return $this->rows[$this->index++];
    }

    public function fetch_all(int $mode = MYSQLI_ASSOC): array
    {
        return $this->rows;
    }
}

class TTLPostgresStatement
{
    private TTLPostgresConnection $connection;
    private ?PDOStatement $statement;
    /** @var array<int, mixed> */
    private array $boundParams = [];
    /** @var array<int, mixed> */
    private array $boundResultRefs = [];
    private ?TTLPostgresResult $result = null;
    public int $num_rows = 0;
    public int $affected_rows = 0;
    public int|string $insert_id = 0;
    public string $error = '';

    public function __construct(TTLPostgresConnection $connection, ?PDOStatement $statement, string $error = '')
    {
        $this->connection = $connection;
        $this->statement = $statement;
        $this->error = $error;
    }

    public function bind_param(string $types, mixed &...$params): bool
    {
        $this->boundParams = [];
        foreach ($params as &$param) {
            $this->boundParams[] = &$param;
        }

        return true;
    }

    public function execute(): bool
    {
        if (!$this->statement instanceof PDOStatement) {
            return false;
        }

        try {
            $values = [];
            foreach ($this->boundParams as &$param) {
                $values[] = $param;
            }

            $this->statement->execute($values);
            $this->affected_rows = $this->statement->rowCount();
            $this->connection->affected_rows = $this->affected_rows;

            if ($this->statement->columnCount() > 0) {
                $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);
                $this->result = new TTLPostgresResult($rows);
                $this->num_rows = $this->result->num_rows;

                if (isset($rows[0]['id'])) {
                    $this->insert_id = (int) $rows[0]['id'];
                    $this->connection->insert_id = $this->insert_id;
                }
            } else {
                $this->result = new TTLPostgresResult([]);
                $this->num_rows = 0;
            }

            return true;
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
            $this->connection->error = $this->error;
            return false;
        }
    }

    public function get_result(): TTLPostgresResult
    {
        return $this->result ?? new TTLPostgresResult([]);
    }

    public function store_result(): bool
    {
        return true;
    }

    public function bind_result(mixed &...$vars): bool
    {
        $this->boundResultRefs = [];
        foreach ($vars as &$var) {
            $this->boundResultRefs[] = &$var;
        }

        return true;
    }

    public function fetch(): bool
    {
        $row = $this->get_result()->fetch_assoc();
        if ($row === null) {
            return false;
        }

        $values = array_values($row);
        foreach ($this->boundResultRefs as $index => &$ref) {
            $ref = $values[$index] ?? null;
        }

        return true;
    }

    public function close(): bool
    {
        return true;
    }
}

class TTLPostgresConnection
{
    private PDO $pdo;
    private bool $productImagesReady = false;
    private bool $userImagesReady = false;
    public ?string $connect_error = null;
    public int $connect_errno = 0;
    public string $error = '';
    public int|string $insert_id = 0;
    public int $affected_rows = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function prepare(string $sql): TTLPostgresStatement
    {
        try {
            return new TTLPostgresStatement($this, $this->pdo->prepare(ttl_pg_normalize_sql($sql)));
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
            return new TTLPostgresStatement($this, null, $this->error);
        }
    }

    public function query(string $sql): TTLPostgresResult|bool
    {
        try {
            $statement = $this->pdo->query(ttl_pg_normalize_sql($sql));
            if (!$statement instanceof PDOStatement) {
                return false;
            }

            $this->affected_rows = $statement->rowCount();
            if ($statement->columnCount() === 0) {
                return true;
            }

            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if (isset($rows[0]['id'])) {
                $this->insert_id = (int) $rows[0]['id'];
            }

            return new TTLPostgresResult($rows);
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    public function begin_transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    public function set_charset(string $charset): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function storeProductImage(string $name, string $mime, string $base64Data): bool
    {
        $this->ensureProductImagesTable();
        $statement = $this->pdo->prepare(
            'INSERT INTO product_images (name, mime, data, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
             ON CONFLICT (name) DO UPDATE SET mime = EXCLUDED.mime, data = EXCLUDED.data, updated_at = CURRENT_TIMESTAMP'
        );

        return $statement->execute([$name, $mime, $base64Data]);
    }

    public function getProductImage(string $name): ?array
    {
        $this->ensureProductImagesTable();
        $statement = $this->pdo->prepare('SELECT mime, data FROM product_images WHERE name = ?');
        $statement->execute([$name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function storeUserImage(string $name, string $mime, string $base64Data): bool
    {
        $this->ensureUserImagesTable();
        $statement = $this->pdo->prepare(
            'INSERT INTO user_images (name, mime, data, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
             ON CONFLICT (name) DO UPDATE SET mime = EXCLUDED.mime, data = EXCLUDED.data, updated_at = CURRENT_TIMESTAMP'
        );

        return $statement->execute([$name, $mime, $base64Data]);
    }

    public function getUserImage(string $name): ?array
    {
        $this->ensureUserImagesTable();
        $statement = $this->pdo->prepare('SELECT mime, data FROM user_images WHERE name = ?');
        $statement->execute([$name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function ensureProductImagesTable(): void
    {
        if ($this->productImagesReady) {
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS product_images (
                name varchar(255) PRIMARY KEY,
                mime varchar(100) NOT NULL,
                data text NOT NULL,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->productImagesReady = true;
    }

    private function ensureUserImagesTable(): void
    {
        if ($this->userImagesReady) {
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_images (
                name varchar(255) PRIMARY KEY,
                mime varchar(100) NOT NULL,
                data text NOT NULL,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->userImagesReady = true;
    }
}

class TTLPostgresSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private bool $ready = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function open(string $path, string $name): bool
    {
        $this->ensureTable();
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $this->ensureTable();
        $statement = $this->pdo->prepare('SELECT data FROM app_sessions WHERE id = ?');
        $statement->execute([$id]);
        $data = $statement->fetchColumn();

        return is_string($data) ? $data : '';
    }

    public function write(string $id, string $data): bool
    {
        $this->ensureTable();
        $statement = $this->pdo->prepare(
            'INSERT INTO app_sessions (id, data, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
             ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, updated_at = CURRENT_TIMESTAMP'
        );

        return $statement->execute([$id, $data]);
    }

    public function destroy(string $id): bool
    {
        $this->ensureTable();
        $statement = $this->pdo->prepare('DELETE FROM app_sessions WHERE id = ?');
        return $statement->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $this->ensureTable();
        $statement = $this->pdo->prepare(
            "DELETE FROM app_sessions WHERE updated_at < CURRENT_TIMESTAMP - (? * INTERVAL '1 second')"
        );
        $statement->execute([$max_lifetime]);

        return $statement->rowCount();
    }

    private function ensureTable(): void
    {
        if ($this->ready) {
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_sessions (
                id varchar(128) PRIMARY KEY,
                data text NOT NULL,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->ready = true;
    }
}

function ttl_pg_normalize_sql(string $sql): string
{
    $normalized = trim(str_replace('`', '', $sql));
    $normalized = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $normalized) ?? $normalized;

    if (preg_match('/^INSERT\s+INTO\s+\w+/i', $normalized) && !preg_match('/\bRETURNING\b/i', $normalized)) {
        $normalized .= ' RETURNING id';
    }

    if (preg_match('/^DELETE\s+FROM\s+/i', $normalized)) {
        $normalized = preg_replace('/\s+LIMIT\s+\d+\s*;?$/i', '', $normalized) ?? $normalized;
    }

    return $normalized;
}

function get_db_connection()
{
    $config = ttl_db_config();

    if (strtolower((string) $config['driver']) === 'pgsql') {
        return get_pg_connection($config);
    }

    $conn = mysqli_init();
    if (!$conn instanceof mysqli) {
        ttl_database_error_response();
    }

    $flags = 0;
    if ($config['ssl']) {
        $conn->ssl_set(
            null,
            null,
            $config['ssl_ca'] !== '' ? (string) $config['ssl_ca'] : null,
            null,
            null
        );
        $flags |= MYSQLI_CLIENT_SSL;

        if (!$config['ssl_verify'] && defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
            $flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        }
    }

    @$conn->real_connect(
        (string) $config['host'],
        (string) $config['username'],
        (string) $config['password'],
        (string) $config['database'],
        (int) $config['port'],
        null,
        $flags
    );

    if ($conn->connect_errno || $conn->connect_error) {
        ttl_database_error_response();
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}

function get_pg_connection(array $config): TTLPostgresConnection
{
    return new TTLPostgresConnection(ttl_pg_pdo($config));
}

function ttl_pg_pdo(array $config): PDO
{
    $sslMode = $config['ssl'] ? ($config['ssl_verify'] ? 'verify-full' : 'require') : 'prefer';
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        (string) $config['host'],
        (int) $config['port'],
        (string) $config['database'],
        $sslMode
    );

    if ((string) $config['ssl_ca'] !== '') {
        $dsn .= ';sslrootcert=' . (string) $config['ssl_ca'];
    }

    try {
        $pdo = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $exception) {
        ttl_database_error_response();
    }

    return $pdo;
}

function ttl_database_error_response(): void
{
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Check DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT, and DB_SSL.',
    ]);
    exit;
}

function ttl_asset_url(string $relativePath, bool $cacheBust = false): string
{
    $url = str_replace('\\', '/', ltrim($relativePath, '/'));
    if ($cacheBust) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 't=' . time();
    }

    return $url;
}

function ttl_public_file_exists(string $relativePath): bool
{
    return is_file(dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/')));
}

function ttl_product_image_url(?string $filename, bool $cacheBust = true): string
{
    $rawName = trim((string) $filename);
    if ($rawName === '') {
        return ttl_asset_url('Image/logo.png', $cacheBust);
    }

    if (preg_match('/^(https?:|data:|php\/product_image\.php)/i', $rawName)) {
        return ttl_asset_url($rawName, $cacheBust);
    }

    $name = basename(str_replace('\\', '/', $rawName));
    $relativePath = 'Image/uploads/products/' . $name;

    if (ttl_public_file_exists($relativePath)) {
        return ttl_asset_url($relativePath, $cacheBust);
    }

    return ttl_asset_url('php/product_image.php?name=' . rawurlencode($name), $cacheBust);
}

function ttl_avatar_filename(?string $filename): string
{
    $name = basename(str_replace('\\', '/', trim((string) $filename)));
    if ($name !== '' && ttl_public_file_exists('Image/uploads/' . $name)) {
        return $name;
    }

    return 'default.png';
}

function ttl_avatar_url(?string $filename, bool $cacheBust = false): string
{
    $rawName = trim((string) $filename);
    if ($rawName === '') {
        return ttl_asset_url('Image/uploads/default.png', $cacheBust);
    }

    if (preg_match('/^(https?:|data:|php\/avatar_image\.php)/i', $rawName)) {
        return ttl_asset_url($rawName, $cacheBust);
    }

    $name = basename(str_replace('\\', '/', $rawName));
    $relativePath = 'Image/uploads/' . $name;

    if ($name !== '' && ttl_public_file_exists($relativePath)) {
        return ttl_asset_url($relativePath, $cacheBust);
    }

    return ttl_asset_url('php/avatar_image.php?name=' . rawurlencode($name), $cacheBust);
}

function ttl_product_upload_error(?array $file): ?string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'Image upload failed.';
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return 'Unsupported image type.';
    }

    return null;
}

function ttl_store_product_upload($conn, ?array $file, string $prefix): array
{
    $error = ttl_product_upload_error($file);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $filename = uniqid($prefix, true) . '.' . $extension;

    if ($conn instanceof TTLPostgresConnection) {
        $data = file_get_contents((string) $file['tmp_name']);
        if ($data === false) {
            return ['success' => false, 'message' => 'Unable to read uploaded image.'];
        }

        $mime = mime_content_type((string) $file['tmp_name']) ?: 'application/octet-stream';
        if (!$conn->storeProductImage($filename, $mime, base64_encode($data))) {
            return ['success' => false, 'message' => 'Unable to save uploaded image.'];
        }

        return ['success' => true, 'filename' => $filename];
    }

    $uploadDir = dirname(__DIR__) . '/Image/uploads/products';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        return ['success' => false, 'message' => 'Unable to create upload directory.'];
    }

    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Unable to move uploaded image.'];
    }

    return ['success' => true, 'filename' => $filename];
}

function ttl_store_avatar_upload($conn, ?array $file, int|string $userId): array
{
    $error = ttl_product_upload_error($file);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $safeUserId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $userId);
    $filename = 'avatar_' . ($safeUserId !== '' ? $safeUserId : uniqid('', false)) . '.' . $extension;

    if ($conn instanceof TTLPostgresConnection) {
        $data = file_get_contents((string) $file['tmp_name']);
        if ($data === false) {
            return ['success' => false, 'message' => 'Unable to read uploaded avatar.'];
        }

        $mime = mime_content_type((string) $file['tmp_name']) ?: 'application/octet-stream';
        if (!$conn->storeUserImage($filename, $mime, base64_encode($data))) {
            return ['success' => false, 'message' => 'Unable to save uploaded avatar.'];
        }

        return ['success' => true, 'filename' => $filename];
    }

    $uploadDir = dirname(__DIR__) . '/Image/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        return ['success' => false, 'message' => 'Unable to create upload directory.'];
    }

    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Unable to move uploaded avatar.'];
    }

    return ['success' => true, 'filename' => $filename];
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

    $config = ttl_db_config();
    if (strtolower((string) $config['driver']) === 'pgsql') {
        try {
            session_set_save_handler(new TTLPostgresSessionHandler(ttl_pg_pdo($config)), true);
            return;
        } catch (Throwable $exception) {
            ttl_database_error_response();
        }
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
