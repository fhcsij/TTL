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

    return new TTLPostgresConnection($pdo);
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
