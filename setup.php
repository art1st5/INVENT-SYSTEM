<?php
/**
 * setup.php — First-time installation page.
 *
 * PURPOSE:
 *   Creates the very first owner account when the system is brand new
 *   and the users table is completely empty. This is the only way to
 *   get into the system before any accounts exist.
 *
 * SECURITY:
 *   This page LOCKS ITSELF automatically once a single user is created.
 *   If any account already exists in the database, visiting this page
 *   returns a 403 Forbidden error. This prevents anyone from creating
 *   a rogue owner account after the system is in use.
 *
 * AFTER SETUP:
 *   Delete or rename this file once the owner account is created,
 *   as an extra precaution.
 */

require 'config.php'; // Load database connection and helper functions

// Count how many users already exist in the database.
// If the result is greater than zero, the system is already set up — lock the page.
$count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count) {
    http_response_code(403);
    die('Setup is locked because an account already exists. Delete setup.php after installation.');
}

$error = '';

// ── Handle the setup form submission ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate the CSRF token to block forged requests.
    checkcsrf();

    $name = trim($_POST['username'] ?? '');
    $pw   = $_POST['password'] ?? '';

    // Enforce minimum length rules.
    // A short username is easy to guess; a short password is easy to brute-force.
    if (strlen($name) < 3 || strlen($pw) < 10) {
        $error = 'Username must have 3+ characters and password 10+ characters.';
    } else {
        // password_hash() converts the plain-text password into a secure bcrypt hash.
        // The hash is what gets stored in the database — the plain text is never saved.
        // PASSWORD_DEFAULT automatically uses the strongest algorithm available.
        $q = $pdo->prepare("INSERT INTO users(user_name,user_pw,role) VALUES(?,?,'owner')");
        $q->execute([$name, password_hash($pw, PASSWORD_DEFAULT)]);

        // Account created — go straight to the login page.
        header('Location: login.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/style.css?v=4">
    <script>
        /**
         * Apply saved dark/light theme before the page renders
         * so there is no white flash on dark mode users.
         */
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body class="login">
    <form class="login-card" method="post">
        <h1>Create owner account</h1>
        <p>Only available before the first user exists.</p>

        <!-- Show validation error if the form was submitted with bad values -->
        <?php if ($error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- CSRF hidden field — protects the form from cross-site forgery -->
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

        <label>Username
            <input name="username" required>
        </label>
        <label>Password
            <input type="password" name="password" minlength="10" required>
        </label>

        <button class="btn primary full">Create account</button>
    </form>
</body>
</html>
