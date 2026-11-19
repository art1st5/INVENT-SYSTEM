<?php
/**
 * src/pages/transactions.php — Multi-product transaction page.
 *
 * Flow:
 *   1. User builds a cart (add/remove rows, each with product + qty + price)
 *   2. Click "Review Sale" → preview of all items shown (nothing saved yet)
 *   3. Click "Confirm" → saved atomically; click "Cancel" → back to form
 *   4. Undo button (owner only) reverses any saved transaction
 */
$preview = $_SESSION['pending_sale'] ?? null;

// Product lookup map for JS: { id: {price, cost, discount, stock, name} }
$productMap = [];
foreach ($active as $pr) {
    $productMap[$pr['product_id']] = [
        'name'     => $pr['product_name'],
        'price'    => (float)$pr['selling_price'],
        'cost'     => (float)$pr['product_cost'],
        'discount' => (float)$pr['product_discount'],
        'stock'    => (int)$pr['product_quantity'],
    ];
}
?>

<style>
    /* ── Full-width side-by-side layout ───────────────────────────── */
    .tx-layout {
        display: grid;
        grid-template-columns: 680px 1fr;
        gap: 18px;
        align-items: start;
    }

    @media (max-width: 1000px) {
        .tx-layout { grid-template-columns: 1fr; }
    }

    /* ── Cart line items ───────────────────────────────────────────── */
    .cart-table { width:100%; border-collapse:collapse; margin-bottom:10px; }
    .cart-table th {
        font-size:9px; font-weight:700; letter-spacing:.8px;
        color:var(--muted); padding:6px 5px; border-bottom:1px solid var(--border);
        text-align:left; white-space:nowrap;
    }
    .cart-table td {
        padding:5px 4px; border-bottom:1px solid var(--border); vertical-align:middle;
    }
    .cart-table tr:last-child td { border-bottom:none; }

    .cart-table select {
        border:1px solid var(--border); border-radius:5px;
        padding:5px 6px; font:inherit; font-size:12px;
        width:100%; background:var(--surface); color:var(--ink);
    }

    .cart-table input[type="number"] {
        border:1px solid var(--border); border-radius:5px;
        padding:5px 6px; font:inherit; font-size:12px;
        width:100%; background:var(--surface); color:var(--ink);
    }

    .cart-table select,
    .cart-table input[type="number"],
    .cart-table input[type="text"] {
        background:var(--field-bg); border-color:var(--border); color:var(--ink);
    }

    .cart-remove {
        background:#ffebed; border:none; border-radius:5px;
        color:#d34750; padding:4px 7px; cursor:pointer; font-size:12px;
        line-height:1;
    }
    .cart-remove:hover { background:#fdd9dc; }

    .cart-subtotal { font-weight:700; color:var(--ink); font-size:12px; white-space:nowrap; }

    .cart-add-btn {
        background:none; border:1px dashed var(--border);
        border-radius:7px; padding:7px 12px; cursor:pointer;
        color:var(--blue); font:inherit; font-size:12px; font-weight:600;
        width:100%; margin-bottom:12px; transition:background .15s;
    }
    .cart-add-btn:hover { background:var(--border); }

    .cart-totals {
        background:var(--bg); border:1px solid var(--border);
        border-radius:7px; padding:9px 12px; margin-bottom:12px; font-size:12px;
    }
    .cart-totals-row {
        display:flex; justify-content:space-between; padding:3px 0; color:var(--muted);
    }
    .cart-totals-row.grand {
        border-top:1px solid var(--border); margin-top:5px; padding-top:8px;
        color:var(--ink); font-weight:700; font-size:15px;
    }

    /* ── Autocomplete dropdown ─────────────────────────────────────── */
    .ac-wrap {
        position: relative;
        width: 100%;
    }

    .ac-wrap input[type="text"] {
        width: 100%;
        border: 1px solid var(--border);
        border-radius: 5px;
        padding: 5px 8px;
        font: inherit;
        font-size: 12px;
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
        z-index: 50;
        max-height: 200px;
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
        font-size: 15px;
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
        font-size: 13px;
        font-weight: 700;
        opacity: 0.75;
        white-space: nowrap;
    }

    .ac-item:hover .ac-price,
    .ac-item.active .ac-price { opacity: 1; }

    .ac-empty {
        padding: 12px 14px;
        font-size: 14px;
        color: var(--muted);
        text-align: center;
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
        z-index: 50;
        max-height: 260px;
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .sale-preview {
        background:#f5f5f5; border:2px solid #0a0a0a;
        border-radius:10px; padding:16px 18px; margin-bottom:16px;
    }
    .sale-preview h3 { font-size:14px; margin-bottom:12px; color:#0a0a0a; }
    .sale-preview-row {
        display:flex; justify-content:space-between; align-items:center;
        padding:6px 0; border-bottom:1px solid #d8d8d8;
        font-size:12px; color:#333333;
    }
    .sale-preview-row:last-of-type { border-bottom:none; }
    .sale-preview-row strong { color:#0a0a0a; }
    .sale-preview-actions { display:flex; gap:8px; margin-top:14px; }

    [data-theme="dark"] .sale-preview { background:#1a1a1a; border-color:#e0e0e0; }
    [data-theme="dark"] .sale-preview h3 { color:#e0e0e0; }
    [data-theme="dark"] .sale-preview-row { color:#aaaaaa; border-color:#333333; }
    [data-theme="dark"] .sale-preview-row strong { color:#f0f0f0; }
</style>

<div class="tx-layout">

    <!-- ── Left: form or preview ──────────────────────────────────────── -->
    <section class="panel form-panel">

    <?php if ($preview): ?>

        <!-- Step 2: Review ──────────────────────────────────────────── -->
        <h2>Review Sale</h2>
        <p>Confirm the details below. Nothing has been saved yet.</p>

        <div class="sale-preview">
            <h3>🧾 Sale Summary</h3>

            <?php foreach ($preview['items'] as $item): ?>
                <div class="sale-preview-row">
                    <span><?= h($item['product_name']) ?></span>
                    <span>
                        <?= h($item['qty']) ?> × <?= money($item['unit_price']) ?>
                        <?php if ($item['discount'] > 0): ?>
                            <span style="color:var(--danger-text);font-size:11px;">
                                −<?= money($item['discount']) ?>
                            </span>
                        <?php endif; ?>
                        = <strong><?= money($item['subtotal']) ?></strong>
                    </span>
                </div>
            <?php endforeach; ?>

            <div class="sale-preview-row" style="margin-top:8px;">
                <span>Grand Total</span>
                <strong style="font-size:18px;"><?= money($preview['grand_total']) ?></strong>
            </div>
        </div>

        <div class="sale-preview-actions">
            <form method="post" style="flex:1;">
                <input type="hidden" name="csrf"   value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="sale_confirm">
                <button class="btn primary full">✓ Confirm Sale</button>
            </form>
            <form method="post" style="flex:1;">
                <input type="hidden" name="csrf"   value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="sale_cancel">
                <button class="btn danger-btn full">✕ Cancel</button>
            </form>
        </div>

    <?php else: ?>

        <!-- Step 1: Cart form ────────────────────────────────────────── -->
        <h2>Record Cash Sale</h2>
        <p>Add one or more products, then review before saving.</p>

        <form method="post" id="saleForm" onsubmit="return prepareSubmit()">
            <input type="hidden" name="csrf"   value="<?= h(csrf()) ?>">
            <input type="hidden" name="action" value="sale_preview">
            <!-- Line items are injected here as hidden inputs before submit -->
            <div id="hiddenItems"></div>

            <!-- Cart table -->
            <table class="cart-table" id="cartTable">
                <thead>
                    <tr>
                        <th style="width:45%">PRODUCT</th>
                        <th style="width:12%">QTY</th>
                        <th style="width:20%">PRICE</th>
                        <th style="width:16%">SUBTOTAL</th>
                        <th style="width:7%"></th>
                    </tr>
                </thead>
                <tbody id="cartBody">
                    <!-- rows injected by JS -->
                </tbody>
            </table>

            <button type="button" class="cart-add-btn" onclick="addRow()">＋ Add Product</button>

            <!-- Running totals -->
            <div class="cart-totals" id="cartTotals">
                <div class="cart-totals-row" id="discountRow" style="display:none;color:var(--danger-text);">
                    <span>Discount</span>
                    <span id="discountAmt"></span>
                </div>
                <div class="cart-totals-row grand">
                    <span>Total</span>
                    <span id="grandTotal">₱0.00</span>
                </div>
            </div>

            <button class="btn primary full" id="reviewBtn" disabled>Review Sale →</button>
        </form>

    <?php endif; ?>

    </section>

    <!-- ── Right: history ──────────────────────────────────────────────── -->
    <section class="panel" style="font-size:11px;">
        <div class="panel-title"><h2 style="font-size:13px;">Recent Transactions</h2></div>
        <div class="table-wrap">
            <table style="font-size:11px;">
                <thead>
                    <tr>
                        <th style="font-size:9px;">ID / PRODUCT</th>
                        <th style="font-size:9px;">QTY</th>
                        <th style="font-size:9px;">AMOUNT</th>
                        <th style="font-size:9px;">COST</th>
                        <th style="font-size:9px;">PROFIT</th>
                        <th style="font-size:9px;">CASHIER</th>
                        <th style="font-size:9px;">UNDO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $r):
                        $lineCost   = round((float)$r['product_cost'] * (int)$r['product_quantity'], 2);
                        $lineProfit = round((float)$r['subamount'] - $lineCost, 2);
                    ?>
                    <tr>
                        <td style="padding:8px 8px;">
                            <strong style="font-size:11px;">#<?= h($r['transaction_id']) ?> · <?= h($r['product_name']) ?></strong>
                            <small><?= h($r['transaction_date']) ?></small>
                        </td>
                        <td style="padding:8px 8px;"><?= h($r['product_quantity']) ?></td>
                        <td style="padding:8px 8px;"><?= money($r['subamount']) ?></td>
                        <td style="padding:8px 8px;"><?= money($lineCost) ?></td>
                        <td style="padding:8px 8px;"><strong><?= money($lineProfit) ?></strong></td>
                        <td style="padding:8px 8px;"><?= h($r['user_name']) ?></td>
                        <td style="padding:8px 8px;">
                            <?php if ($_SESSION['role'] === 'owner'): ?>
                            <form method="post"
                                  onsubmit="return confirm('Undo this sale? Stock will be restored.')">
                                <input type="hidden" name="csrf"           value="<?= h(csrf()) ?>">
                                <input type="hidden" name="action"         value="sale_undo">
                                <input type="hidden" name="transaction_id" value="<?= h($r['transaction_id']) ?>">
                                <button class="btn undo-btn mini">↩ Undo</button>
                            </form>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:11px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<script>
const PRODUCTS = <?= json_encode($productMap, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

// Build the product <select> options HTML once
function buildOptions(selectedId) {
    return Object.entries(PRODUCTS).map(([id, p]) =>
        `<option value="${id}" ${id == selectedId ? 'selected' : ''}
            data-price="${p.price}" data-discount="${p.discount}" data-stock="${p.stock}">
            ${escHtml(p.name)} (${p.stock} in stock)
         </option>`
    ).join('');
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmt(n) {
    return '₱' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

let rowId = 0;

function addRow(productId, qty, price) {
    rowId++;
    const id     = productId || '';
    const pr     = id ? (PRODUCTS[id] || {}) : {};
    const qtyV   = qty || 1;
    const priceV = price != null ? price : (pr.price != null ? pr.price : '');

    const tr = document.createElement('tr');
    tr.id = 'row-' + rowId;
    tr.innerHTML = `
        <td>
            <div class="ac-wrap" id="acwrap-${rowId}">
                <input type="text" id="search-${rowId}"
                       placeholder="Search product…"
                       autocomplete="off"
                       onfocus="acOpen('${rowId}')"
                       oninput="acFilter('${rowId}')"
                       onkeydown="acKey(event, '${rowId}')">
                <div class="ac-list" id="aclist-${rowId}"></div>
                <input type="hidden" id="prod-${rowId}" value="${escHtml(id)}">
            </div>
        </td>
        <td>
            <input type="number" id="qty-${rowId}" min="1" value="${qtyV}"
                   oninput="recalcRow('${rowId}')" style="width:70px;">
        </td>
        <td>
            <input type="number" id="price-${rowId}" value="${priceV !== '' ? Number(priceV).toFixed(2) : ''}"
                   style="width:90px;background:var(--bg);pointer-events:none;opacity:.7;" readonly>
        </td>
        <td class="cart-subtotal" id="sub-${rowId}">—</td>
        <td>
            <button type="button" class="cart-remove" onclick="removeRow('${rowId}')">✕</button>
        </td>`;
    document.getElementById('cartBody').appendChild(tr);

    // Pre-fill search text only when a product was explicitly provided (e.g. re-load)
    if (id && PRODUCTS[id]) {
        document.getElementById('search-' + rowId).value = PRODUCTS[id].name;
        recalcRow(rowId);
    }
    updateTotals();
}

function onProductChange(sel, rid) {
    const opt   = sel.options[sel.selectedIndex];
    const pr    = PRODUCTS[opt.value] || {};
    document.getElementById('price-' + rid).value = (pr.price || 0).toFixed(2);
    // Update stock hint in option text is live already via the select options
    recalcRow(rid);
}

function filterOptions(rid) {
    const search = document.getElementById('search-' + rid).value.toLowerCase().trim();
    const sel    = document.getElementById('prod-' + rid);
    let firstMatch = null;

    Array.from(sel.options).forEach(opt => {
        const match = !search || opt.text.toLowerCase().includes(search);
        opt.style.display = match ? '' : 'none';
        if (match && !firstMatch) firstMatch = opt;
    });

    // Auto-select first visible match so price/subtotal update live
    if (firstMatch) {
        sel.value = firstMatch.value;
        onProductChange(sel, rid);
    }
}

// ── Autocomplete logic ────────────────────────────────────────────
// Sorted product list (alphabetically)
const PRODUCT_LIST = Object.entries(PRODUCTS)
    .map(([id, p]) => ({ id, ...p }))
    .sort((a, b) => a.name.localeCompare(b.name));

let acActiveIndex = {}; // tracks keyboard cursor per row

function acOpen(rid) {
    acFilter(rid); // show all or filtered
}

function acFilter(rid) {
    const input   = document.getElementById('search-' + rid);
    const list    = document.getElementById('aclist-' + rid);
    const search  = input.value.toLowerCase().trim();
    acActiveIndex[rid] = -1;

    const matches = search
        ? PRODUCT_LIST.filter(p => p.name.toLowerCase().includes(search))
        : PRODUCT_LIST; // show all alphabetically when empty

    list.innerHTML = '';

    if (matches.length === 0) {
        list.innerHTML = '<div class="ac-empty">No products found.</div>';
    } else {
        matches.forEach((p, i) => {
            const div = document.createElement('div');
            div.className = 'ac-item';
            div.dataset.id = p.id;
            div.dataset.idx = i;
            div.innerHTML = `<span>${escHtml(p.name)} <small style="color:inherit;opacity:1;">(${p.stock} left)</small></span>
                             <span class="ac-price">${fmt(p.price)}</span>`;
            div.addEventListener('mousedown', e => {
                e.preventDefault(); // keep focus
                acSelect(rid, p.id, p.name);
            });
            list.appendChild(div);
        });
    }

    list.style.display = 'block';
}

function acSelect(rid, productId, productName) {
    document.getElementById('search-' + rid).value = productName;
    document.getElementById('prod-'   + rid).value = productId;
    document.getElementById('aclist-' + rid).style.display = 'none';

    const pr = PRODUCTS[productId] || {};
    document.getElementById('price-' + rid).value = (pr.price || 0).toFixed(2);
    recalcRow(rid);
}

function acKey(e, rid) {
    const list  = document.getElementById('aclist-' + rid);
    const items = list.querySelectorAll('.ac-item');
    if (!items.length) return;

    let idx = acActiveIndex[rid] ?? -1;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        idx = Math.min(idx + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        idx = Math.max(idx - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (idx >= 0 && items[idx]) {
            acSelect(rid, items[idx].dataset.id, items[idx].querySelector('span').textContent.replace(/\(.*\)/, '').trim());
        }
        return;
    } else if (e.key === 'Escape') {
        list.style.display = 'none';
        return;
    }

    acActiveIndex[rid] = idx;
    items.forEach((item, i) => item.classList.toggle('active', i === idx));
    if (items[idx]) items[idx].scrollIntoView({ block: 'nearest' });
}

// Close dropdown when clicking outside
document.addEventListener('click', e => {
    document.querySelectorAll('.ac-list').forEach(list => {
        if (!list.closest('.ac-wrap')?.contains(e.target)) {
            list.style.display = 'none';
        }
    });
});

function recalcRow(rid) {
    const qty     = parseFloat(document.getElementById('qty-'   + rid)?.value) || 0;
    const priceEl = document.getElementById('price-' + rid);
    const price   = priceEl && priceEl.value !== '' ? parseFloat(priceEl.value) : null;
    const el      = document.getElementById('sub-' + rid);

    if (price === null || price === 0) {
        if (el) el.innerHTML = '—';
        updateTotals();
        return;
    }

    const pid     = document.getElementById('prod-' + rid)?.value;
    const discPct = pid && PRODUCTS[pid] ? (PRODUCTS[pid].discount || 0) : 0;
    const gross   = qty * price;
    const disc    = Math.round(gross * discPct) / 100;
    const sub     = gross - disc;
    if (el) el.innerHTML = fmt(sub) + (discPct > 0 ? ` <small style="color:var(--danger-text)">-${discPct}%</small>` : '');
    updateTotals();
}

function removeRow(rid) {
    const rows = document.getElementById('cartBody').querySelectorAll('tr');
    if (rows.length <= 1) return; // keep at least one row
    document.getElementById('row-' + rid)?.remove();
    updateTotals();
}

function updateTotals() {
    const rows = document.getElementById('cartBody').querySelectorAll('tr');
    let gross = 0, totalDisc = 0;
    rows.forEach(tr => {
        const rid      = tr.id.replace('row-', '');
        const qty      = parseFloat(document.getElementById('qty-'   + rid)?.value) || 0;
        const priceEl  = document.getElementById('price-' + rid);
        const price    = priceEl && priceEl.value !== '' ? (parseFloat(priceEl.value) || 0) : 0;
        const pid      = document.getElementById('prod-' + rid)?.value;
        const discPct  = pid && PRODUCTS[pid] ? (PRODUCTS[pid].discount || 0) : 0;
        const lineGross = qty * price;
        const lineDisc  = Math.round(lineGross * discPct) / 100;
        gross     += lineGross;
        totalDisc += lineDisc;
    });
    const grand = gross - totalDisc;

    const discRow = document.getElementById('discountRow');
    if (totalDisc > 0) {
        discRow.style.display = 'flex';
        document.getElementById('discountAmt').textContent = '−' + fmt(totalDisc);
    } else {
        discRow.style.display = 'none';
    }

    document.getElementById('grandTotal').textContent = grand > 0 ? fmt(grand) : '₱0.00';
    document.getElementById('reviewBtn').disabled = (grand <= 0 || rows.length === 0);
}

function prepareSubmit() {
    const container = document.getElementById('hiddenItems');
    container.innerHTML = '';
    const rows = document.getElementById('cartBody').querySelectorAll('tr');
    let valid = true;

    rows.forEach((tr, i) => {
        const rid   = tr.id.replace('row-', '');
        const pid   = document.getElementById('prod-'  + rid)?.value;
        const qty   = parseInt(document.getElementById('qty-'   + rid)?.value) || 0;

        if (!pid || qty < 1) { valid = false; return; }

        const mk = (name, val) => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = name; inp.value = val;
            container.appendChild(inp);
        };
        mk(`items[${i}][product_id]`, pid);
        mk(`items[${i}][qty]`,        qty);
    });

    if (!valid) { alert('Please fill in all rows correctly.'); return false; }
    return true;
}

// Start with one row
addRow();
</script>
