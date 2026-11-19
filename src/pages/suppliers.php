<?php
/**
 * src/pages/suppliers.php — Suppliers page template.
 */
?>

<div class="two-col">
    <section class="panel">
        <div class="panel-title">
            <h2>Suppliers</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>CONTACT</th>
                    <th>PRODUCTS</th>
                    <?php if ($_SESSION['role'] === 'owner'): ?>
                        <th>ACTIONS</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sups as $item):
                    $itemCount = array_sum(array_map(
                        fn($p) => (int)$p['supplier_id'] === (int)$item['supplier_id'] ? 1 : 0,
                        $products
                    ));
                ?>
                    <tr>
                        <td>
                            <a class="category-link" href="?page=suppliers&supplier_id=<?= $item['supplier_id'] ?>">
                                <?= h($item['supplier_name']) ?> →
                            </a>
                        </td>
                        <td><?= h($item['contact_num']) ?></td>
                        <td>
                            <span class="pill <?= $itemCount > 0 ? 'good' : 'muted' ?>">
                                <?= $itemCount ?> product(s)
                            </span>
                        </td>
                        <?php if ($_SESSION['role'] === 'owner'): ?>
                            <td>
                                <button class="btn mini"
                                    onclick="openEditSupplier(
                                        <?= $item['supplier_id'] ?>,
                                        <?= h(json_encode($item['supplier_name'])) ?>,
                                        <?= h(json_encode($item['contact_num'])) ?>,
                                        <?= h(json_encode($item['address'])) ?>
                                    )">✎ Edit</button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <!-- Add Supplier form (owner only) -->
    <?php if ($_SESSION['role'] === 'owner'): ?>
        <section class="panel form-panel">
            <h2>Add Supplier</h2>
            <form method="post">
                <input type="hidden" name="csrf"   value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="save_supplier">
                <label>Name<input name="name" required></label>
                <label>Contact Number<input name="contact"></label>
                <label>Address<textarea name="address"></textarea></label>
                <button class="btn primary">Save</button>
            </form>
        </section>
    <?php endif; ?>
</div>

<!-- Filtered product list by supplier -->
<?php if (isset($_GET['supplier_id'])):
    $selectedId  = (int)$_GET['supplier_id'];
    $selectedSup = null;
    foreach ($sups as $s) {
        if ((int)$s['supplier_id'] === $selectedId) { $selectedSup = $s; break; }
    }
?>
    <section class="panel category-results" style="margin-top:20px">
        <div class="panel-title">
            <div>
                <h2><?= $selectedSup ? 'Products from ' . h($selectedSup['supplier_name']) : 'Supplier not found' ?></h2>
                <p>Click another supplier to view their products.</p>
            </div>
            <a href="?page=suppliers">Clear selection</a>
        </div>

        <?php if ($selectedSup): ?>
        <!-- Supplier details card -->
        <div style="display:flex;gap:24px;flex-wrap:wrap;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:16px 20px;margin-bottom:20px;">
            <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:1px;color:var(--muted);margin-bottom:4px;">SUPPLIER NAME</div>
                <div style="font-size:15px;font-weight:700;color:var(--ink);"><?= h($selectedSup['supplier_name']) ?></div>
            </div>
            <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:1px;color:var(--muted);margin-bottom:4px;">CONTACT</div>
                <div style="font-size:14px;color:var(--ink);"><?= $selectedSup['contact_num'] ? h($selectedSup['contact_num']) : '—' ?></div>
            </div>
            <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:1px;color:var(--muted);margin-bottom:4px;">ADDRESS</div>
                <div style="font-size:14px;color:var(--ink);"><?= $selectedSup['address'] ? h($selectedSup['address']) : '—' ?></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>PRODUCT</th><th>CATEGORY</th>
                        <th>SELLING PRICE</th><th>UNIT COST</th>
                        <th>STOCK</th><th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $found = false;
                    foreach ($products as $pr):
                        if ((int)$pr['supplier_id'] !== $selectedId) continue;
                        $found = true;
                    ?>
                        <tr>
                            <td><strong><?= h($pr['product_name']) ?></strong></td>
                            <td><?= h($pr['category_name']) ?></td>
                            <td><?= money($pr['selling_price']) ?></td>
                            <td><?= money($pr['product_cost']) ?></td>
                            <td><?= h($pr['product_quantity']) ?></td>
                            <td><?= $pr['active'] ? 'Active' : 'Archived' ?></td>
                        </tr>
                    <?php endforeach;
                    if (!$found): ?>
                        <tr><td colspan="6" class="empty">No products from this supplier.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<!-- ── Edit Supplier Modal (owner only) ──────────────────────────────── -->
<?php if ($_SESSION['role'] === 'owner'): ?>
<div class="modal" id="editSupplierModal">
    <div class="modal-card" style="width:min(480px,96vw);">
        <div class="modal-head">
            <h2>Edit Supplier</h2>
            <button onclick="closeModal('editSupplierModal')">✕</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf"        value="<?= h(csrf()) ?>">
            <input type="hidden" name="action"      value="edit_supplier">
            <input type="hidden" name="supplier_id" id="edit_sup_id">

            <div style="display:flex;flex-direction:column;gap:14px;">
                <label>Supplier Name
                    <input name="name" id="edit_sup_name" required>
                </label>
                <label>Contact Number
                    <input name="contact" id="edit_sup_contact">
                </label>
                <label>Address
                    <textarea name="address" id="edit_sup_address"></textarea>
                </label>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeModal('editSupplierModal')">Cancel</button>
                <button class="btn primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditSupplier(id, name, contact, address) {
    document.getElementById('edit_sup_id').value      = id;
    document.getElementById('edit_sup_name').value    = name;
    document.getElementById('edit_sup_contact').value = contact;
    document.getElementById('edit_sup_address').value = address;
    openModal('editSupplierModal');
}
</script>
<?php endif; ?>
