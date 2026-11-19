<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Displays inventory summaries, low-stock products and recent sales. Customize stat cards,
 * table presentation and overview queries here.
 */
/**
 * src/pages/dashboard.php — Dashboard overview page template.
 *
 * Shows 4 stat cards (products, units, low-stock, today's revenue)
 * and two side-by-side tables (low-stock products + recent sales).
 *
 * Variables available from queries.php: $active, $low, $sales
 */

// Today's total cash sales revenue — used for the "Today's Sales" stat card
$today = (float)$pdo->query(
    'SELECT COALESCE(SUM(total_amount), 0)
     FROM   transactions
     WHERE  DATE(transaction_date) = CURDATE()'
)->fetchColumn();
?>

<div class="stats">
    <div class="stat">
        <span>Total Products</span>
        <strong><?= count($active) ?></strong>
        <small>Active products in inventory</small>
        <div class="stat-icon">▣</div>
    </div>
    <div class="stat">
        <span>Inventory Units</span>
        <strong><?= number_format(array_sum(array_column($active, 'product_quantity'))) ?></strong>
        <small>Available units in stock</small>
        <div class="stat-icon">▥</div>
    </div>
    <div class="stat">
        <span>Low Stock Alerts</span>
        <strong><?= count($low) ?></strong>
        <small>Need attention or restocking</small>
        <div class="stat-icon warn">!</div>
    </div>
    <div class="stat">
        <span>Today's Sales</span>
        <strong><?= money($today) ?></strong>
        <small>Recorded cash transactions</small>
        <div class="stat-icon">₱</div>
    </div>
</div>

<div class="two-col">

    <!-- Low-stock products (top 6 only) -->
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
                    <?php foreach (array_slice($low, 0, 6) as $pr): ?>
                        <tr>
                            <td>
                                <strong><?= h($pr['product_name']) ?></strong>
                                <small><?= h($pr['category_name']) ?></small>
                            </td>
                            <td><?= h($pr['product_quantity']) ?></td>
                            <td>
                                <span class="pill <?= $pr['product_quantity'] == 0 ? 'danger' : 'warning' ?>">
                                    <?= $pr['product_quantity'] == 0 ? 'Out of stock' : 'Low stock' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach;
                    if (!$low): ?>
                        <tr>
                            <td colspan="3" class="empty">All active products are above reorder levels.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Recent sales (top 6 only) -->
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
                    <?php foreach (array_slice($sales, 0, 6) as $s): ?>
                        <tr>
                            <td>
                                <strong><?= h($s['product_name']) ?></strong>
                                <small>Qty: <?= h($s['product_quantity']) ?></small>
                            </td>
                            <td><?= date('M j', strtotime($s['transaction_date'])) ?></td>
                            <td><strong><?= money($s['total_amount']) ?></strong></td>
                        </tr>
                    <?php endforeach;
                    if (!$sales): ?>
                        <tr>
                            <td colspan="3" class="empty">No sales recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
