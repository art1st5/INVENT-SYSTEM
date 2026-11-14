<?php
require 'config.php';
auth();
$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'products', 'categories', 'suppliers', 'transactions', 'deliveries', 'reports', 'users', 'audit', 'catalog'];
if (!in_array($page, $allowed, true)) $page = 'dashboard';
if (in_array($page, ['users', 'audit'], true)) owner();
function logit($pdo, $action, $details = '') {
    $q = $pdo->prepare('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)');
    $q->execute([$_SESSION['uid'], $action, $details]);
}
function money($v) {
    return '₱' . number_format((float)$v, 2);
}
function getall($pdo, $sql) {
    return $pdo->query($sql)->fetchAll();
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkcsrf();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'save_product') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $cost = (float)($_POST['cost'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 0);
            $reorder = (int)($_POST['reorder'] ?? 5);
            $discount = (float)($_POST['discount'] ?? 0);
            $image = trim($_POST['image'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $catMode = $_POST['category_mode'] ?? 'existing';
            if ($catMode === 'new') {
                $newCatName = trim($_POST['new_category_name'] ?? '');
                if (!$newCatName) throw new Exception('Category name is required when adding a new category.');
                $q = $pdo->prepare('INSERT INTO categories(category_name,description) VALUES(?,?)');
                $q->execute([$newCatName, trim($_POST['new_category_desc'] ?? '')]);
                $cat = (int)$pdo->lastInsertId();
            } else {
                $cat = (int)($_POST['category'] ?? 0);
            }
            $supplierMode = $_POST['supplier_mode'] ?? 'existing';
            if ($supplierMode === 'new') {
                $newSupName = trim($_POST['new_supplier_name'] ?? '');
                if (!$newSupName) throw new Exception('Supplier name is required when adding a new supplier.');
                $q = $pdo->prepare('INSERT INTO suppliers(supplier_name,contact_num,address) VALUES(?,?,?)');
                $q->execute([$newSupName, trim($_POST['new_supplier_contact'] ?? ''), trim($_POST['new_supplier_address'] ?? '')]);
                $sup = (int)$pdo->lastInsertId();
            } else {
                $sup = (int)($_POST['supplier'] ?? 0);
            }
            if (!$name || !$cat || !$sup || min($price, $cost, $qty, $reorder, $discount) < 0 || $discount > 100) throw new Exception('Check the product fields and values.');
            if ($image && !filter_var($image, FILTER_VALIDATE_URL)) throw new Exception('Image must be a valid URL.');
            if ($id) {
                $q = $pdo->prepare('UPDATE products SET product_name=?,category_id=?,supplier_id=?,selling_price=?,product_cost=?,product_quantity=?,reorder_level=?,product_discount=?,image_url=?,description=? WHERE product_id=?');
                $q->execute([$name, $cat, $sup, $price, $cost, $qty, $reorder, $discount, $image, $desc, $id]);
            } else {
                $q = $pdo->prepare('INSERT INTO products(product_name,category_id,supplier_id,selling_price,product_cost,product_quantity,reorder_level,product_discount,image_url,description) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $q->execute([$name, $cat, $sup, $price, $cost, $qty, $reorder, $discount, $image, $desc]);
                $id = $pdo->lastInsertId();
            }
            logit($pdo, 'Save product', "Product #$id: $name");
            flash('Product saved successfully.');
            go('products');
        }
        if ($act === 'archive_product' || $act === 'restore_product') {
            $id = (int)$_POST['id'];
            $q = $pdo->prepare('UPDATE products SET active=? WHERE product_id=?');
            $q->execute([$act === 'restore_product' ? 1 : 0, $id]);
            logit($pdo, $act, "Product #$id");
            flash('Product status updated.');
            go('products');
        }
        if ($act === 'save_category') {
            owner();
            $name = trim($_POST['name'] ?? '');
            if (!$name) throw new Exception('Category name required');
            $q = $pdo->prepare('INSERT INTO categories(category_name,description) VALUES(?,?)');
            $q->execute([$name, trim($_POST['description'] ?? '')]);
            logit($pdo, 'Add category', $name);
            flash('Category added.');
            go('categories');
        }
        if ($act === 'edit_category') {
            owner();
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if (!$id || !$name) throw new Exception('Category ID and name required.');
            $q = $pdo->prepare('UPDATE categories SET category_name=?,description=? WHERE category_id=?');
            $q->execute([$name, trim($_POST['description'] ?? ''), $id]);
            logit($pdo, 'Edit category', "Category #$id: $name");
            flash('Category updated.');
            go('categories');
        }
        if ($act === 'delete_category') {
            owner();
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Category ID required.');
            $count = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE category_id=' . (int)$id)->fetchColumn();
            if ($count > 0) throw new Exception("Cannot delete: $count product(s) are still using this category. Reassign them first.");
            $q = $pdo->prepare('DELETE FROM categories WHERE category_id=?');
            $q->execute([$id]);
            logit($pdo, 'Delete category', "Category #$id");
            flash('Category deleted.');
            go('categories');
        }
        if ($act === 'save_supplier') {
            owner();
            $name = trim($_POST['name'] ?? '');
            if (!$name) throw new Exception('Supplier name required');
            $q = $pdo->prepare('INSERT INTO suppliers(supplier_name,contact_num,address) VALUES(?,?,?)');
            $q->execute([$name, trim($_POST['contact'] ?? ''), trim($_POST['address'] ?? '')]);
            logit($pdo, 'Add supplier', $name);
            flash('Supplier added.');
            go('suppliers');
        }
        if ($act === 'sale') {
            $id = (int)($_POST['product'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 0);
            if ($qty < 1) throw new Exception('Quantity must be at least one.');
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT * FROM products WHERE product_id=? AND active=1 FOR UPDATE');
            $q->execute([$id]);
            $pr = $q->fetch();
            if (!$pr || $pr['product_quantity'] < $qty) throw new Exception('Product unavailable or insufficient stock.');
            $unit = (float)$pr['selling_price'];
            $discount = round($unit * $qty * (float)$pr['product_discount'] / 100, 2);
            $total = round($unit * $qty - $discount, 2);
            $q = $pdo->prepare('INSERT INTO transactions(admin_id,total_discount,total_amount) VALUES(?,?,?)');
            $q->execute([$_SESSION['uid'], $discount, $total]);
            $tid = $pdo->lastInsertId();
            $q = $pdo->prepare('INSERT INTO product_transactions(transaction_id,product_id,selling_price,product_quantity,product_cost,discount_amount,subamount) VALUES(?,?,?,?,?,?,?)');
            $q->execute([$tid, $id, $unit, $qty, $pr['product_cost'], $discount, $total]);
            $q = $pdo->prepare('UPDATE products SET product_quantity=product_quantity-? WHERE product_id=?');
            $q->execute([$qty, $id]);
            logit($pdo, 'Record cash sale', "Sale #$tid product #$id quantity $qty");
            $pdo->commit();
            flash('Cash sale recorded. Stock updated automatically.');
            go('transactions');
        }
        if ($act === 'delivery') {
            $id = (int)($_POST['product'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 0);
            $unit = (float)($_POST['unit_cost'] ?? 0);
            $status = $_POST['status'] ?? 'received';
            $date = $_POST['date'] ?? date('Y-m-d');
            if ($qty < 1 || $unit < 0 || !in_array($status, ['received', 'delivering'], true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new Exception('Invalid delivery details.');
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT * FROM products WHERE product_id=? FOR UPDATE');
            $q->execute([$id]);
            $pr = $q->fetch();
            if (!$pr || !$pr['active']) throw new Exception('Active product not found');
            $unit = (float)$pr['product_cost'];
            $total = round($unit * $qty, 2);
            $q = $pdo->prepare('INSERT INTO deliveries(supplier_id,admin_id,date_received,delivery_date,delivery_status,total_amount) VALUES(?,?,NOW(),?,?,?)');
            $q->execute([$pr['supplier_id'], $_SESSION['uid'], $date, $date, $status, $total]);
            $did = $pdo->lastInsertId();
            if ($status === 'delivering') {
                $pdo->prepare('UPDATE deliveries SET date_received=NULL WHERE delivery_id=?')->execute([$did]);
            }
            $q = $pdo->prepare('INSERT INTO delivery_details(delivery_id,product_id,quantity_received,unit_cost,subtotal) VALUES(?,?,?,?,?)');
            $q->execute([$did, $id, $qty, $unit, $total]);
            if ($status === 'received') {
                $pdo->prepare('UPDATE products SET product_quantity=product_quantity+?,product_cost=? WHERE product_id=?')->execute([$qty, $unit, $id]);
            }
            logit($pdo, 'Record delivery', "Delivery #$did product #$id");
            $pdo->commit();
            flash('Delivery recorded.');
            go('deliveries');
        }
        if ($act === 'receive_delivery') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT * FROM deliveries WHERE delivery_id=? AND delivery_status='delivering' FOR UPDATE");
            $q->execute([$id]);
            if (!$q->fetch()) throw new Exception('Delivery is not pending.');
            $rows = $pdo->prepare('SELECT * FROM delivery_details WHERE delivery_id=?');
            $rows->execute([$id]);
            foreach ($rows as $line) {
                $pdo->prepare('UPDATE products SET product_quantity=product_quantity+?,product_cost=? WHERE product_id=?')->execute([$line['quantity_received'], $line['unit_cost'], $line['product_id']]);
            }
            $pdo->prepare("UPDATE deliveries SET delivery_status='received',date_received=NOW() WHERE delivery_id=?")->execute([$id]);
            logit($pdo, 'Receive delivery', "Delivery #$id");
            $pdo->commit();
            flash('Delivery received and stock updated.');
            go('deliveries');
        }
        if ($act === 'add_user') {
            owner();
            $name = trim($_POST['name'] ?? '');
            $pass = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'assistant';
            if (strlen($name) < 3 || strlen($pass) < 10 || !in_array($role, ['owner', 'assistant'], true)) throw new Exception('Invalid account details.');
            $pdo->prepare('INSERT INTO users(user_name,user_pw,role) VALUES(?,?,?)')->execute([$name, password_hash($pass, PASSWORD_DEFAULT), $role]);
            logit($pdo, 'Create user', $name);
            flash('User created.');
            go('users');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e instanceof PDOException ? 'Database error. Check for duplicate names or invalid references.' : $e->getMessage();
    }
}
$cats = getall($pdo, 'SELECT * FROM categories ORDER BY category_name');
$sups = getall($pdo, 'SELECT * FROM suppliers ORDER BY supplier_name');
$products = getall($pdo, 'SELECT p.*,c.category_name,s.supplier_name FROM products p JOIN categories c ON c.category_id=p.category_id JOIN suppliers s ON s.supplier_id=p.supplier_id ORDER BY p.active DESC,p.product_name');
$active = array_values(array_filter($products, fn($x) => $x['active']));
$low = array_values(array_filter($active, fn($x) => $x['product_quantity'] <= $x['reorder_level']));
$sales = getall($pdo, 'SELECT t.*,u.user_name,p.product_name,pt.product_quantity,pt.selling_price,pt.product_cost,pt.discount_amount,pt.subamount FROM transactions t JOIN users u ON u.user_id=t.admin_id JOIN product_transactions pt ON pt.transaction_id=t.transaction_id JOIN products p ON p.product_id=pt.product_id ORDER BY t.transaction_id DESC LIMIT 100');
$deliveries = getall($pdo, 'SELECT d.*,u.user_name,s.supplier_name,p.product_name,dd.quantity_received FROM deliveries d JOIN users u ON u.user_id=d.admin_id JOIN suppliers s ON s.supplier_id=d.supplier_id JOIN delivery_details dd ON dd.delivery_id=d.delivery_id JOIN products p ON p.product_id=dd.product_id ORDER BY d.delivery_id DESC LIMIT 100');
$nav = ['dashboard' => 'LayoutDashboard', 'products' => 'Package', 'categories' => 'Tags', 'suppliers' => 'Truck', 'transactions' => 'Receipt', 'deliveries' => 'PackageCheck', 'reports' => 'ChartNoAxesColumn', 'catalog' => 'Store', 'users' => 'Users', 'audit' => 'History'];
?>



<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Matet Inventory</title>
    <link rel="stylesheet" href="assets/style.css">
    <script>
        // Apply theme before paint to avoid flash
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>
    <div class="layout">
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
                <?php foreach($nav as $key=>$icon):
                    if(in_array($key,['users','audit'])&&$_SESSION['role']!=='owner')continue;
                ?>
                    <a class="<?= $page===$key?'selected':'' ?>" href="?page=<?=h($key)?>">
                        <span class="nav-symbol"><?= ['dashboard'=>'▦','products'=>'▣','categories'=>'▤','suppliers'=>'♧','transactions'=>'▧','deliveries'=>'⇩','reports'=>'▥','catalog'=>'▦','users'=>'♙','audit'=>'◷'][$key] ?></span>
                        <?=ucfirst($key)?>
                    </a>
                <?php endforeach;?>
            </nav>
            <div class="sidebar-bottom">
                <div class="avatar"><?=strtoupper(substr($_SESSION['name'],0,1))?></div>
                <div>
                    <strong><?=h($_SESSION['name'])?></strong>
                    <small><?=h(ucfirst($_SESSION['role']))?></small>
                </div>
                <a href="logout.php" title="Sign out">↪</a>
            </div>
        </aside>
        <main>
            <header class="topbar">
                <div>
                    <span class="breadcrumb">Workspace / </span><?=ucfirst($page)?>
                </div>
                <div class="top-right">
                    <span class="date" id="live-date"></span>
                    <button class="dark-toggle" id="darkToggle" title="Toggle dark mode" onclick="toggleDarkMode()">🌙</button>
                    <span class="avatar small"><?=strtoupper(substr($_SESSION['name'],0,1))?></span>
                </div>
            </header>
            <div class="content">
                <div class="heading">
                    <div>
                        <div class="eyebrow">MATET MOTORCYCLE PARTS</div>
                        <h1><?= ['dashboard'=>'Dashboard Overview','products'=>'Product Inventory','categories'=>'Product Categories','suppliers'=>'Suppliers','transactions'=>'Sales Transactions','deliveries'=>'Stock Deliveries','reports'=>'Business Reports','catalog'=>'Product Catalog','users'=>'User Management','audit'=>'Activity Log'][$page] ?></h1>
                        <p><?= ['dashboard'=>"Here's what's happening with your store today.",'products'=>'Manage products, pricing, and stock levels.','categories'=>'Organize your inventory into categories.','suppliers'=>'Manage your product sources.','transactions'=>'Record in-store cash sales and track transactions.','deliveries'=>'Receive stock from your suppliers.','reports'=>'Sales performance and inventory insights.','catalog'=>'Customer-facing product and availability preview.','users'=>'Manage staff access to the system.','audit'=>'Track important actions performed in the system.'][$page] ?></p>
                    </div>
                    <?php if($page==='products'):?>
                        <button class="btn primary" onclick="openModal('productModal');resetProduct()">＋ Add Product</button>
                    <?php endif;?>
                </div>
                <?php if(!empty($_SESSION['flash'])):?>
                    <div class="success"><?=h($_SESSION['flash'])?></div>
                    <?php unset($_SESSION['flash']);
                    endif;
                    if($err):?>
                    <div class="alert"><?=h($err)?></div>
                <?php endif;?>
                <?php if($page==='dashboard'):
                    $today=(float)$pdo->query('SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE DATE(transaction_date)=CURDATE()')->fetchColumn();
                ?>
                    <div class="stats">
                        <div class="stat">
                            <span>Total Products</span>
                            <strong><?=count($active)?></strong>
                            <small>Active products in inventory</small>
                            <div class="stat-icon">▣</div>
                        </div>
                        <div class="stat">
                            <span>Inventory Units</span>
                            <strong><?=number_format(array_sum(array_column($active,'product_quantity')))?></strong>
                            <small>Available units in stock</small>
                            <div class="stat-icon">▥</div>
                        </div>
                        <div class="stat">
                            <span>Low Stock Alerts</span>
                            <strong><?=count($low)?></strong>
                            <small>Need attention or restocking</small>
                            <div class="stat-icon warn">!</div>
                        </div>
                        <div class="stat">
                            <span>Today's Sales</span>
                            <strong><?=money($today)?></strong>
                            <small>Recorded cash transactions</small>
                            <div class="stat-icon">₱</div>
                        </div>
                    </div>
                    <div class="two-col">
                        <section class="panel">
                            <div class="panel-title">
                                <div>
                                    <h2>Low Stock Products</h2>
                                    <p>Products that need to be restocked</p>
                                </div>
                                <a href="?page=products">View all →</a>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>PRODUCT</th>
                                            <th>IN STOCK</th>
                                            <th>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach(array_slice($low,0,6) as $pr):?>
                                            <tr>
                                                <td>
                                                    <strong><?=h($pr['product_name'])?></strong>
                                                    <small><?=h($pr['category_name'])?></small>
                                                </td>
                                                <td><?=h($pr['product_quantity'])?></td>
                                                <td>
                                                    <span class="pill <?= $pr['product_quantity']==0?'danger':'warning' ?>"><?= $pr['product_quantity']==0?'Out of stock':'Low stock' ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                        if(!$low):?>
                                            <tr>
                                                <td colspan="3" class="empty">All active products are above reorder levels.</td>
                                            </tr>
                                        <?php endif;?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        <section class="panel">
                            <div class="panel-title">
                                <div>
                                    <h2>Recent Sales</h2>
                                    <p>Latest in-store transactions</p>
                                </div>
                                <a href="?page=transactions">View all →</a>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>PRODUCT</th>
                                            <th>DATE</th>
                                            <th>AMOUNT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach(array_slice($sales,0,6) as $s):?>
                                            <tr>
                                                <td>
                                                    <strong><?=h($s['product_name'])?></strong>
                                                    <small>Qty: <?=h($s['product_quantity'])?></small>
                                                </td>
                                                <td><?=date('M j',strtotime($s['transaction_date']))?></td>
                                                <td><strong><?=money($s['total_amount'])?></strong></td>
                                            </tr>
                                        <?php endforeach;
                                        if(!$sales):?>
                                            <tr>
                                                <td colspan="3" class="empty">No sales recorded yet.</td>
                                            </tr>
                                        <?php endif;?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                <?php elseif($page==='products'):?>
                    <section class="panel">
                        <div class="toolbar">
                            <div class="searchbox">⌕ <input id="search" placeholder="Search products, category, supplier..." oninput="filterProducts()"></div>
                            <select id="statusFilter" onchange="filterProducts()">
                                <option value="all">All products</option>
                                <option value="active">Active</option>
                                <option value="low">Low stock</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="table-wrap">
                            <table id="productTable">
                                <thead>
                                    <tr>
                                        <th>PRODUCT</th>
                                        <th>CATEGORY</th>
                                        <th>SUPPLIER</th>
                                        <th>PRICE</th>
                                        <th>STOCK</th>
                                        <th>STATUS</th>
                                        <th>ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($products as $pr):
                                        $status=!$pr['active']?'archived':($pr['product_quantity']<=$pr['reorder_level']?'low':'active');
                                    ?>
                                        <tr data-status="<?=$status?>">
                                            <td>
                                                <div class="product-cell">
                                                    <div class="product-thumb">
                                                        <?php if($pr['image_url']):?>
                                                            <img src="<?=h($pr['image_url'])?>" alt="" onerror="this.style.display='none'" />
                                                        <?php else:?>▣<?php endif;?>
                                                    </div>
                                                    <div>
                                                        <strong><?=h($pr['product_name'])?></strong>
                                                        <small>#PRD-<?=str_pad($pr['product_id'],4,'0',STR_PAD_LEFT)?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?=h($pr['category_name'])?></td>
                                            <td><?=h($pr['supplier_name'])?></td>
                                            <td><strong><?=money($pr['selling_price'])?></strong></td>
                                            <td><?=h($pr['product_quantity'])?> units</td>
                                            <td>
                                                <span class="pill <?=$status==='low'?($pr['product_quantity']==0?'danger':'warning'):($status==='archived'?'muted':'good')?>"><?= $status==='low'?($pr['product_quantity']==0?'Out of stock':'Low stock'):ucfirst($status) ?></span>
                                            </td>
                                            <td>
                                                <div class="actions">
                                                    <button title="Edit product" onclick='editProduct(<?=h(json_encode($pr,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP))?>)'>✎</button>
                                                    <form method="post" onsubmit="return confirm('Change this product status?')">
                                                        <input type="hidden" name="csrf" value="<?=h(csrf())?>">
                                                        <input type="hidden" name="id" value="<?=$pr['product_id']?>">
                                                        <input type="hidden" name="action" value="<?=$pr['active']?'archive_product':'restore_product'?>">
                                                        <button title="<?=$pr['active']?'Archive':'Restore'?>"><?=$pr['active']?'⊘':'↺'?></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach;?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php elseif($page==='categories'||$page==='suppliers'):?>
                    <div class="two-col">
                        <section class="panel">
                            <div class="panel-title">
                                <h2><?=ucfirst($page)?> List</h2>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>NAME</th>
                                        <th>DETAILS</th>
                                        <th>PRODUCTS</th>
                                        <?php if($page==='categories'):?>
                                            <th>ACTIONS</th>
                                        <?php endif;?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($page==='categories'?$cats:$sups as $item):?>
                                        <?php if($page==='categories'){
                                            $itemCount=array_sum(array_map(fn($p)=>(int)$p['category_id']===(int)$item['category_id']?1:0,$products));
                                        }else{
                                            $itemCount=array_sum(array_map(fn($p)=>(int)$p['supplier_id']===(int)$item['supplier_id']?1:0,$products));
                                        }?>
                                        <tr>
                                            <td>
                                                <?php if($page==='categories'):?>
                                                    <a class="category-link" href="?page=categories&category_id=<?=$item['category_id']?>"><?=h($item['category_name'])?> →</a>
                                                <?php else:?>
                                                    <a class="category-link" href="?page=suppliers&supplier_id=<?=$item['supplier_id']?>"><?=h($item['supplier_name'])?> →</a>
                                                <?php endif;?>
                                            </td>
                                            <td><?=h($page==='categories'?$item['description']:$item['contact_num'])?></td>
                                            <td>
                                                <span class="pill <?= $itemCount>0?'good':'muted' ?>"><?= $itemCount ?> product(s)</span>
                                            </td>
                                            <?php if($page==='categories'):?>
                                                <td>
                                                    <div class="actions">
                                                        <button title="Edit category" onclick='editCategory(<?=h(json_encode($item,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP))?>)'>✎</button>
                                                        <form method="post" onsubmit="return confirm('Delete this category? Products using it must be reassigned first.')">
                                                            <input type="hidden" name="csrf" value="<?=h(csrf())?>">
                                                            <input type="hidden" name="id" value="<?=$item['category_id']?>">
                                                            <input type="hidden" name="action" value="delete_category">
                                                            <button title="Delete category">⊘</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            <?php endif;?>
                                        </tr>
                                    <?php endforeach;?>
                                </tbody>
                            </table>
                        </section>
                        <?php if($_SESSION['role']==='owner'):?>
                            <section class="panel form-panel">
                                <h2>Add <?= $page==='categories'?'Category':'Supplier' ?></h2>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?=h(csrf())?>">
                                    <input type="hidden" name="action" value="<?=$page==='categories'?'save_category':'save_supplier'?>">
                                    <label>Name<input name="name" required></label>
                                    <?php if($page==='suppliers'):?>
                                        <label>Contact Number<input name="contact"></label>
                                        <label>Address<textarea name="address"></textarea></label>
                                    <?php else:?>
                                        <label>Description<textarea name="description"></textarea></label>
                                    <?php endif;?>
                                    <button class="btn primary">Save</button>
                                </form>
                            </section>
                        <?php endif;?>
                    </div>
                    <?php if($page==='categories' && isset($_GET['category_id'])):
                        $selectedCategory=(int)$_GET['category_id'];
                        $selectedCat=null;
                        foreach($cats as $c){
                            if((int)$c['category_id']===$selectedCategory){
                                $selectedCat=$c;
                                break;
                            }
                        }
                    ?>
                        <section class="panel category-results">
                            <div class="panel-title">
                                <div>
                                    <h2><?= $selectedCat?'Products in '.h($selectedCat['category_name']):'Category not found' ?></h2>
                                    <p>Click another category to view its products.</p>
                                </div>
                                <a href="?page=categories">Clear selection</a>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>PRODUCT</th>
                                            <th>SUPPLIER</th>
                                            <th>SELLING PRICE</th>
                                            <th>UNIT COST</th>
                                            <th>STOCK</th>
                                            <th>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $found=false;
                                        foreach($products as $pr):
                                            if((int)$pr['category_id']!==$selectedCategory)continue;
                                            $found=true;
                                        ?>
                                            <tr>
                                                <td><strong><?=h($pr['product_name'])?></strong></td>
                                                <td><?=h($pr['supplier_name'])?></td>
                                                <td><?=money($pr['selling_price'])?></td>
                                                <td><?=money($pr['product_cost'])?></td>
                                                <td><?=h($pr['product_quantity'])?></td>
                                                <td><?= $pr['active']?'Active':'Archived' ?></td>
                                            </tr>
                                        <?php endforeach;
                                        if(!$found):?>
                                            <tr>
                                                <td colspan="6" class="empty">No products in this category.</td>
                                            </tr>
                                        <?php endif;?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endif;?>
                    <?php if($page==='suppliers' && isset($_GET['supplier_id'])):
                        $selectedSupplier=(int)$_GET['supplier_id'];
                        $selectedSup=null;
                        foreach($sups as $s){
                            if((int)$s['supplier_id']===$selectedSupplier){
                                $selectedSup=$s;
                                break;
                            }
                        }
                    ?>
                        <section class="panel category-results">
                            <div class="panel-title">
                                <div>
                                    <h2><?= $selectedSup?'Products from '.h($selectedSup['supplier_name']):'Supplier not found' ?></h2>
                                    <p>Click another supplier to view their products.</p>
                                </div>
                                <a href="?page=suppliers">Clear selection</a>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>PRODUCT</th>
                                            <th>CATEGORY</th>
                                            <th>SELLING PRICE</th>
                                            <th>UNIT COST</th>
                                            <th>STOCK</th>
                                            <th>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $found=false;
                                        foreach($products as $pr):
                                            if((int)$pr['supplier_id']!==$selectedSupplier)continue;
                                            $found=true;
                                        ?>
                                            <tr>
                                                <td><strong><?=h($pr['product_name'])?></strong></td>
                                                <td><?=h($pr['category_name'])?></td>
                                                <td><?=money($pr['selling_price'])?></td>
                                                <td><?=money($pr['product_cost'])?></td>
                                                <td><?=h($pr['product_quantity'])?></td>
                                                <td><?= $pr['active']?'Active':'Archived' ?></td>
                                            </tr>
                                        <?php endforeach;
                                        if(!$found):?>
                                            <tr>
                                                <td colspan="6" class="empty">No products from this supplier.</td>
                                            </tr>
                                        <?php endif;?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endif;?>

                <?php elseif($page === 'transactions' || $page === 'deliveries'): ?>
                    <div class="two-col">
                        <section class="panel form-panel">
                            <h2><?= $page === 'transactions' ? 'Record Cash Sale' : 'Record Supplier Delivery' ?></h2>
                            <p><?= $page === 'transactions' ? 'Cash payment must be settled before products are released.' : 'Received deliveries automatically increase stock.' ?></p>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                <input type="hidden" name="action" value="<?= $page === 'transactions' ? 'sale' : 'delivery' ?>">
                                <label>Product
                                    <select name="product" required id="deliveryProductSelect" onchange="autoFillUnitCost()">
                                        <?php foreach($active as $pr): ?>
                                            <option value="<?= $pr['product_id'] ?>" data-cost="<?= $pr['product_cost'] ?>">
                                                <?= h($pr['product_name']) ?> (<?= $pr['product_quantity'] ?> in stock)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Quantity
                                    <input name="quantity" type="number" min="1" value="1" required>
                                </label>
                                <?php if($page === 'deliveries'): ?>
                                    <label>Unit Cost (₱)
                                        <input name="unit_cost" id="deliveryUnitCost" type="number" step="0.01" min="0" required>
                                    </label>
                                    <label>Delivery Date
                                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                                    </label>
                                    <label>Status
                                        <select name="status">
                                            <option value="received">Received</option>
                                            <option value="delivering">Delivering</option>
                                        </select>
                                    </label>
                                <?php endif; ?>
                                <button class="btn primary"><?= $page === 'transactions' ? 'Record Paid Sale' : 'Save Delivery' ?></button>
                            </form>
                        </section>
                        <section class="panel">
                            <div class="panel-title">
                                <h2>Recent <?= $page === 'transactions' ? 'Transactions' : 'Deliveries' ?></h2>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID / PRODUCT</th>
                                            <th>QTY</th>
                                            <th>AMOUNT</th>
                                            <?php if($page === 'transactions'): ?>
                                                <th>COST</th>
                                                <th>PROFIT</th>
                                                <th>CASHIER</th>
                                            <?php endif; ?>
                                            <?php if($page === 'deliveries'): ?>
                                                <th>STATUS</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($page === 'transactions' ? $sales : $deliveries as $r): ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?= h($page === 'transactions' ? $r['transaction_id'] : $r['delivery_id']) ?> · <?= h($r['product_name']) ?></strong>
                                                    <small><?= h($page === 'transactions' ? $r['transaction_date'] : $r['delivery_date']) ?></small>
                                                </td>
                                                <td><?= h($page === 'transactions' ? $r['product_quantity'] : $r['quantity_received']) ?></td>
                                                <td><?= money($r['total_amount']) ?></td>
                                                <?php if($page === 'transactions'):
                                                    $lineCost = round((float)$r['product_cost'] * (int)$r['product_quantity'], 2);
                                                    $lineProfit = round((float)$r['total_amount'] - $lineCost, 2);
                                                ?>
                                                    <td><?= money($lineCost) ?></td>
                                                    <td><strong><?= money($lineProfit) ?></strong></td>
                                                    <td><?= h($r['user_name']) ?></td>
                                                <?php endif; ?>
                                                <?php if($page === 'deliveries'): ?>
                                                    <td>
                                                        <?php if($r['delivery_status'] === 'delivering'): ?>
                                                            <form method="post">
                                                                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                                                <input type="hidden" name="action" value="receive_delivery">
                                                                <input type="hidden" name="id" value="<?= $r['delivery_id'] ?>">
                                                                <button class="btn mini">Mark received</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="pill good">Received</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                <?php elseif($page === 'reports'):
                    $period = $_GET['period'] ?? 'all';
                    $validPeriods = ['all', 'yearly', 'monthly', 'weekly', 'daily'];
                    if (!in_array($period, $validPeriods, true)) $period = 'all';
                    $dateWhere = '';
                    if ($period === 'yearly')
                        $dateWhere = ' AND YEAR(t.transaction_date)=YEAR(CURDATE())';
                    elseif ($period === 'monthly')
                        $dateWhere = ' AND YEAR(t.transaction_date)=YEAR(CURDATE()) AND MONTH(t.transaction_date)=MONTH(CURDATE())';
                    elseif ($period === 'weekly')
                        $dateWhere = ' AND YEARWEEK(t.transaction_date,1)=YEARWEEK(CURDATE(),1)';
                    elseif ($period === 'daily')
                        $dateWhere = ' AND DATE(t.transaction_date)=CURDATE()';
                    $revenue    = (float)$pdo->query("SELECT COALESCE(SUM(t.total_amount),0) FROM transactions t WHERE 1=1" . $dateWhere)->fetchColumn();
                    $totalCost  = (float)$pdo->query("SELECT COALESCE(SUM(pt.product_cost*pt.product_quantity),0) FROM product_transactions pt JOIN transactions t ON t.transaction_id=pt.transaction_id WHERE 1=1" . $dateWhere)->fetchColumn();
                    $totalProfit = round($revenue - $totalCost, 2);
                    $best = getall($pdo, "SELECT p.product_name,SUM(pt.product_quantity) AS sold,SUM(pt.subamount) AS revenue,SUM(pt.product_cost*pt.product_quantity) AS cost,SUM(pt.subamount-pt.product_cost*pt.product_quantity) AS profit FROM product_transactions pt JOIN transactions t ON t.transaction_id=pt.transaction_id JOIN products p ON p.product_id=pt.product_id WHERE 1=1" . $dateWhere . " GROUP BY pt.product_id,p.product_name ORDER BY sold DESC LIMIT 10");
                    $recentTx = getall($pdo, "SELECT t.*,u.user_name,p.product_name,pt.product_quantity,pt.selling_price,pt.product_cost,pt.subamount FROM transactions t JOIN users u ON u.user_id=t.admin_id JOIN product_transactions pt ON pt.transaction_id=t.transaction_id JOIN products p ON p.product_id=pt.product_id WHERE 1=1" . $dateWhere . " ORDER BY t.transaction_id DESC LIMIT 100");
                ?>
                    <div class="toolbar" style="margin-bottom:20px">
                        <div class="searchbox" style="max-width:none;flex:0 0 auto">
                            <strong style="white-space:nowrap">Filter by period:</strong>
                        </div>
                        <select id="reportPeriod" onchange="changeReportPeriod()" style="border:1px solid var(--border);padding:10px;border-radius:8px;font:inherit">
                            <option value="all"     <?= $period === 'all'     ? 'selected' : '' ?>>All time</option>
                            <option value="yearly"  <?= $period === 'yearly'  ? 'selected' : '' ?>>This year</option>
                            <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>This month</option>
                            <option value="weekly"  <?= $period === 'weekly'  ? 'selected' : '' ?>>This week</option>
                            <option value="daily"   <?= $period === 'daily'   ? 'selected' : '' ?>>Today</option>
                        </select>
                    </div>
                    <div class="stats">
                        <div class="stat">
                            <span>Total Revenue</span>
                            <strong><?= money($revenue) ?></strong>
                            <small>Gross sales amount</small>
                            <div class="stat-icon">₱</div>
                        </div>
                        <div class="stat">
                            <span>Total Cost</span>
                            <strong><?= money($totalCost) ?></strong>
                            <small>Product cost of goods sold</small>
                            <div class="stat-icon">▥</div>
                        </div>
                        <div class="stat">
                            <span>Total Profit</span>
                            <strong style="color:<?= $totalProfit >= 0 ? '#17965c' : '#d34750' ?>"><?= money($totalProfit) ?></strong>
                            <small>Revenue minus cost</small>
                            <div class="stat-icon <?= $totalProfit >= 0 ? '' : 'warn' ?>"><?= $totalProfit >= 0 ? '▲' : '▼' ?></div>
                        </div>
                        <div class="stat">
                            <span>Transactions</span>
                            <strong><?= count($recentTx) ?></strong>
                            <small>Recorded in this period</small>
                            <div class="stat-icon">▧</div>
                        </div>
                    </div>
                    <section class="panel">
                        <div class="panel-title">
                            <h2>Best-Selling Products</h2>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>PRODUCT</th>
                                        <th>UNITS SOLD</th>
                                        <th>REVENUE</th>
                                        <th>COST</th>
                                        <th>PROFIT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($best as $b): ?>
                                        <tr>
                                            <td><?= h($b['product_name']) ?></td>
                                            <td><?= h($b['sold']) ?></td>
                                            <td><?= money($b['revenue']) ?></td>
                                            <td><?= money($b['cost']) ?></td>
                                            <td>
                                                <strong style="color:<?= $b['profit'] >= 0 ? '#17965c' : '#d34750' ?>">
                                                    <?= money($b['profit']) ?>
                                                </strong>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                    if (!$best): ?>
                                        <tr>
                                            <td colspan="5" class="empty">No transactions in this period.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="panel">
                        <div class="panel-title">
                            <h2>Transaction History</h2>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID / PRODUCT</th>
                                        <th>QTY</th>
                                        <th>AMOUNT</th>
                                        <th>COST</th>
                                        <th>PROFIT</th>
                                        <th>CASHIER</th>
                                        <th>DATE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recentTx as $tx):
                                        $txCost   = round((float)$tx['product_cost'] * (int)$tx['product_quantity'], 2);
                                        $txProfit = round((float)$tx['subamount'] - $txCost, 2);
                                    ?>
                                        <tr>
                                            <td>
                                                <strong>#<?= h($tx['transaction_id']) ?> · <?= h($tx['product_name']) ?></strong>
                                            </td>
                                            <td><?= h($tx['product_quantity']) ?></td>
                                            <td><?= money($tx['subamount']) ?></td>
                                            <td><?= money($txCost) ?></td>
                                            <td>
                                                <strong style="color:<?= $txProfit >= 0 ? '#17965c' : '#d34750' ?>">
                                                    <?= money($txProfit) ?>
                                                </strong>
                                            </td>
                                            <td><?= h($tx['user_name']) ?></td>
                                            <td><?= h($tx['transaction_date']) ?></td>
                                        </tr>
                                    <?php endforeach;
                                    if (!$recentTx): ?>
                                        <tr>
                                            <td colspan="7" class="empty">No transactions in this period.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                <?php elseif($page === 'catalog'): ?>
                    <div class="catalog-grid">
                        <?php foreach($active as $pr): ?>
                            <div class="catalog-card">
                                <div class="catalog-photo">
                                    <?php if($pr['image_url']): ?>
                                        <img src="<?= h($pr['image_url']) ?>" alt="<?= h($pr['product_name']) ?>">
                                    <?php else: ?>▣<?php endif; ?>
                                </div>
                                <div class="catalog-info">
                                    <small><?= h($pr['category_name']) ?></small>
                                    <h3><?= h($pr['product_name']) ?></h3>
                                    <strong><?= money($pr['selling_price']) ?></strong>
                                    <p>
                                        <span class="pill <?= $pr['product_quantity'] ? 'good' : 'danger' ?>">
                                            <?= $pr['product_quantity'] ? 'Available · ' . $pr['product_quantity'] . ' left' : 'Out of stock' ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif($page === 'users'): ?>
                    <div class="two-col">
                        <section class="panel">
                            <h2>Accounts</h2>
                            <table>
                                <thead>
                                    <tr>
                                        <th>USERNAME</th>
                                        <th>ROLE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach(getall($pdo, 'SELECT user_name,role FROM users ORDER BY user_id') as $u): ?>
                                        <tr>
                                            <td><?= h($u['user_name']) ?></td>
                                            <td><?= h($u['role']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </section>
                        <section class="panel form-panel">
                            <h2>Add Staff Account</h2>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                <input type="hidden" name="action" value="add_user">
                                <label>Username
                                    <input name="name" required>
                                </label>
                                <label>Password (10+ characters)
                                    <input name="password" type="password" minlength="10" required>
                                </label>
                                <label>Role
                                    <select name="role">
                                        <option value="assistant">Assistant</option>
                                        <option value="owner">Owner</option>
                                    </select>
                                </label>
                                <button class="btn primary">Create User</button>
                            </form>
                        </section>
                    </div>

                <?php elseif($page === 'audit'): ?>
                    <section class="panel">
                        <table>
                            <thead>
                                <tr>
                                    <th>WHEN</th>
                                    <th>USER</th>
                                    <th>ACTION</th>
                                    <th>DETAILS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach(getall($pdo, 'SELECT l.*,u.user_name FROM audit_logs l JOIN users u ON u.user_id=l.user_id ORDER BY l.log_id DESC LIMIT 100') as $r): ?>
                                    <tr>
                                        <td><?= h($r['created_at']) ?></td>
                                        <td><?= h($r['user_name']) ?></td>
                                        <td><?= h($r['action']) ?></td>
                                        <td><?= h($r['details']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>

                <?php endif; ?>
            </div><!-- /.content -->
        </main>
    </div><!-- /.layout -->

    <!-- ============================================================
         PRODUCT MODAL
    ============================================================ -->
    <?php if($page === 'products'): ?>
        <div class="modal" id="productModal">
            <div class="modal-card">
                <div class="modal-head">
                    <h2 id="modalTitle">Add Product</h2>
                    <button onclick="closeModal('productModal')">✕</button>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="id" id="p_id">

                    <div class="form-grid">
                        <label class="wide">Product Name
                            <input id="p_name" name="name" required>
                        </label>
                        <label>Selling Price (₱)
                            <input id="p_price" name="price" type="number" min="0" step="0.01" required>
                        </label>
                        <label>Product Cost (₱)
                            <input id="p_cost" name="cost" type="number" min="0" step="0.01" required>
                        </label>
                        <label>Quantity
                            <input id="p_quantity" name="quantity" type="number" min="0" required>
                        </label>
                        <label>Reorder Level
                            <input id="p_reorder" name="reorder" type="number" min="0" value="5" required>
                        </label>
                        <label>Discount (%)
                            <input id="p_discount" name="discount" type="number" min="0" max="100" step="0.01" value="0">
                        </label>
                        <label>Image URL
                            <input id="p_image" name="image" type="url" placeholder="https://...">
                        </label>
                        <label class="wide">Description
                            <textarea id="p_description" name="description"></textarea>
                        </label>
                    </div>

                    <!-- Category Section -->
                    <div class="category-section">
                        <div class="category-section-head">
                            <h3>Category</h3>
                            <div class="category-mode-toggle">
                                <label class="radio-toggle">
                                    <input type="radio" name="category_mode" value="existing" checked onchange="toggleCategoryMode('existing')">
                                    Select existing
                                </label>
                                <label class="radio-toggle">
                                    <input type="radio" name="category_mode" value="new" onchange="toggleCategoryMode('new')">
                                    Add new category
                                </label>
                            </div>
                        </div>
                        <div id="existingCategoryFields">
                            <label class="wide" style="display:block;margin-bottom:15px">Select Category
                                <select id="p_category" name="category" required>
                                    <?php foreach($cats as $c): ?>
                                        <option value="<?= $c['category_id'] ?>"><?= h($c['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="cat-action-row">
                                <button type="button" class="btn mini danger-btn" onclick="removeCategory()">Remove</button>
                                <button type="button" class="btn mini undo-btn" id="undoCategoryBtn" onclick="undoCategoryRemove()" style="display:none">Undo</button>
                                <span class="cat-action-hint" id="catRemoveHint"></span>
                            </div>
                        </div>
                        <div class="form-grid" id="newCategoryFields" style="display:none">
                            <label class="wide">Category Name
                                <input name="new_category_name" placeholder="e.g. Helmets &amp; Gear">
                            </label>
                            <label class="wide">Description
                                <textarea name="new_category_desc" placeholder="Category description (optional)"></textarea>
                            </label>
                        </div>
                    </div>

                    <!-- Supplier Section -->
                    <div class="supplier-section">
                        <div class="supplier-section-head">
                            <h3>Supplier Details</h3>
                            <div class="supplier-mode-toggle">
                                <label class="radio-toggle">
                                    <input type="radio" name="supplier_mode" value="existing" checked onchange="toggleSupplierMode('existing')">
                                    Select existing
                                </label>
                                <label class="radio-toggle">
                                    <input type="radio" name="supplier_mode" value="new" onchange="toggleSupplierMode('new')">
                                    Add new supplier
                                </label>
                            </div>
                        </div>
                        <div id="existingSupplierFields">
                            <label class="wide" style="display:block;margin-bottom:15px">Select Supplier
                                <select id="p_supplier" name="supplier" required>
                                    <?php foreach($sups as $s): ?>
                                        <option value="<?= $s['supplier_id'] ?>"><?= h($s['supplier_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="form-grid" id="newSupplierFields" style="display:none">
                            <label class="wide">Supplier Name
                                <input name="new_supplier_name" placeholder="e.g. ABC Motorcycle Parts">
                            </label>
                            <label>Contact Number
                                <input name="new_supplier_contact" placeholder="e.g. 0917 123 4567">
                            </label>
                            <label class="wide">Address
                                <textarea name="new_supplier_address" placeholder="Supplier address"></textarea>
                            </label>
                        </div>
                    </div>

                    <div class="modal-foot">
                        <button type="button" class="btn" onclick="closeModal('productModal')">Cancel</button>
                        <button class="btn primary">Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         EDIT CATEGORY MODAL
    ============================================================ -->
    <div class="modal" id="categoryEditModal">
        <div class="modal-card">
            <div class="modal-head">
                <h2>Edit Category</h2>
                <button onclick="closeModal('categoryEditModal')">✕</button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="id" id="ce_id">
                <div class="form-panel" style="display:flex;flex-direction:column;gap:16px">
                    <label>Name
                        <input id="ce_name" name="name" required>
                    </label>
                    <label>Description
                        <textarea id="ce_description" name="description"></textarea>
                    </label>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" onclick="closeModal('categoryEditModal')">Cancel</button>
                    <button class="btn primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>
