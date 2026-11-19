<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Singleton PDO connection wrapper. Customize private host, db, user and pass values
 * together with config.php; preserve PDO error handling.
 */
/**
 * src/classes/Database.php
 *
 * ENCAPSULATION — The PDO connection and credentials are private.
 * Outside code cannot access $host, $user, $pass, or $pdo directly.
 * Everything goes through the public methods, keeping the internals hidden.
 */
class Database
{
    // Private properties — cannot be accessed from outside this class
    private static ?Database $instance = null;
    private PDO $pdo;

    private string $host = 'localhost';
    private string $db   = 'matet_inventory';
    private string $user = 'root';
    private string $pass = '';

    // Private constructor — prevents direct instantiation (Singleton pattern)
    // Customization: Dependencies are initialized here; coordinate changes with object creation sites.
    private function __construct()
    {
        $this->pdo = new PDO(
            "mysql:host={$this->host};dbname={$this->db};charset=utf8mb4",
            $this->user,
            $this->pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    /**
     * getInstance() — Returns the single shared Database instance.
     * Creates it on first call; returns the same one after that.
     */
    // Customization: Preserve the shared-instance pattern.
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * getConnection() — Returns the underlying PDO object.
     * This is the only public way to reach the PDO connection.
     */
    // Customization: Returns the PDO connection used by repositories.
    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
