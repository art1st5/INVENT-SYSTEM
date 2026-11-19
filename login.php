<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Authenticates active staff and renders the sign-in form. Customize branding and form
 * presentation here; preserve password verification and session regeneration.
 */
/**
 * login.php — Staff login page
 *
 * PURPOSE:
 *   Displays the sign-in form and processes the submitted credentials.
 *   On success, stores the user's ID, name, and role in the session and
 *   redirects to the main inventory dashboard (index.php).
 *
 * SECURITY MEASURES:
 *   - CSRF token validation prevents forged form submissions from other sites.
 *   - password_verify() safely checks bcrypt hashes (never compares plain text).
 *   - session_regenerate_id() assigns a new session ID after login to prevent
 *     session fixation attacks.
 *   - Only accounts with active=1 can log in (deactivated accounts are blocked).
 */

require 'config.php'; // Load database connection and helper functions

// If a user is already logged in, skip the login page and go straight to the dashboard.
if (!empty($_SESSION['uid'])) {
    header('Location: index.php');
    exit;
}

$error = ''; // Will hold any login error message shown to the user

// ── Handle the login form submission ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify the CSRF token to block forged requests from other websites.
    checkcsrf();

    // Look up the user by username, but only if their account is active (active=1).
    // The ? placeholder is a prepared statement parameter — the actual value is
    // passed safely in execute(), preventing SQL injection.
    $q = $pdo->prepare('SELECT * FROM users WHERE user_name=? AND active=1');
    $q->execute([trim($_POST['username'] ?? '')]);
    $u = $q->fetch();

    // password_verify() compares the submitted plain-text password against
    // the stored bcrypt hash. It returns true only if they match.
    if ($u && password_verify($_POST['password'] ?? '', $u['user_pw'])) {

        // Generate a new session ID to prevent session fixation.
        // This invalidates the old ID so an attacker who captured it cannot use it.
        session_regenerate_id(true);

        // Store the user's details in the session. These are available on every
        // subsequent page via $_SESSION['uid'], $_SESSION['name'], $_SESSION['role'].
        $_SESSION['uid']  = $u['user_id'];
        $_SESSION['name'] = $u['user_name'];
        $_SESSION['role'] = $u['role'];

        // Redirect to the dashboard after a successful login.
        header('Location: index.php');
        exit;
    }

    // If we reach here, the username or password was wrong.
    // We use a generic message on purpose — never reveal which one was wrong.
    $error = 'Invalid username or password';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Matet • Sign in</title>
    <link rel="stylesheet" href="assets/style.css?v=4">
    <script>
        /**
         * Apply dark/light theme before the page renders to avoid a white flash.
         * We read localStorage (set by the toggle button in index.php).
         * If no preference is saved, we fall back to the OS setting.
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
        <!-- Brand icon — matches the sidebar icon in the main app -->
        <div class="brand-icon">M</div>
        <h1>Welcome back</h1>
        <p>Matet Motorcycle Parts &bull; Inventory System</p>

        <!-- Show error message if login failed -->
        <?php if ($error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- Hidden CSRF token field — must match the session token or the form is rejected -->
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

        <label>Username
            <input name="username" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>

        <button class="btn primary full">Sign in</button>

        <small>First-time setup: open setup.php to create your owner account.</small>

        <!-- Link to the password reset tool (protected by a secret key in the URL) -->
        <small style="margin-top:4px">
            Forgot your password?
            Contact the owner to reset your password.
        </small>
    </form>
</body>
</html>
