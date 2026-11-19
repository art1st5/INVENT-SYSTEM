<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Abstract parent providing common prepared query, queryAll, execute and lastId helpers.
 * Customize shared database behavior here; changes affect subclasses.
 */
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

    // Customization: Dependencies are initialized here; coordinate changes with object creation sites.
    public function __construct(PDO $pdo, Auditable $logger)
    {
        $this->pdo    = $pdo;
        $this->logger = $logger;
    }

    /**
     * query() — Run a SELECT and return a single row.
     * ABSTRACTION: child classes call this without knowing PDO internals.
     */
    // Customization: Keep bound parameters separate from SQL text.
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
    // Customization: Keep bound parameters and the returned row collection consistent.
    protected function queryAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * execute() — Run an INSERT / UPDATE / DELETE statement.
     */
    // Customization: Keep bound parameters and write operations consistent.
    protected function execute(string $sql, array $params = []): void
    {
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * lastId() — Return the last auto-incremented ID after an INSERT.
     */
    // Customization: Use the inserted ID on the same database connection.
    protected function lastId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }
}
