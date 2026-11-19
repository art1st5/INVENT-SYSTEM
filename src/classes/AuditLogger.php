<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Concrete implementation of Auditable that records activity in audit_logs. Customize
 * logging details and coordinate schema changes with database.sql.
 */
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

    // Customization: Dependencies are initialized here; coordinate changes with object creation sites.
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * log() — Insert a row into audit_logs.
     */
    // Customization: Coordinate audit fields with the audit table; retain actor identity.
    public function log(int $userId, string $action, string $details = ''): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $details]);
    }
}
