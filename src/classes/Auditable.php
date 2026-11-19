<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Interface defining the logging contract. Customize only when all implementing loggers and
 * their callers can be updated together.
 */
/**
 * src/classes/Auditable.php
 *
 * POLYMORPHISM — Any class that implements Auditable must define log().
 * Both AuditLogger and any future logger (e.g. FileLogger, EmailLogger)
 * can be swapped in wherever an Auditable is expected, and the calling
 * code doesn't need to know which one it's using.
 */
interface Auditable
{
    /**
     * log() — Record an action to whichever storage this logger uses.
     *
     * @param int    $userId  The ID of the user performing the action
     * @param string $action  Short action label e.g. "Save product"
     * @param string $details Longer description of what changed
     */
    // Customization: Coordinate audit fields with the audit table; retain actor identity.
    public function log(int $userId, string $action, string $details = ''): void;
}
