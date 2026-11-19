<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Displays active products as catalog cards with category filtering. Customize card layout,
 * filter appearance and labels in this template.
 */
/**
 * src/pages/catalog.php — Product catalog view.
 * Matches the employee storefront card style.
 * Variables available: $active
 */
?>

<style>
    /* ── Catalog toolbar ───────────────────────────────────────── */
    .cat-filter-strip {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .cat-filter-btn {
        background: white;
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 7px 14px;
        font: 12px 'DM Sans', sans-serif;
        font-weight: 600;
        cursor: pointer;
        color: var(--muted);
        transition: border-color 0.1s, color 0.1s, background 0.1s;
    }

    .cat-filter-btn:hover  { border-color: var(--blue); color: var(--blue); }
    .cat-filter-btn.active, .cat-filter-btn.active:hover { background: var(--blue); border-color: var(--blue); color: var(--on-accent); }

    .cat-result-count {
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 16px;
    }

    .cat-result-count strong { color: var(--ink); }

    /* ── Product grid ──────────────────────────────────────────── */
    .cat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
        gap: 12px;
    }

    .cat-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
        transition: box-shadow 0.15s, transform 0.15s;
    }

    .cat-card:hover {
        box-shadow: 0 6px 22px #14223812;
        transform: translateY(-2px);
    }

    .cat-card-img {
        width: 100%;
        aspect-ratio: 1 / 1;
        background: #f0f4f9;
        display: grid;
        place-items: center;
        font-size: 44px;
        color: #626b76;
        overflow: hidden;
    }

    .cat-card-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .cat-card-body {
        padding: 10px 12px 12px;
    }

    .cat-card-cat {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 1px;
        color: var(--blue);
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .cat-card-name {
        font-size: 13px;
        font-weight: 500;
        color: var(--ink);
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 36px;
        margin-bottom: 8px;
    }

    .cat-card-price {
        font-size: 17px;
        font-weight: 800;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--ink);
        letter-spacing: -0.5px;
        margin-bottom: 8px;
        display: block;
    }

    .cat-card-stock {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        color: var(--muted);
    }

    .cat-stock-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .cat-stock-dot.in  { background: #22c55e; }
    .cat-stock-dot.low { background: #f59e0b; }
    .cat-stock-dot.out { background: #ef4444; }

    .cat-no-results {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        color: var(--muted);
    }

    .cat-no-results .icon { font-size: 44px; margin-bottom: 10px; }

    /* Dark mode */
    [data-theme="dark"] .cat-card { background: #1a1a1a; border-color: var(--border); }
    [data-theme="dark"] .cat-card:hover { box-shadow: 0 6px 22px rgba(255,255,255,0.07); }
    [data-theme="dark"] .cat-card-img { background: #2a2a2a; color: #aaaaaa; }
    [data-theme="dark"] .cat-filter-btn { background: #1a1a1a; border-color: var(--border); color: var(--muted); }
    [data-theme="dark"] .cat-filter-btn.active, .cat-filter-btn.active:hover { background: var(--blue); border-color: var(--blue); color: var(--on-accent); }
    [data-theme="dark"] #catStockFilter { background: #1a1a1a; border-color: var(--border); color: var(--ink); }

    @media (max-width: 600px) {
        .cat-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .cat-card-price { font-size: 14px; }
    }
</style>

<!-- Toolbar -->
<div class="toolbar" style="margin-bottom:12px;">
    <div class="searchbox">
        ⌕ <input type="text" id="catSearch" placeholder="Search products…" oninput="catFilter()">
    </div>
    <select id="catStockFilter" onchange="catFilter()"
            style="border:1px solid var(--border);padding:10px;border-radius:8px;font:inherit;">
        <option value="">All stock</option>
        <option value="in">In stock</option>
        <option value="out">Out of stock</option>
    </select>
</div>

<!-- Category buttons -->
<div class="cat-filter-strip">
    <button class="cat-filter-btn active" onclick="setCatFilter(this, '')">All</button>
    <?php
    $seenCats = [];
    foreach ($active as $pr) {
        if (!in_array($pr['category_name'], $seenCats)) {
            $seenCats[] = $pr['category_name'];
            echo '<button class="cat-filter-btn" onclick="setCatFilter(this, \'' . h(addslashes($pr['category_name'])) . '\')">' . h($pr['category_name']) . '</button>';
        }
    }
    ?>
</div>

<p class="cat-result-count" id="catCount">
    <strong><?= count($active) ?></strong> product<?= count($active) !== 1 ? 's' : '' ?>
</p>

<!-- Grid -->
<div class="cat-grid" id="catGrid">

    <?php foreach ($active as $pr):
        $qty      = (int)$pr['product_quantity'];
        $outOfStock = $qty === 0;
        $lowStock   = !$outOfStock && $qty <= (int)$pr['reorder_level'];
    ?>
        <div class="cat-card"
             data-name="<?= h(strtolower($pr['product_name'])) ?>"
             data-cat="<?= h($pr['category_name']) ?>"
             data-stock="<?= $outOfStock ? 'out' : 'in' ?>">

            <div class="cat-card-img">
                <?php if (!empty($pr['image_url'])): ?>
                    <img src="<?= h($pr['image_url']) ?>" alt="" onerror="this.parentNode.innerHTML='▣'">
                <?php else: ?>
                    ▣
                <?php endif; ?>
            </div>

            <div class="cat-card-body">
                <div class="cat-card-cat"><?= h($pr['category_name']) ?></div>
                <div class="cat-card-name"><?= h($pr['product_name']) ?></div>
                <span class="cat-card-price"><?= money($pr['selling_price']) ?></span>
                <div class="cat-card-stock">
                    <?php if ($outOfStock): ?>
                        <span class="cat-stock-dot out"></span> Out of stock
                    <?php elseif ($lowStock): ?>
                        <span class="cat-stock-dot low"></span> Low stock — <?= $qty ?> left
                    <?php else: ?>
                        <span class="cat-stock-dot in"></span> <?= $qty ?> in stock
                    <?php endif; ?>
                </div>
            </div>

        </div>
    <?php endforeach; ?>

    <div class="cat-no-results" id="catNoResults" style="display:none;">
        <div class="icon">🔍</div>
        <p>No products found.</p>
    </div>

</div>

<script>
var _catActive = '';

function setCatFilter(btn, cat) {
    _catActive = cat;
    document.querySelectorAll('.cat-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    catFilter();
}

function catFilter() {
    const search = document.getElementById('catSearch').value.toLowerCase().trim();
    const stock  = document.getElementById('catStockFilter').value;
    const cards  = document.querySelectorAll('#catGrid .cat-card');
    let visible  = 0;

    cards.forEach(card => {
        const ok = (!search    || card.dataset.name.includes(search))
                && (!_catActive || card.dataset.cat === _catActive)
                && (!stock     || card.dataset.stock === stock);
        card.style.display = ok ? '' : 'none';
        if (ok) visible++;
    });

    document.getElementById('catNoResults').style.display = visible === 0 ? 'block' : 'none';
    document.getElementById('catCount').innerHTML =
        '<strong>' + visible + '</strong> product' + (visible !== 1 ? 's' : '');
}
</script>
