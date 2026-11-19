<?php
/**
 * src/pages/products.php — Product inventory list page template.
 *
 * Shows a searchable, filterable table of all products with
 * edit and archive/restore action buttons.
 *
 * Variables available: $products, $readOnly
 */
?>

<section class="panel">
    <!-- Toolbar: live search + status filter -->
    <div class="toolbar">
        <div class="searchbox">
            ⌕ <input id="search" placeholder="Search products, category, supplier…" oninput="filterProducts()">
        </div>
        <!-- filterProducts() in app.js hides rows that don't match without a page reload -->
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
                    <?php if ($readOnly): ?>
                        <th>PRODUCT COST</th>
                    <?php else: ?>
                        <th>ACTIONS</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allProducts as $pr):
                    // data-status is read by filterProducts() in app.js
                    $status = !$pr['active'] ? 'archived'
                            : ($pr['product_quantity'] <= $pr['reorder_level'] ? 'low' : 'active');
                ?>
                    <tr data-status="<?= $status ?>">
                        <!-- Product name + thumbnail -->
                        <td>
                            <div class="product-cell">
                                <div class="product-thumb">
                                    <?php if ($pr['image_url']): ?>
                                        <img src="<?= h($pr['image_url']) ?>" alt="" onerror="this.style.display='none'" />
                                    <?php else: ?>▣<?php endif; ?>
                                </div>
                                <div>
                                    <strong><?= h($pr['product_name']) ?></strong>
                                    <small>#PRD-<?= str_pad($pr['product_id'], 4, '0', STR_PAD_LEFT) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= h($pr['category_name']) ?></td>
                        <td><?= h($pr['supplier_name']) ?></td>
                        <td><strong><?= money($pr['selling_price']) ?></strong></td>
                        <td><?= h($pr['product_quantity']) ?> units</td>
                        <td>
                            <span class="pill <?= $status === 'low'
                                ? ($pr['product_quantity'] == 0 ? 'danger' : 'warning')
                                : ($status === 'archived' ? 'muted' : 'good') ?>">
                                <?= $status === 'low'
                                    ? ($pr['product_quantity'] == 0 ? 'Out of stock' : 'Low stock')
                                    : ucfirst($status) ?>
                            </span>
                        </td>
                        <?php if ($readOnly): ?>
                            <td><?= money($pr['product_cost']) ?></td>
                        <?php else: ?>
                            <td>
                                <div class="actions">
                                    <button title="Edit product"
                                        onclick='editProduct(<?= h(json_encode(
                                            $pr,
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_TAG |
                                            JSON_HEX_AMP
                                        )) ?>)'>
                                        ✎
                                    </button>

                                    <form method="post"
                                        onsubmit="return confirm('Change this product status?')">
                                        <input type="hidden" name="csrf"   value="<?= h(csrf()) ?>">
                                        <input type="hidden" name="id"     value="<?= $pr['product_id'] ?>">
                                        <input type="hidden" name="action" value="<?= $pr['active'] ? 'archive_product' : 'restore_product' ?>">
                                        <button title="<?= $pr['active'] ? 'Archive' : 'Restore' ?>">
                                            <?= $pr['active'] ? '⊘' : '↺' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
