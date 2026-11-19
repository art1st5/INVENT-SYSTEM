<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Shared session, PDO connection, authorization and helper functions. Customize $host, $db,
 * $user and $pass for your server; keep Database.php credentials consistent.
 * RESET_SECRET_KEY controls recovery access; change it before deployment. Preserve CSRF and
 * authorization checks.
 */
/**
 * config.php — Shared configuration loaded by every page.
 *
 * This file is the foundation of the entire system. It:
 *   1. Starts the user session so login state is remembered across pages.
 *   2. Connects to the MySQL database using PDO (PHP Data Objects).
 *   3. Defines reusable helper functions that all other files can call.
 *
 * Every PHP file begins with: require 'config.php';
 */

// ── 1. Session ────────────────────────────────────────────────────────────────
// session_start() must be called before reading or writing $_SESSION.
// It resumes an existing session (identified by a cookie) or creates a new one.
// We store the logged-in user's ID, name, and role here after a successful login.
session_start();

// ── 2. Database connection ────────────────────────────────────────────────────
// These are the credentials for the local XAMPP MySQL server.
// Change $db if the database name is different on another machine.
$host = 'localhost';        // MySQL server address (localhost = this machine)
$db   = 'matet_inventory';  // The database name created from database.sql
$user = 'root';             // Default XAMPP MySQL username (no password)
$pass = '';

try {
    // PDO (PHP Data Objects) is a database abstraction layer.
    // Using prepared statements through PDO prevents SQL injection attacks
    // because user input is never directly embedded into SQL strings.
    //
    // Options explained:
    //   ERRMODE_EXCEPTION  — throw a PDOException on any database error
    //                        so we can catch and handle it cleanly.
    //   FETCH_ASSOC        — return rows as associative arrays (e.g. $row['product_name'])
    //                        instead of numeric arrays ($row[0]).
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // If the connection fails (wrong credentials, MySQL not running, etc.),
    // stop the page immediately with a 500 error so the user knows something is wrong.
    http_response_code(500);
    die('Database connection failed. Import database.sql and check config.php.');
}

// ── 3. Helper functions ───────────────────────────────────────────────────────

/**
 * h() — HTML-escape a value before displaying it on the page.
 *
 * Purpose: prevent XSS (Cross-Site Scripting) attacks.
 * If a product name contains <script>alert('hacked')</script>, this function
 * converts the angle brackets to &lt; and &gt; so the browser displays
 * the text literally instead of executing it as JavaScript.
 *
 * Every variable echoed into HTML must be wrapped: <?= h($variable) ?>
 */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * csrf() — Return (or create) a CSRF token for the current session.
 *
 * Purpose: prevent Cross-Site Request Forgery attacks.
 * A CSRF attack tricks a logged-in user into unknowingly submitting a form
 * on another website that targets our system.
 *
 * How it works:
 *   - On first call, a random 64-character token is generated and stored in
 *     the session. The same token is printed as a hidden input in every form.
 *   - When the form is submitted, checkcsrf() verifies the token matches.
 *   - An attacker on another site cannot know this token, so their fake
 *     form submission will be rejected.
 */
function csrf() {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

/**
 * checkcsrf() — Verify that the submitted CSRF token matches the session token.
 *
 * Called at the top of every POST handler. Kills the request with a 403 error
 * if the token is missing or wrong (indicating a forged request).
 *
 * hash_equals() is used instead of === to prevent timing attacks.
 */
function checkcsrf() {
    if (!hash_equals(csrf(), $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid request token');
    }
}

/**
 * auth() — Redirect to the login page if the user is not logged in.
 *
 * Every page that requires a login calls auth() near the top.
 * If $_SESSION['uid'] is not set, there is no active login session,
 * so the user is sent to login.php instead.
 */
function auth() {
    if (empty($_SESSION['uid'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * owner() — Block access and return a 403 error if the user is not an owner.
 *
 * Some pages (users, audit log) and some actions (delete category, add user)
 * are restricted to the 'owner' role only. Assistants cannot access them.
 */
function owner() {
    if (($_SESSION['role'] ?? '') !== 'owner') {
        http_response_code(403);
        die('Owner access required');
    }
}

/**
 * notAccountant() — Block write actions if the current user is an accountant.
 *
 * Accountants are read-only: they can view the dashboard, reports, catalog,
 * and products list, but cannot add, edit, archive, or delete anything.
 * Call this at the top of any handler that modifies data.
 */
function notAccountant() {
    if (($_SESSION['role'] ?? '') === 'accountant') {
        http_response_code(403);
        die('Accountants have read-only access.');
    }
}

/**
 * flash() — Store a one-time success message to show after a redirect.
 *
 * After a form is submitted (e.g. saving a product), the page redirects
 * to avoid re-submission on refresh. The success message is stored in
 * the session so it survives the redirect and can be displayed once.
 */
function flash($message) {
    $_SESSION['flash'] = $message;
}

/**
 * go() — Redirect to a specific page within the inventory system.
 *
 * Shortcut for: header('Location: index.php?page=X'); exit;
 * Used after every successful POST action to redirect back to the right page.
 */
function go($page) {
    header('Location: index.php?page=' . $page);
    exit;
}

// ── 4. OOP layer ─────────────────────────────────────────────────────────────
// Load all class files in dependency order.
// These classes demonstrate all four OOP pillars:
//   Encapsulation  → Database, all Repository classes (private/protected members)
//   Abstraction    → BaseRepository (abstract class hiding SQL details)
//   Inheritance    → ProductRepository, TransactionRepository, DeliveryRepository extend BaseRepository
//   Polymorphism   → Auditable interface; AuditLogger implements it

require __DIR__ . '/src/classes/Database.php';
require __DIR__ . '/src/classes/Auditable.php';
require __DIR__ . '/src/classes/AuditLogger.php';
require __DIR__ . '/src/classes/BaseRepository.php';
require __DIR__ . '/src/classes/Stack.php';
require __DIR__ . '/src/classes/SearchAlgorithms.php';
require __DIR__ . '/src/classes/SortAlgorithms.php';
require __DIR__ . '/src/classes/ProductRepository.php';
require __DIR__ . '/src/classes/TransactionRepository.php';
require __DIR__ . '/src/classes/DeliveryRepository.php';

// Instantiate shared services — available to all handlers and pages
$auditLogger         = new AuditLogger($pdo);
$productRepo         = new ProductRepository($pdo, $auditLogger);
$transactionRepo     = new TransactionRepository($pdo, $auditLogger, $productRepo);
$deliveryRepo        = new DeliveryRepository($pdo, $auditLogger, $productRepo);

// ── 5. Password reset secret key ─────────────────────────────────────────────
// This key is required in the URL to access reset.php.
// Visit: http://localhost/matet_inventory_website/reset.php?key=matet-reset-2024
// IMPORTANT: Change this to something only you know so strangers cannot reset passwords.
define('RESET_SECRET_KEY', 'matet-reset-2024');
