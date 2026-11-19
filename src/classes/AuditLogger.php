<?php
/**
 * src/classes/AuditLogger.php
 *
 * POLYMORPHISM — Implements the Auditable interface.
 * Writes audit entries to the database audit_logs table.
 *
 * A second implementation (e.g. FileAuditLogger) could write to a file
 * instead, and all repositories would work with it without any changes —
 * that's polymorphism: different behaviour behind the same interface.
 */
class AuditLogger implements Auditable
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * log() — Insert a row into audit_logs.
     */
    public function log(int $userId, string $action, string $details = ''): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $details]);
    }
}
