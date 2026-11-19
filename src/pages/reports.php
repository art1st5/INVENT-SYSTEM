<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Calculates and displays period-based business reports. Customize default $period,
 * supported date filters and report limits here; keep report totals consistent with
 * transaction details.
 */
/**
 * src/pages/reports.php — Business reports page template.
 *
 * Period filter (all / yearly / monthly / weekly / daily) is applied
 * through $dateWhere — a hardcoded SQL fragment, never built from raw
 * user input, so there is no SQL injection risk.
 *
 * Variables available: $pdo (for the period-scoped queries run here)
 */

// ── Period filter ─────────────────────────────────────────────────────────────
$period       = $_GET['period'] ?? 'daily';
$validPeriods = ['all', 'yearly', 'monthly', 'weekly', 'daily'];
if (!in_array($period, $validPeriods, true)) $period = 'daily';

// $dateWhere is chosen from a fixed set of hardcoded strings — not user input
$dateWhere = '';
if      ($period === 'yearly')  $dateWhere = ' AND YEAR(t.transaction_date)=YEAR(CURDATE())';
elseif  ($period === 'monthly') $dateWhere = ' AND YEAR(t.transaction_date)=YEAR(CURDATE()) AND MONTH(t.transaction_date)=MONTH(CURDATE())';
elseif  ($period === 'weekly')  $dateWhere = ' AND YEARWEEK(t.transaction_date,1)=YEARWEEK(CURDATE(),1)';
elseif  ($period === 'daily')   $dateWhere = ' AND DATE(t.transaction_date)=CURDATE()';

// ── Aggregate totals for stat cards ──────────────────────────────────────────
$revenue     = (float)$pdo->query("SELECT COALESCE(SUM(t.total_amount),0) FROM transactions t WHERE 1=1" . $dateWhere)->fetchColumn();
$totalCost   = (float)$pdo->query("SELECT COALESCE(SUM(pt.product_cost*pt.product_quantity),0) FROM product_transactions pt JOIN transactions t ON t.transaction_id=pt.transaction_id WHERE 1=1" . $dateWhere)->fetchColumn();
$totalProfit = round($revenue - $totalCost, 2);

// ── Best-selling products ─────────────────────────────────────────────────────
$best = getall($pdo,
    "SELECT p.product_name,
            SUM(pt.product_quantity)                            AS sold,
            SUM(pt.subamount)                                   AS revenue,
            SUM(pt.product_cost * pt.product_quantity)          AS cost,
            SUM(pt.subamount - pt.product_cost*pt.product_quantity) AS profit
     FROM   product_transactions pt
     JOIN   transactions t ON t.transaction_id = pt.transaction_id
     JOIN   products     p ON p.product_id     = pt.product_id
     WHERE  1=1" . $dateWhere . "
     GROUP  BY pt.product_id, p.product_name
     ORDER  BY sold DESC
     LIMIT  10");

// ── Full transaction history ──────────────────────────────────────────────────
$recentTx = getall($pdo,
    "SELECT t.*, u.user_name,
            p.product_name, pt.product_quantity,
            pt.selling_price, pt.product_cost, pt.subamount
     FROM   transactions         t
     JOIN   users                u  ON u.user_id        = t.admin_id
     JOIN   product_transactions pt ON pt.transaction_id = t.transaction_id
     JOIN   products             p  ON p.product_id     = pt.product_id
     WHERE  1=1" . $dateWhere . "
     ORDER  BY t.transaction_id DESC
     LIMIT  100");
?>

<!-- Period filter toolbar -->
<div class="toolbar" style="margin-bottom:20px">
    <div class="searchbox" style="max-width:none;flex:0 0 auto">
        <strong style="white-space:nowrap">Filter by period:</strong>
    </div>
    <!-- changeReportPeriod() in app.js reloads the page with the new ?period= value -->
    <select id="reportPeriod" onchange="changeReportPeriod()"
            style="border:1px solid var(--border);padding:10px;border-radius:8px;font:inherit">
        <option value="all"     <?= $period === 'all'     ? 'selected' : '' ?>>All time</option>
        <option value="yearly"  <?= $period === 'yearly'  ? 'selected' : '' ?>>This year</option>
        <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>This month</option>
        <option value="weekly"  <?= $period === 'weekly'  ? 'selected' : '' ?>>This week</option>
        <option value="daily"   <?= $period === 'daily'   ? 'selected' : '' ?>>Today</option>
    </select>
</div>

<!-- Summary stat cards -->
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
        <strong style="color:<?= $totalProfit >= 0 ? 'var(--profit-text)' : 'var(--danger-text)' ?>">
            <?= money($totalProfit) ?>
        </strong>
        <small>Revenue minus cost</small>
        <div class="stat-icon <?= $totalProfit >= 0 ? '' : 'warn' ?>">
            <?= $totalProfit >= 0 ? '▲' : '▼' ?>
        </div>
    </div>
    <div class="stat">
        <span>Transactions</span>
        <strong><?= count($recentTx) ?></strong>
        <small>Recorded in this period</small>
        <div class="stat-icon">▧</div>
    </div>
</div>

<!-- Best-selling products -->
<section class="panel">
    <div class="panel-title"><h2>Best-Selling Products</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>PRODUCT</th><th>UNITS SOLD</th>
                    <th>REVENUE</th><th>COST</th><th>PROFIT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($best as $b): ?>
                    <tr>
                        <td><?= h($b['product_name']) ?></td>
                        <td><?= h($b['sold']) ?></td>
                        <td><?= money($b['revenue']) ?></td>
                        <td><?= money($b['cost']) ?></td>
                        <td>
                            <strong style="color:<?= $b['profit'] >= 0 ? 'var(--profit-text)' : 'var(--danger-text)' ?>">
                                <?= money($b['profit']) ?>
                            </strong>
                        </td>
                    </tr>
                <?php endforeach;
                if (!$best): ?>
                    <tr><td colspan="5" class="empty">No transactions in this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Full transaction history -->
<section class="panel">
    <div class="panel-title"><h2>Transaction History</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID / PRODUCT</th><th>QTY</th><th>AMOUNT</th>
                    <th>COST</th><th>PROFIT</th><th>CASHIER</th><th>DATE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTx as $tx):
                    $txCost   = round((float)$tx['product_cost'] * (int)$tx['product_quantity'], 2);
                    $txProfit = round((float)$tx['subamount'] - $txCost, 2);
                ?>
                    <tr>
                        <td><strong>#<?= h($tx['transaction_id']) ?> · <?= h($tx['product_name']) ?></strong></td>
                        <td><?= h($tx['product_quantity']) ?></td>
                        <td><?= money($tx['subamount']) ?></td>
                        <td><?= money($txCost) ?></td>
                        <td>
                            <strong style="color:<?= $txProfit >= 0 ? 'var(--profit-text)' : 'var(--danger-text)' ?>">
                                <?= money($txProfit) ?>
                            </strong>
                        </td>
                        <td><?= h($tx['user_name']) ?></td>
                        <td><?= h($tx['transaction_date']) ?></td>
                    </tr>
                <?php endforeach;
                if (!$recentTx): ?>
                    <tr><td colspan="7" class="empty">No transactions in this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
