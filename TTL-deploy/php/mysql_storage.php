<?php
declare(strict_types=1);

/** Shared storage for MySQL-compatible databases, including TiDB Cloud Starter. */
class TTLMySQLConnection extends mysqli
{
    private array $imageTablesReady = [];

    public function storeProductImage(string $name, string $mime, string $base64Data): bool
    {
        return $this->storeImage('product_images', $name, $mime, $base64Data);
    }

    public function getProductImage(string $name): ?array
    {
        return $this->getImage('product_images', $name);
    }

    public function storeUserImage(string $name, string $mime, string $base64Data): bool
    {
        return $this->storeImage('user_images', $name, $mime, $base64Data);
    }

    public function getUserImage(string $name): ?array
    {
        return $this->getImage('user_images', $name);
    }

    private function ensureImageTable(string $table): bool
    {
        if (isset($this->imageTablesReady[$table])) {
            return true;
        }

        // Table names are private constants, never request input.
        if (!$this->query("CREATE TABLE IF NOT EXISTS `$table` (
            name varchar(255) COLLATE utf8mb4_bin PRIMARY KEY,
            mime varchar(100) NOT NULL,
            data mediumtext NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin")) {
            return false;
        }

        $this->imageTablesReady[$table] = true;
        return true;
    }

    private function storeImage(string $table, string $name, string $mime, string $data): bool
    {
        if (!$this->ensureImageTable($table)) {
            return false;
        }

        $statement = $this->prepare("INSERT INTO `$table` (name, mime, data, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE mime = VALUES(mime), data = VALUES(data),
                updated_at = CURRENT_TIMESTAMP");
        if ($statement === false) {
            return false;
        }
        $statement->bind_param('sss', $name, $mime, $data);
        $success = $statement->execute();
        $statement->close();
        return $success;
    }

    private function getImage(string $table, string $name): ?array
    {
        if (!$this->ensureImageTable($table)) {
            return null;
        }

        $statement = $this->prepare("SELECT mime, data FROM `$table` WHERE name = ?");
        if ($statement === false) {
            return null;
        }
        $statement->bind_param('s', $name);
        if (!$statement->execute()) {
            $statement->close();
            return null;
        }
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();
        return $row;
    }
}

/** Use a dedicated connection so session locks do not affect application transactions. */
class TTLMySQLSessionHandler implements SessionHandlerInterface
{
    private TTLMySQLConnection $connection;
    private bool $transactionOpen = false;

    public function __construct(TTLMySQLConnection $connection)
    {
        $this->connection = $connection;
    }

    public function open(string $path, string $name): bool
    {
        return (bool) $this->connection->query('CREATE TABLE IF NOT EXISTS app_sessions (
            id varchar(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
            data mediumblob NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX app_sessions_updated_at (updated_at)
        )');
    }

    public function close(): bool
    {
        if (!$this->transactionOpen) {
            return true;
        }

        $success = $this->connection->commit();
        $this->transactionOpen = false;
        return $success;
    }

    public function read(string $id): string|false
    {
        if (!$this->transactionOpen) {
            if (!$this->connection->begin_transaction()) {
                return false;
            }
            $this->transactionOpen = true;
        }

        // Create the row before locking it; this also serializes a new session's requests.
        $statement = $this->connection->prepare("INSERT INTO app_sessions (id, data)
            VALUES (?, '') ON DUPLICATE KEY UPDATE id = VALUES(id)");
        if ($statement === false) {
            return false;
        }
        $statement->bind_param('s', $id);
        $success = $statement->execute();
        $statement->close();
        if (!$success) {
            return false;
        }

        $statement = $this->connection->prepare('SELECT data FROM app_sessions WHERE id = ? FOR UPDATE');
        if ($statement === false) {
            return false;
        }
        $statement->bind_param('s', $id);
        $success = $statement->execute();
        $row = $success ? $statement->get_result()->fetch_assoc() : null;
        $statement->close();
        return $success ? (string) ($row['data'] ?? '') : false;
    }

    public function write(string $id, string $data): bool
    {
        $statement = $this->connection->prepare('INSERT INTO app_sessions (id, data, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = CURRENT_TIMESTAMP');
        if ($statement === false) {
            return false;
        }
        $statement->bind_param('ss', $id, $data);
        $success = $statement->execute();
        $statement->close();
        return $success;
    }

    public function destroy(string $id): bool
    {
        $statement = $this->connection->prepare('DELETE FROM app_sessions WHERE id = ?');
        if ($statement === false) {
            return false;
        }
        $statement->bind_param('s', $id);
        $success = $statement->execute();
        $statement->close();
        return $success;
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = $this->connection->prepare('DELETE FROM app_sessions
            WHERE updated_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? SECOND)');
        if ($statement === false) {
            return false;
        }
        $statement->bind_param('i', $max_lifetime);
        $success = $statement->execute();
        $count = $statement->affected_rows;
        $statement->close();
        return $success ? $count : false;
    }
}
