<?php
// Deliveries page template.
?>

<style>
    /* ── Autocomplete dropdown (delivery product search) ───────────── */
    .ac-wrap {
        position: relative;
        width: 100%;
    }

    .ac-wrap input[type="text"] {
        width: 100%;
        border: 1px solid var(--border);
        border-radius: 5px;
        padding: 8px 10px;
        font: inherit;
        font-size: 13px;
        background: var(--surface);
        color: var(--ink);
    }

    .ac-wrap input[type="text"] {
        background: var(--field-bg);
        border-color: var(--border);
        color: var(--ink);
    }

    .ac-list {
        display: none;
        position: absolute;
        top: calc(100% + 2px);
        left: 0; right: 0;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 7px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        z-index: 100;
        max-height: 240px;
        overflow-y: auto;
        scrollbar-width: thin;
    }

    .ac-list {
        background: var(--surface);
        border-color: #2a2a2a;
        box-shadow: 0 6px 20px rgba(0,0,0,0.4);
    }

    .ac-item {
        padding: 11px 14px;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        color: var(--ink);
        border-bottom: 1px solid var(--border);
    }

    .ac-item:last-child { border-bottom: none; }

    .ac-item:hover, .ac-item.active {
        background: var(--blue);
        color: var(--on-accent);
    }

    .ac-item .ac-price {
        font-size: 12px;
        font-weight: 700;
        opacity: 0.75;
        white-space: nowrap;
    }

    .ac-item:hover .ac-price,
    .ac-item.active .ac-price { opacity: 1; }

    .ac-empty {
        padding: 12px 14px;
        font-size: 13px;
        color: var(--muted);
        text-align: center;
    }
</style>

<div class="two-col">

    <section class="panel form-panel">
<?php if (!$readOnly): ?>
        <h2>Record Supplier Delivery</h2>

        <p>Received deliveries automatically increase stock.</p>

        <form method="post">

            <input type="hidden"
                   name="csrf"
                   value="<?= h(csrf()) ?>">

            <input type="hidden"
                   name="action"
                   value="delivery">

            <input type="hidden" id="del-product-guard" value=""><!-- validated by onsubmit -->

            <label>Supplier

                <select name="supplier" id="deliverySupplierFilter" onchange="filterDeliveryProducts()">
                    <option value="">— Not specified —</option>
                    <?php foreach ($sups as $sup): ?>
                        <option value="<?= h($sup['supplier_id']) ?>">
                            <?= h($sup['supplier_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </label>

            <label>Product

                <div class="ac-wrap" id="del-acwrap" style="margin-top:4px;">
                    <input type="text" id="del-search"
                           placeholder="Search product…"
                           autocomplete="off"
                           onfocus="delAcOpen()"
                           oninput="delAcFilter()"
                           onkeydown="delAcKey(event)">
                    <div class="ac-list" id="del-aclist"></div>
                    <input type="hidden" name="product" id="del-product" value="">
                </div>

            </label>

            <label>Quantity

                <input name="quantity"
                       type="number"
                       min="1"
                       value="1"
                       required>

            </label>

            <label>Unit Cost (₱)

                <input name="unit_cost"
                       id="deliveryUnitCost"
                       type="number"
                       step="0.01"
                       min="0"
                       value="">

            </label>

            <label>Delivery Date

                <input type="date"
                       name="date"
                       value="<?= date('Y-m-d') ?>"
                       required>

            </label>

            <label>Status

                <select name="status">

                    <option value="received">
                        Received — add stock now
                    </option>

                    <option value="delivering">
                        Delivering — pending arrival
                    </option>

                </select>

            </label>

            <button class="btn primary"
                onclick="if(!document.getElementById('del-product').value){alert('Please select a product.');return false;}"
                <?= !$active ? 'disabled' : '' ?>>

                Save Delivery

            </button>

        </form>

    </section>


    <section class="panel">

        <div class="panel-title">
            <h2>Recent Deliveries</h2>
        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>ID / PRODUCT</th>
                        <th>SUPPLIER</th>
                        <th>QTY</th>
                        <th>AMOUNT</th>
                        <th>RECORDED BY</th>
                        <th>APPROVED BY</th>
                        <th>STATUS</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($deliveries as $r): ?>

                        <tr>

                            <td>

                                <strong>
                                    #<?= h($r['delivery_id']) ?>
                                    ·
                                    <?= h($r['product_name']) ?>
                                </strong>

                                <small>
                                    <?= h($r['delivery_date']) ?>
                                </small>

                            </td>

                            <td>
                                <?= $r['supplier_name'] ? h($r['supplier_name']) : '<span style="color:var(--muted)">—</span>' ?>
                            </td>

                            <td>
                                <?= h($r['quantity_received']) ?>
                            </td>

                            <td>

                                <?= money($r['total_amount']) ?>

                                <small>
                                    <?= money($r['unit_cost']) ?> / unit
                                </small>

                            </td>

                            <td>
                                <?= h($r['user_name']) ?>
                            </td>

                            <td>

                                <?= h(
                                    $r['approver_name'] ??
                                    (
                                        $r['delivery_status'] === 'delivering'
                                        ? 'Pending'
                                        : 'Not recorded'
                                    )
                                ) ?>

                                <?php if ($r['approved_at']): ?>

                                    <small>
                                        <?= h($r['approved_at']) ?>
                                    </small>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ($r['delivery_status'] === 'delivering' && !$readOnly): ?>

                                    <form method="post">

                                        <input type="hidden"
                                               name="csrf"
                                               value="<?= h(csrf()) ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="receive_delivery">

                                        <input type="hidden"
                                               name="id"
                                               value="<?= h($r['delivery_id']) ?>">

                                        <button class="btn mini">
                                            Approve &amp; Receive
                                        </button>

                                    </form>

                                <?php elseif ($r['delivery_status'] === 'received'): ?>

                                    <span class="pill good">
                                        Received
                                    </span>

                                <?php else: ?>

                                    <span class="pill">
                                        Cancelled
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </section>
<?php endif; ?>
</div>

<script>
// Product map for delivery autocomplete
const DEL_PRODUCTS = <?= json_encode(
    array_map(fn($p) => [
        'id'       => $p['product_id'],
        'name'     => $p['product_name'],
        'cost'     => (float)$p['product_cost'],
        'stock'    => (int)$p['product_quantity'],
        'supplier' => (string)$p['supplier_id'],
    ], $active),
    JSON_HEX_TAG | JSON_HEX_AMP
) ?>;

const DEL_LIST = [...DEL_PRODUCTS].sort((a, b) => a.name.localeCompare(b.name));

let delActiveIndex = -1;
let delCurrentSupplier = '';

function delEsc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function delFmt(n) {
    return '₱' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function delGetFiltered() {
    const search = document.getElementById('del-search').value.toLowerCase().trim();
    return DEL_LIST.filter(p =>
        (!delCurrentSupplier || p.supplier === delCurrentSupplier) &&
        (!search || p.name.toLowerCase().includes(search))
    );
}

function delRenderList(matches) {
    const list = document.getElementById('del-aclist');
    delActiveIndex = -1;
    list.innerHTML = '';

    if (!matches.length) {
        list.innerHTML = '<div class="ac-empty">No products found.</div>';
    } else {
        matches.forEach((p, i) => {
            const div = document.createElement('div');
            div.className = 'ac-item';
            div.dataset.id = p.id;
            div.innerHTML = `<span>${delEsc(p.name)} <small style="color:inherit;opacity:1;">(${p.stock} left)</small></span>
                             <span class="ac-price">${delFmt(p.cost)}</span>`;
            div.addEventListener('mousedown', e => {
                e.preventDefault();
                delSelect(p.id, p.name, p.cost);
            });
            list.appendChild(div);
        });
    }
    list.style.display = 'block';
}

function delAcOpen() {
    delRenderList(delGetFiltered());
}

function delAcFilter() {
    delRenderList(delGetFiltered());
}

function delSelect(id, name, cost) {
    document.getElementById('del-search').value  = name;
    document.getElementById('del-product').value = id;
    document.getElementById('del-aclist').style.display = 'none';
    document.getElementById('deliveryUnitCost').value = cost.toFixed(2);
}

function delAcKey(e) {
    const list  = document.getElementById('del-aclist');
    const items = list.querySelectorAll('.ac-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        delActiveIndex = Math.min(delActiveIndex + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        delActiveIndex = Math.max(delActiveIndex - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (delActiveIndex >= 0 && items[delActiveIndex]) {
            const id   = items[delActiveIndex].dataset.id;
            const p    = DEL_PRODUCTS.find(x => x.id == id);
            if (p) delSelect(p.id, p.name, p.cost);
        }
        return;
    } else if (e.key === 'Escape') {
        list.style.display = 'none';
        return;
    }

    items.forEach((item, i) => item.classList.toggle('active', i === delActiveIndex));
    if (items[delActiveIndex]) items[delActiveIndex].scrollIntoView({ block: 'nearest' });
}

// Close on outside click
document.addEventListener('click', e => {
    const wrap = document.getElementById('del-acwrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('del-aclist').style.display = 'none';
    }
});

function filterDeliveryProducts() {
    delCurrentSupplier = document.getElementById('deliverySupplierFilter').value;
    // Clear selection when supplier changes
    document.getElementById('del-search').value  = '';
    document.getElementById('del-product').value = '';
    document.getElementById('deliveryUnitCost').value = '';
}
</script>