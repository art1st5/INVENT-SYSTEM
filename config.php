<?php
session_start();
$host = 'localhost';
$db = 'matet_inventory';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Import database.sql and check config.php.');
}
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function csrf() {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function checkcsrf() {
    if (!hash_equals(csrf(), $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid request token');
    }
}
function auth() {
    if (empty($_SESSION['uid'])) {
        header('Location: login.php');
        exit;
    }
}
function owner() {
    if (($_SESSION['role'] ?? '') !== 'owner') {
        http_response_code(403);
        die('Owner access required');
    }
}
function flash($message) {
    $_SESSION['flash'] = $message;
}
function go($page) {
    header('Location: index.php?page=' . $page);
    exit;
}

// Secret key to access reset.php — change this to something only you know.
// Visit: reset.php?key=YOUR_SECRET_KEY
define('RESET_SECRET_KEY', 'matet-reset-2024');
