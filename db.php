<?php

$host = '127.0.0.1';
$dbname = 'qr_motors';
$username = 'root';
$password = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $connection = new mysqli($host, $username, $password, $dbname);
    $connection->set_charset('utf8mb4');
    $db = new DatabaseAdapter($connection);
} catch (mysqli_sql_exception $e) {
    exit('Database connection failed: ' . $e->getMessage());
}

class DatabaseAdapter {
    private mysqli $connection;

    public function __construct(mysqli $connection) {
        $this->connection = $connection;
    }

    public function prepare(string $query): StatementAdapter {
        return new StatementAdapter($this->connection->prepare($query));
    }

    public function query(string $query): StatementAdapter {
        $result = $this->connection->query($query);
        return new StatementAdapter(null, $result);
    }

    public function lastInsertId(): int {
        return $this->connection->insert_id;
    }
}

class StatementAdapter {
    private ?mysqli_stmt $statement;
    private mysqli_result|bool|null $result;

    public function __construct(?mysqli_stmt $statement, mysqli_result|bool|null $result = null) {
        $this->statement = $statement;
        $this->result = $result;
    }

    public function execute(array $values = []): bool {
        if (!$this->statement) {
            return true;
        }

        if ($values) {
            $types = '';
            foreach ($values as $value) {
                $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
            }
            $references = [];
            foreach ($values as $key => &$value) {
                $references[$key] = &$value;
            }
            $this->statement->bind_param($types, ...$references);
        }

        $this->statement->execute();
        $this->result = $this->statement->get_result();
        return true;
    }

    public function fetch(): ?array {
        return $this->result instanceof mysqli_result ? $this->result->fetch_assoc() : null;
    }

    public function fetchAll(): array {
        return $this->result instanceof mysqli_result ? $this->result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function rowCount(): int {
        return $this->statement ? $this->statement->affected_rows : 0;
    }
}
