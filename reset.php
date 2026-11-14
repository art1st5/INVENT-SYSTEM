<?php
/**
 * reset.php — Account recovery tool
 *
 * HOW TO USE:
 *   1. Open your browser and go to:
 *      http://localhost/matet_inventory_website/reset.php?key=matet-reset-2024
 *   2. The page shows all usernames so you can find yours.
 *   3. Pick the account, enter a new password, and save.
 *   4. Log in normally at login.php.
 *
 * SECURITY:
 *   - The page is locked behind RESET_SECRET_KEY (defined in config.php).
 *   - Change that key to something personal so only you can access this page.
 *   - Nobody can reach this page without knowing the key — the URL alone is enough.
 */

require 'config.php';

// ── Gate: require the secret key in the URL ──────────────────────────────────
$key = $_GET['key'] ?? '';
if (!hash_equals(RESET_SECRET_KEY, $key)) {
    http_response_code(403);
    die('Access denied. Append ?key=YOUR_SECRET_KEY to the URL to access this page.');
}

// ── Handle form submission ────────────────────────────────────────────────────
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkcsrf();

    $uid  = (int)($_POST['user_id'] ?? 0);
    $pw   = $_POST['password'] ?? '';
    $pw2  = $_POST['password2'] ?? '';

    if (!$uid) {
        $error = 'Please select an account.';
    } elseif (strlen($pw) < 10) {
        $error = 'Password must be at least 10 characters.';
    } elseif ($pw !== $pw2) {
        $error = 'Passwords do not match.';
    } else {
        // Verify the user exists before updating
        $q = $pdo->prepare('SELECT user_name FROM users WHERE user_id = ?');
        $q->execute([$uid]);
        $u = $q->fetch();

        if (!$u) {
            $error = 'Account not found.';
        } else {
            $q = $pdo->prepare('UPDATE users SET user_pw = ? WHERE user_id = ?');
            $q->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
            $success = 'Password for "' . h($u['user_name']) . '" has been reset. You can now log in.';
        }
    }
}

// ── Load all accounts ─────────────────────────────────────────────────────────
$users = $pdo->query('SELECT user_id, user_name, role FROM users ORDER BY role, user_name')->fetchAll();

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Matet • Account Recovery</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .reset-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 70px #17243a17;
            width: min(480px, 90vw);
        }
        .reset-card h1 {
            margin-bottom: 6px;
        }
        .reset-card .subtitle {
            color: #8b96a8;
            margin-bottom: 28px;
            font-size: 13px;
        }
        .user-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }
        .user-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border: 1px solid #e8edf3;
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }
        .user-option:hover {
            background: #f4f7fb;
        }
        .user-option input[type="radio"] {
            width: auto;
            margin: 0;
            accent-color: #2563eb;
        }
        .user-option .uname {
            font-weight: 700;
            color: #1b2738;
            font-size: 13px;
        }
        .user-option .urole {
            font-size: 11px;
            color: #8b96a8;
            margin-left: auto;
            background: #eef2ff;
            color: #2563eb;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
        }
        .user-option .urole.owner-badge {
            background: #fef3c7;
            color: #d97706;
        }
        .divider {
            border: none;
            border-top: 1px solid #e8edf3;
            margin: 24px 0;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            font-size: 13px;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .success {
            background: #e4f8ed;
            color: #177e4c;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert {
            background: #ffebee;
            color: #b3273b;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .security-note {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 12px;
            color: #92400e;
            margin-bottom: 24px;
        }
        .security-note strong {
            display: block;
            margin-bottom: 4px;
        }
    </style>
    <script>
        (function(){
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body class="login">
    <div class="reset-card">
        <div class="brand-icon" style="margin-bottom:16px">M</div>
        <h1>Account Recovery</h1>
        <p class="subtitle">Matet Motorcycle Parts &bull; Reset a forgotten password</p>

        <?php if ($success): ?>
            <div class="success"><?= h($success) ?></div>
            <a class="btn primary full" href="login.php">Go to Login</a>
            <a class="back-link" href="reset.php?key=<?= h($key) ?>">Reset another account</a>
        <?php else: ?>

            <div class="security-note">
                <strong>⚠ Keep this URL private</strong>
                Anyone with this link can reset passwords. Do not share it.
            </div>

            <?php if ($error): ?>
                <div class="alert"><?= h($error) ?></div>
            <?php endif; ?>

            <?php if (!$users): ?>
                <p style="color:#8b96a8;text-align:center">No accounts found in the database.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

                    <p style="font-size:12px;font-weight:700;color:#536175;margin-bottom:10px">
                        Step 1 — Select the account to reset
                    </p>
                    <div class="user-list">
                        <?php foreach ($users as $u): ?>
                            <label class="user-option">
                                <input
                                    type="radio"
                                    name="user_id"
                                    value="<?= $u['user_id'] ?>"
                                    <?= (isset($_POST['user_id']) && (int)$_POST['user_id'] === (int)$u['user_id']) ? 'checked' : '' ?>
                                    required
                                >
                                <span class="uname"><?= h($u['user_name']) ?></span>
                                <span class="urole <?= $u['role'] === 'owner' ? 'owner-badge' : '' ?>">
                                    <?= ucfirst(h($u['role'])) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <hr class="divider">

                    <p style="font-size:12px;font-weight:700;color:#536175;margin-bottom:16px">
                        Step 2 — Set a new password
                    </p>

                    <label>New Password (10+ characters)
                        <input
                            type="password"
                            name="password"
                            minlength="10"
                            required
                            autocomplete="new-password"
                        >
                    </label>
                    <label>Confirm New Password
                        <input
                            type="password"
                            name="password2"
                            minlength="10"
                            required
                            autocomplete="new-password"
                        >
                    </label>

                    <button class="btn primary full" style="margin-top:8px">Reset Password</button>
                </form>
            <?php endif; ?>

            <a class="back-link" href="login.php">← Back to login</a>

        <?php endif; ?>
    </div>
</body>
</html>
