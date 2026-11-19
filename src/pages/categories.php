<?php
/**
 * src/pages/categories.php — Categories page template.
 *
 * Shows the category list with product count badges and edit/delete buttons.
 * Clicking a category name filters and shows only that category's products below.
 *
 * Variables available: $cats, $sups, $products
 */
?>

<div class="two-col">
    <section class="panel">
        <div class="panel-title">
            <h2>Categories</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>DETAILS</th>
                    <th>PRODUCTS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cats as $item):
                    // Count how many products belong to this category
                    $itemCount = array_sum(array_map(
                        fn($p) => (int)$p['category_id'] === (int)$item['category_id'] ? 1 : 0,
                        $products
                    ));
                ?>
                    <tr>
                        <td>
                            <!-- Clicking the name appends ?category_id= and shows products below -->
                            <a class="category-link" href="?page=categories&category_id=<?= $item['category_id'] ?>">
                                <?= h($item['category_name']) ?> →
                            </a>
                        </td>
                        <td><?= h($item['description']) ?></td>
                        <td>
                            <span class="pill <?= $itemCount > 0 ? 'good' : 'muted' ?>">
                                <?= $itemCount ?> product(s)
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <!-- Edit: opens categoryEditModal via editCategory() in app.js -->
                                <button title="Edit category"
                                    onclick='editCategory(<?= h(json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>)'>✎</button>
                                <!-- Delete (owner only on server) -->
                                <form method="post"
                                      onsubmit="return confirm('Delete this category? Products using it must be reassigned first.')">
                                    <input type="hidden" name="csrf"   value="<?= h(csrf()) ?>">
                                    <input type="hidden" name="id"     value="<?= $item['category_id'] ?>">
                                    <input type="hidden" name="action" value="delete_category">
                                    <button title="Delete category">⊘</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <!-- Add Category form (owner only) -->
    <?php if ($_SESSION['role'] === 'owner'): ?>
        <section class="panel form-panel">
            <h2>Add Category</h2>
            <form method="post">
                <input type="hidden" name="csrf"   value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="save_category">
                <label>Name<input name="name" required></label>
                <label>Description<textarea name="description"></textarea></label>
                <button class="btn primary">Save</button>
            </form>
        </section>
    <?php endif; ?>
</div>

<!-- Product list filtered by selected category (shown when ?category_id= is in URL) -->
<?php if (isset($_GET['category_id'])):
    $selectedId  = (int)$_GET['category_id'];
    $selectedCat = null;
    foreach ($cats as $c) {
        if ((int)$c['category_id'] === $selectedId) { $selectedCat = $c; break; }
    }
?>
    <section class="panel category-results" style="margin-top:20px">
        <div class="panel-title">
            <div>
                <h2><?= $selectedCat ? 'Products in ' . h($selectedCat['category_name']) : 'Category not found' ?></h2>
                <p>Click another category to view its products.</p>
            </div>
            <a href="?page=categories">Clear selection</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>PRODUCT</th><th>SUPPLIER</th>
                        <th>SELLING PRICE</th><th>UNIT COST</th>
                        <th>STOCK</th><th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $found = false;
                    foreach ($products as $pr):
                        if ((int)$pr['category_id'] !== $selectedId) continue;
                        $found = true;
                    ?>
                        <tr>
                            <td><strong><?= h($pr['product_name']) ?></strong></td>
                            <td><?= h($pr['supplier_name']) ?></td>
                            <td><?= money($pr['selling_price']) ?></td>
                            <td><?= money($pr['product_cost']) ?></td>
                            <td><?= h($pr['product_quantity']) ?></td>
                            <td><?= $pr['active'] ? 'Active' : 'Archived' ?></td>
                        </tr>
                    <?php endforeach;
                    if (!$found): ?>
                        <tr><td colspan="6" class="empty">No products in this category.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
