<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Main entry point: routes requests, checks account permissions, dispatches handlers and
 * renders templates. Customize navigation labels, allowed routes and rolePages here;
 * coordinate permissions with handlers and database roles.
 */
/**
 * index.php — Application entry point and router.
 *
 * This file is intentionally kept thin. Its only jobs are:
 *   1. Bootstrap (config, auth, routing)
 *   2. Dispatch POST actions to the right handler file in src/handlers/
 *   3. Load shared query data from src/queries.php
 *   4. Render the page layout and include the right page template from src/pages/
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Project structure
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  index.php             ← this file (router + layout)
 *  config.php            ← database connection, helper functions, CSRF, auth
 *  login.php             ← sign-in page
 *  logout.php            ← destroys session and redirects
 *  setup.php             ← creates the first owner account (auto-locks after use)
 *  reset.php             ← password recovery (key-protected)
 *  database.sql          ← full schema + seed data
 *  assets/
 *    style.css           ← all styling including dark mode
 *    app.js?v=3              ← all client-side interactivity
 *  src/
 *    queries.php         ← shared SELECT queries run on every page load
 *    handlers/
 *      products.php      ← save_product, archive_product, restore_product
 *      categories.php    ← save_category, edit_category, delete_category
 *      suppliers.php     ← save_supplier
 *      transactions.php  ← sale
 *      deliveries.php    ← delivery, receive_delivery
 *      users.php         ← add_user
 *    pages/
 *      dashboard.php     ← page template: stats + low stock + recent sales
 *      products.php      ← page template: product table
 *      categories.php    ← page template: category list + filtered products
 *      suppliers.php     ← page template: supplier list + filtered products
 *      transactions.php  ← page template: record sale + history
 *      deliveries.php    ← page template: record delivery + history
 *      reports.php       ← page template: profit breakdown with period filter
 *      catalog.php       ← page template: customer-facing product cards
 *      users.php         ← page template: account management (owner only)
 *      audit.php         ← page template: activity log (owner only)
 *      modal_product.php ← Add / Edit product modal dialog
 *      modal_category.php← Edit category modal dialog
 */

// ── 1. Bootstrap ──────────────────────────────────────────────────────────────
require 'config.php';   // DB connection + helpers (h, csrf, auth, owner, flash, go)
auth();

// ── 2. Route — determine which page to show ───────────────────────────────────
// Retrieve the current user's account permissions.
$q = $pdo->prepare(
    'SELECT role, active FROM users WHERE user_id = ?'
);

$q->execute([$_SESSION['uid']]);
$account = $q->fetch();

if (!$account || !$account['active']) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['role'] = $account['role'];

$role = $_SESSION['role'];

$readOnly = $role === 'employee';

$employee = $role === 'employee';

$rolePages = null;

// Employee permissions.
if ($role === 'employee') {
    $rolePages = ['products'];
}

$page = $_GET['page'] ?? (
    $employee ? 'products' : 'dashboard'
);

$allowed = ['dashboard', 'products', 'categories', 'suppliers',
            'transactions', 'deliveries', 'reports', 'catalog',
            'users', 'audit'];

// Reject unknown pages silently — prevents path traversal or other tricks
if (!in_array($page, $allowed, true)) $page = 'dashboard';
// Prevent access to restricted pages.

if ($rolePages !== null &&
    !in_array($page, $rolePages, true)) {

    http_response_code(403);
    exit('Access denied.');
}

// Prevent read-only users from modifying records.
if ($readOnly &&
    $_SERVER['REQUEST_METHOD'] === 'POST') {

    http_response_code(403);
    exit('You do not have permission to modify records.');
}

// users and audit pages require owner role
if (in_array($page, ['users', 'audit'], true)) owner();

// ── Helper functions shared by handlers and page templates ────────────────────

/** logit() — Write an entry to the audit_logs table. */
function logit($pdo, $action, $details = '') {
    $q = $pdo->prepare('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)');
    $q->execute([$_SESSION['uid'], $action, $details]);
}

/** money() — Format a value as ₱1,234.56 */
function money($v) {
    return '₱' . number_format((float)$v, 2);
}

/** getall() — Run a SELECT with no parameters and return all rows. */
function getall($pdo, $sql) {
    return $pdo->query($sql)->fetchAll();
}

// ── 3. Handle POST actions ────────────────────────────────────────────────────
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkcsrf();                        // Reject forged requests
    $act = $_POST['action'] ?? '';

    try {
        // Each handler file checks $act internally and calls go() on success,
        // so only the matching handler actually does anything.
        require __DIR__ . '/src/handlers/products.php';
        require __DIR__ . '/src/handlers/categories.php';
        require __DIR__ . '/src/handlers/suppliers.php';
        require __DIR__ . '/src/handlers/transactions.php';
        require __DIR__ . '/src/handlers/deliveries.php';
        require __DIR__ . '/src/handlers/users.php';

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Show the real error message in all cases so issues are visible
        $err = $e->getMessage();
    }
}

// ── 4. Load shared data for all page templates ────────────────────────────────
// Populates: $cats, $sups, $products, $active, $low, $sales, $deliveries
if ($employee) {

    $products = getall(
        $pdo,
        'SELECT
            p.product_name,
            p.product_cost,
            p.selling_price,
            p.product_quantity,
            p.reorder_level,
            p.image_url,
            c.category_name
         FROM products p
         JOIN categories c ON c.category_id = p.category_id
         WHERE p.active = 1
         ORDER BY p.product_name'
    );

    require __DIR__ . '/src/pages/employees_prod.php';
    exit;

} else {

    require __DIR__ . '/src/queries.php';

}

// Navigation menu: page key => display label mapping
$nav = [
    'dashboard'    => '🏠',   // ← Dashboard
    'catalog'      => '🛍️',   // ← Catalog
    'products'     => '📦',   // ← Products
    'categories'   => '🗂️',   // ← Categories
    'suppliers'    => '👤',   // ← Suppliers
    'transactions' => '🧾',   // ← Transactions / Sales
    'deliveries'   => '🚚',   // ← Deliveries
    'reports'      => '📊',   // ← Reports
    'users'        => '👤',   // ← Users
    'audit'        => '🔍',   // ← Audit log
];

$pageTitles = [
    'dashboard'    => 'Dashboard Overview',
    'products'     => 'Product Inventory',
    'categories'   => 'Product Categories',
    'suppliers'    => 'Suppliers',
    'transactions' => 'Sales Transactions',
    'deliveries'   => 'Stock Deliveries',
    'reports'      => 'Business Reports',
    'catalog'      => 'Product Catalog',
    'users'        => 'User Management',
    'audit'        => 'Activity Log',
];

$pageSubtitles = [
    'dashboard'    => "Here's what's happening with your store today.",
    'products'     => 'Manage products, pricing, and stock levels.',
    'categories'   => 'Organize your inventory into categories.',
    'suppliers'    => 'Manage your product sources.',
    'transactions' => 'Record in-store cash sales and track transactions.',
    'deliveries'   => 'Receive stock from your suppliers.',
    'reports'      => 'Sales performance and inventory insights.',
    'catalog'      => 'Customer-facing product and availability preview.',
    'users'        => 'Manage staff access to the system.',
    'audit'        => 'Track important actions performed in the system.',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Matet Inventory</title>
    <link rel="stylesheet" href="assets/style.css?v=4">
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch(e) {}
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>
<div class="layout">

    <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">M</div>
            <div>
                <strong>MATET</strong>
                <span>MOTORCYCLE PARTS</span>
            </div>
        </div>

        <div class="nav-label">WORKSPACE</div>

        <nav>
            <?php foreach ($nav as $key => $symbol):
                if ($rolePages !== null && !in_array($key, $rolePages, true)) continue;
                if (in_array($key, ['users', 'audit']) && $_SESSION['role'] !== 'owner') continue;
            ?>
                <a class="<?= $page === $key ? 'selected' : '' ?>" href="?page=<?= h($key) ?>">
                    <span class="nav-symbol"><?= $symbol ?></span>
                    <?= ucfirst($key) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-bottom" style="display:none;">
            <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
            <div>
                <strong><?= h($_SESSION['name']) ?></strong>
                <small><?= h(ucfirst($_SESSION['role'])) ?></small>
            </div>
            <a href="logout.php" title="Sign out">↪</a>
        </div>
    </aside>

    <!-- ── Main area ─────────────────────────────────────────────────────── -->
    <main>
        <header class="topbar">
            <div>
                <span class="breadcrumb">Workspace / </span><?= ucfirst($page) ?>
            </div>
            <div class="top-right">
                <span class="date" id="live-date"></span>
                <button class="dark-toggle" id="darkToggle" title="Toggle dark mode" onclick="toggleDarkMode()">🌙</button>
                <div style="display:flex;align-items:center;gap:10px;border-left:1px solid var(--border);padding-left:20px;">
                    <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                    <div style="line-height:1.3;">
                        <strong style="display:block;font-size:13px;color:var(--ink);"><?= h($_SESSION['name']) ?></strong>
                        <small style="font-size:11px;color:var(--muted);font-weight:500;"><?= h(ucfirst($_SESSION['role'])) ?></small>
                    </div>
                    <a href="logout.php" title="Sign out" style="font-size:20px;color:var(--muted);text-decoration:none;margin-left:4px;">↪</a>
                </div>
            </div>
        </header>

        <div class="content">

            <!-- Page heading -->
            <div class="heading">
                <div>
                    <div class="eyebrow">MATET MOTORCYCLE PARTS</div>
                    <h1><?= $pageTitles[$page] ?></h1>
                    <p><?= $pageSubtitles[$page] ?></p>
                </div>
                <?php if ($page === 'products' && !$readOnly): ?>
                    <button class="btn primary" onclick="openModal('productModal');resetProduct()">＋ Add Product</button>
                <?php endif; ?>
            </div>

            <!-- Flash / error messages -->
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="success"><?= h($_SESSION['flash']) ?></div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert"><?= h($err) ?></div>
            <?php endif; ?>

            <!-- ── Page content ──────────────────────────────────── -->
            <?php require __DIR__ . '/src/pages/' . $page . '.php'; ?>

        </div><!-- /.content -->
    </main>
</div><!-- /.layout -->

<!-- ── Modals ───────────────────────────────────────────────────────────────── -->
<?php if ($page === 'products' && !$readOnly): ?>
    <?php require __DIR__ . '/src/pages/modal_product.php'; ?>
<?php endif; ?>
<?php if (!$readOnly): ?>
    <?php require __DIR__ . '/src/pages/modal_category.php'; ?>
<?php endif; ?>
<script src="assets/app.js?v=3"></script>
</body>
</html>
