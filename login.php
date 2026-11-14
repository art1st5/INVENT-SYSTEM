<?php
require 'config.php';
if (!empty($_SESSION['uid'])) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkcsrf();
    $q = $pdo->prepare('SELECT * FROM users WHERE user_name=? AND active=1');
    $q->execute([trim($_POST['username'] ?? '')]);
    $u = $q->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['user_pw'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['user_id'];
        $_SESSION['name'] = $u['user_name'];
        $_SESSION['role'] = $u['role'];
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid username or password';
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Matet • Sign in</title>
    <link rel="stylesheet" href="assets/style.css">
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
    <form class="login-card" method="post">
        <div class="brand-icon">M</div>
        <h1>Welcome back</h1>
        <p>Matet Motorcycle Parts • Inventory System</p>
        <?php if ($error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <label>Username<input name="username" required autofocus></label>
        <label>Password<input type="password" name="password" required></label>
        <button class="btn primary full">Sign in</button>
        <small>First-time setup: open setup.php to create your owner account.</small>
        <small style="margin-top:4px">Forgot your password? <a href="reset.php?key=matet-reset-2024" style="color:#2563eb;font-weight:600">Reset it here</a></small>
    </form>
</body>
</html>
