<?php

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
}
if (!defined('MYSQLI_NUM')) {
    define('MYSQLI_NUM', 2);
}

class SQLiteCompatResult {
    private array $rows;
    private int $position = 0;
    public int $num_rows = 0;

    public function __construct(array $rows) {
        $this->rows = array_values($rows);
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc(): ?array {
        if ($this->position >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->position++];
    }

    public function fetch_row(): ?array {
        $assoc = $this->fetch_assoc();
        if ($assoc === null) {
            return null;
        }
        return array_values($assoc);
    }

    public function fetch_all(int $mode = MYSQLI_ASSOC): array {
        if ($mode === MYSQLI_NUM) {
            return array_map('array_values', $this->rows);
        }
        return $this->rows;
    }

    public function free(): void {
        $this->rows = [];
        $this->num_rows = 0;
        $this->position = 0;
    }
}

class SQLiteCompatStatement {
    private SQLiteCompatConnection $connection;
    private PDOStatement $statement;
    private string $types = '';
    private array $boundValues = [];
    public string $error = '';
    public int $affected_rows = 0;
    public int $insert_id = 0;

    public function __construct(SQLiteCompatConnection $connection, PDOStatement $statement) {
        $this->connection = $connection;
        $this->statement = $statement;
    }

    public function bind_param(string $types, &...$vars): bool {
        $this->types = $types;
        $this->boundValues = [];
        foreach ($vars as &$value) {
            $this->boundValues[] = &$value;
        }
        return true;
    }

    public function execute(): bool {
        try {
            $params = [];
            foreach ($this->boundValues as $value) {
                $params[] = $value;
            }
            $ok = $this->statement->execute($params);
            $this->affected_rows = $ok ? $this->statement->rowCount() : 0;
            $this->insert_id = (int)$this->connection->getPDO()->lastInsertId();
            $this->connection->error = '';
            $this->error = '';
            return $ok;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->connection->error = $this->error;
            return false;
        }
    }

    public function get_result() {
        try {
            $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);
            return new SQLiteCompatResult($rows);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->connection->error = $this->error;
            return false;
        }
    }

    public function close(): bool {
        return true;
    }
}

class SQLiteCompatConnection {
    private PDO $pdo;
    public string $error = '';
    public string $connect_error = '';

    public function __construct(string $sqlitePath) {
        try {
            $dir = dirname($sqlitePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $this->pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
            ]);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } catch (Throwable $e) {
            $this->connect_error = $e->getMessage();
            $this->error = $this->connect_error;
        }
    }

    public function getPDO(): PDO {
        return $this->pdo;
    }

    public function set_charset(string $charset): bool {
        return true;
    }

    public function prepare(string $sql) {
        $transformed = $this->transformSql($sql);
        try {
            $stmt = $this->pdo->prepare($transformed);
            if (!$stmt) {
                $this->error = 'Failed to prepare statement';
                return false;
            }
            $this->error = '';
            return new SQLiteCompatStatement($this, $stmt);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function query(string $sql) {
        $transformed = $this->transformSql($sql);
        try {
            $isSelect = (bool)preg_match('/^\s*(SELECT|WITH|PRAGMA)/i', $transformed);
            if ($isSelect) {
                $stmt = $this->pdo->query($transformed);
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $this->error = '';
                return new SQLiteCompatResult($rows);
            }
            $result = $this->pdo->exec($transformed);
            $this->error = '';
            return $result !== false;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function close(): bool {
        return true;
    }

    private function transformSql(string $sql): string {
        $updated = $sql;

        $updated = preg_replace('/\bNOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $updated);
        $updated = preg_replace('/\bUNIX_TIMESTAMP\s*\(([^\)]+)\)/i', "CAST(strftime('%s', $1) AS INTEGER)", $updated);

        if (stripos($updated, 'ON DUPLICATE KEY UPDATE') !== false) {
            $updated = $this->transformOnDuplicateKey($updated);
        }

        return $updated;
    }

    private function transformOnDuplicateKey(string $sql): string {
        if (!preg_match('/^\s*INSERT\s+INTO\s+([a-zA-Z0-9_]+)\s*\(([^\)]*)\)\s*VALUES\s*\(([^\)]*)\)\s*ON\s+DUPLICATE\s+KEY\s+UPDATE\s*(.+)$/is', $sql, $m)) {
            return $sql;
        }

        $table = trim($m[1]);
        $columns = array_map('trim', explode(',', $m[2]));
        $values = trim($m[3]);
        $updates = trim($m[4]);

        $conflictMap = [
            'checklist_status' => ['user_id', 'swo_id', 'item_key'],
            'support_reviews' => ['swo_id', 'reviewed_by'],
            'control_reviews' => ['swo_id', 'reviewed_by'],
            'support_item_reviews' => ['swo_id', 'item_key'],
            'control_item_reviews' => ['swo_id', 'item_key'],
            'user_item_comments' => ['swo_id', 'item_key', 'user_id'],
        ];

        if (!isset($conflictMap[$table])) {
            return $sql;
        }

        $updates = preg_replace('/VALUES\s*\(\s*([a-zA-Z0-9_]+)\s*\)/i', 'excluded.$1', $updates);
        $updates = preg_replace('/\bNOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $updates);

        $conflictCols = implode(', ', $conflictMap[$table]);
        return "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$values}) ON CONFLICT({$conflictCols}) DO UPDATE SET {$updates}";
    }
}
