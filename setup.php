<?php
require 'config.php';
$count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count) {
    http_response_code(403);
    die('Setup is locked because an account already exists. Delete setup.php after installation.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkcsrf();
    $name = trim($_POST['username'] ?? '');
    $pw = $_POST['password'] ?? '';
    if (strlen($name) < 3 || strlen($pw) < 10) {
        $error = 'Username must have 3+ characters and password 10+ characters.';
    } else {
        $q = $pdo->prepare("INSERT INTO users(user_name,user_pw,role) VALUES(?,?,'owner')");
        $q->execute([$name, password_hash($pw, PASSWORD_DEFAULT)]);
        header('Location: login.php');
        exit;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login">
    <form class="login-card" method="post">
        <h1>Create owner account</h1>
        <p>Only available before the first user exists.</p>
        <?php if ($error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <label>Username<input name="username" required></label>
        <label>Password<input type="password" name="password" minlength="10" required></label>
        <button class="btn primary full">Create account</button>
    </form>
</body>
</html>
