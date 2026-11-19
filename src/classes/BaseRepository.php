<?php
/**
 * src/classes/BaseRepository.php
 *
 * ABSTRACTION — This abstract class defines the shared structure and
 * common behaviour for all repositories. It hides the details of how
 * database queries are prepared and executed; child classes only
 * implement the domain-specific logic they need.
 *
 * INHERITANCE — ProductRepository, TransactionRepository, and
 * DeliveryRepository all extend this class and inherit $pdo,
 * $logger, query(), queryAll(), and execute() without rewriting them.
 */
abstract class BaseRepository
{
    // Protected — accessible in this class and all child classes, but not outside
    protected PDO      $pdo;
    protected Auditable $logger;

    public function __construct(PDO $pdo, Auditable $logger)
    {
        $this->pdo    = $pdo;
        $this->logger = $logger;
    }

    /**
     * query() — Run a SELECT and return a single row.
     * ABSTRACTION: child classes call this without knowing PDO internals.
     */
    protected function query(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * queryAll() — Run a SELECT and return all matching rows.
     */
    protected function queryAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * execute() — Run an INSERT / UPDATE / DELETE statement.
     */
    protected function execute(string $sql, array $params = []): void
    {
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * lastId() — Return the last auto-incremented ID after an INSERT.
     */
    protected function lastId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }
}
