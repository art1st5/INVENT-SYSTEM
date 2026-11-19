<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Matet Motorcycle Parts</title>
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
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #f5f5f5;
            font-family: 'DM Sans', sans-serif;
            color: #222;
        }

        /* ── Top bar ───────────────────────────────────────────────── */
        .shop-topbar {
            background: #ffffff;
            color: #222;
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 20;
            border-bottom: 1px solid #e8e8e8;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }

        /* Centered brand — absolutely centered regardless of left/right content */
        .shop-brand-center {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 10px;
            pointer-events: none;
        }

        .shop-brand-icon {
            width: 34px; height: 34px;
            background: #111111;
            border-radius: 8px;
            display: grid; place-items: center;
            font-size: 18px; font-weight: 900; color: white;
        }

        .shop-brand-center strong {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #111111;
            display: block;
        }

        .shop-brand-center span {
            font-size: 8px;
            letter-spacing: 1.5px;
            color: #606b7c;
            display: block;
            margin-top: 1px;
        }

        .shop-search-wrap {
            flex: 0 0 auto;
            width: 260px;
            display: flex;
        }

        .shop-search-wrap input {
            flex: 1;
            border: 1px solid #ddd;
            border-right: none;
            border-radius: 2px 0 0 2px;
            padding: 0 14px;
            height: 36px;
            font: 14px 'DM Sans', sans-serif;
            outline: none;
            color: #222;
            background: #f7f7f7;
        }

        .shop-search-wrap input:focus {
            border-color: #111111;
            background: white;
        }

        .shop-search-wrap button {
            background: #111111;
            border: 1px solid #111111;
            border-radius: 0 2px 2px 0;
            width: 44px;
            height: 36px;
            cursor: pointer;
            color: white;
            font-size: 16px;
            display: grid;
            place-items: center;
        }

        .shop-search-wrap button:hover { background: #2a2a2a; border-color: #2a2a2a; }

        .shop-user {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 13px;
            white-space: nowrap;
        }

        .shop-user span { color: #666; }
        .shop-user strong { color: #111111; }

        .shop-logout {
            background: transparent;
            border: 1px solid #ddd;
            color: #555;
            border-radius: 2px;
            padding: 5px 12px;
            font: 13px 'DM Sans', sans-serif;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .shop-logout:hover { background: #f5f5f5; border-color: #bbb; color: #222; }

        /* ── Category filter strip ─────────────────────────────────── */
        .cat-strip {
            background: white;
            border-bottom: 1px solid #e8e8e8;
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 4px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .cat-strip::-webkit-scrollbar { display: none; }

        .cat-btn {
            background: none;
            border: none;
            padding: 12px 14px;
            font: 15px 'DM Sans', sans-serif;  /* ← change tab text size here for employee */
            color: #555;
            cursor: pointer;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            font-weight: 500;
        }

        .cat-btn:hover { color: #111111; }
        .cat-btn.active { color: #111111; border-bottom-color: #111111; font-weight: 700; }

        /* ── Page body ─────────────────────────────────────────────── */
        .shop-body {
            max-width: 1300px;
            margin: 0 auto;
            padding: 18px 16px 40px;
        }

        .shop-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .shop-meta .result-count {
            font-size: 13px;
            color: #666;
        }

        .shop-meta .result-count strong { color: #222; }

        .stock-filter {
            display: flex;
            gap: 8px;
        }

        .stock-filter button {
            background: white;
            border: 1px solid #ddd;
            border-radius: 2px;
            padding: 6px 14px;
            font: 12px 'DM Sans', sans-serif;
            cursor: pointer;
            color: #555;
        }

        .stock-filter button:hover { border-color: #111111; color: #111111; }
        .stock-filter button.active { background: #111111; border-color: #111111; color: white; font-weight: 700; }

        /* ── Product grid ──────────────────────────────────────────── */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
        }

        .prod-card {
            background: white;
            border-radius: 3px;
            overflow: hidden;
            cursor: default;
            transition: box-shadow 0.15s;
            border: 1px solid #f0f0f0;
        }

        .prod-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,0.10); }

        .prod-card:hover { box-shadow: 0 4px 18px rgba(255,255,255,0.08); }

        .prod-img {
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #f5f5f5;
            display: grid;
            place-items: center;
            overflow: hidden;
            font-size: 48px;
            color: #666;
        }

        .prod-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .prod-details {
            padding: 8px 10px 10px;
        }

        /* Product name — 2 lines max then ellipsis */
        .prod-name {
            font-size: 13px;
            font-weight: 400;
            color: #222;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 6px;
            min-height: 36px;
        }

        .prod-price {
            color: #111111;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin-bottom: 6px;
        }

        .prod-stock {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #666;
        }

        .stock-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .stock-dot.in  { background: #26aa99; }
        .stock-dot.low { background: #f59e0b; }
        .stock-dot.out { background: #ee4d2d; }

        /* No results */
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }

        .no-results .nr-icon { font-size: 52px; margin-bottom: 12px; }
        .no-results p { font-size: 14px; }

        /* ── Dark mode ─────────────────────────────────────────────── */
        [data-theme="dark"] body { background: #0d0d0d; color: #f0f0f0; }

        [data-theme="dark"] .cat-strip,
        [data-theme="dark"] .prod-card {
            background: #1a1a1a;
            border-color: #2a2a2a;
        }

        [data-theme="dark"] .cat-strip { border-color: #2a2a2a; }
        [data-theme="dark"] .cat-btn { color: #aaaaaa; }
        [data-theme="dark"] .cat-btn.active { color: #f0f0f0; border-bottom-color: #f0f0f0; }
        [data-theme="dark"] .prod-name { color: #f0f0f0; }
        [data-theme="dark"] .prod-price { color: #f0f0f0; }
        [data-theme="dark"] .prod-stock { color: #888888; }
        [data-theme="dark"] .result-count { color: #888888; }
        [data-theme="dark"] .result-count strong { color: #f0f0f0; }
        [data-theme="dark"] .stock-filter button { background: #1a1a1a; border-color: #2a2a2a; color: #aaaaaa; }
        [data-theme="dark"] .stock-filter button.active { background: #f0f0f0; border-color: #f0f0f0; color: #111111; }
        [data-theme="dark"] .prod-img { background: #2a2a2a; color: #aaaaaa; }
        [data-theme="dark"] .shop-search-wrap input { background: #1a1a1a; color: #f0f0f0; border-color: #2a2a2a; }
        [data-theme="dark"] .shop-topbar { background: #0d0d0d; border-color: #2a2a2a; }
        [data-theme="dark"] .shop-brand-center strong { color: #f0f0f0; }
        [data-theme="dark"] .shop-brand-icon { background: #111111; }
        [data-theme="dark"] .shop-user span { color: #888888; }
        [data-theme="dark"] .shop-user strong { color: #f0f0f0; }
        [data-theme="dark"] .shop-logout { border-color: #2a2a2a; color: #aaaaaa; }
        [data-theme="dark"] .shop-logout:hover { background: #1a1a1a; color: #f0f0f0; }
        [data-theme="dark"] .shop-search-wrap button { background: #1a1a1a; border-color: #2a2a2a; }

        [data-theme="dark"] .cat-btn:hover, [data-theme="dark"] .stock-filter button:hover { color: #f0f0f0; }
        [data-theme="dark"] .stock-filter button.active:hover { color: #111111; }
        [data-theme="dark"] .shop-meta .result-count strong { color: #f0f0f0; }
        [data-theme="dark"] .no-results { color: #aaaaaa; }
        /* ── Responsive ────────────────────────────────────────────── */
        @media (max-width: 600px) {
            .shop-topbar { padding: 0 12px; gap: 10px; }
            .shop-brand { font-size: 17px; }
            .shop-user span { display: none; }
            .shop-body { padding: 12px 10px 32px; }
            .product-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .prod-price { font-size: 14px; }
        }
    </style>
    
</head>
<body>

<!-- ── Top bar ──────────────────────────────────────────────────────── -->
<div class="shop-topbar">

    <!-- Left: search -->
    <div class="shop-search-wrap">
        <input type="text" id="shopSearch" placeholder="Search products…" oninput="shopFilter()">
        <button>⌕</button>
    </div>

    <!-- Center: brand -->
    <div class="shop-brand-center">
        <div class="shop-brand-icon">M</div>
        <div>
            <strong>MATET</strong>
            <span>MOTORCYCLE PARTS</span>
        </div>
    </div>

    <!-- Right: user + actions -->
    <div class="shop-user">
        <span>Hi, <strong><?= h($_SESSION['name']) ?></strong></span>
        
        <button class="dark-toggle" id="darkToggle" title="Toggle dark mode" onclick="toggleDarkMode()" style="border-color:var(--border);color:var(--ink);">🌙</button>
        <a href="logout.php" class="shop-logout">Sign out</a>
    </div>

</div>

<!-- ── Category strip ───────────────────────────────────────────────── -->
<div class="cat-strip" id="catStrip">
    <button class="cat-btn active" onclick="setCat(this, '')" data-cat="">All</button>
    <?php
    $seenCats = [];
    foreach ($products as $pr) {
        if (!in_array($pr['category_name'], $seenCats)) {
            $seenCats[] = $pr['category_name'];
            echo '<button class="cat-btn" onclick="setCat(this, \'' . h(addslashes($pr['category_name'])) . '\')" data-cat="' . h($pr['category_name']) . '">' . h($pr['category_name']) . '</button>';
        }
    }
    ?>
</div>

<!-- ── Body ─────────────────────────────────────────────────────────── -->
<div class="shop-body">

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="success" style="margin-bottom:14px;"><?= h($_SESSION['flash']) ?></div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="shop-meta">
        <p class="result-count" id="shopCount">
            <strong><?= count($products) ?></strong> product<?= count($products) !== 1 ? 's' : '' ?> available
        </p>
        <div class="stock-filter">
            <button class="active" onclick="setStock(this, '')">All</button>
            <button onclick="setStock(this, 'in')">In stock</button>
            <button onclick="setStock(this, 'out')">Out of stock</button>
        </div>
    </div>

    <!-- Grid -->
    <div class="product-grid" id="shopGrid">

        <?php foreach ($products as $pr):
            $qty        = (int)$pr['product_quantity'];
            $outOfStock = $qty === 0;
            $lowStock   = !$outOfStock && $qty <= (int)$pr['reorder_level'];
        ?>
            <div class="prod-card"
                 data-name="<?= h(strtolower($pr['product_name'])) ?>"
                 data-cat="<?= h($pr['category_name']) ?>"
                 data-stock="<?= $outOfStock ? 'out' : 'in' ?>">

                <div class="prod-img">
                    <?php if (!empty($pr['image_url'])): ?>
                        <img src="<?= h($pr['image_url']) ?>" alt="" onerror="this.parentNode.innerHTML='▣'">
                    <?php else: ?>
                        ▣
                    <?php endif; ?>
                </div>

                <div class="prod-details">
                    <div class="prod-name"><?= h($pr['product_name']) ?></div>
                    <div class="prod-price"><?= money($pr['selling_price']) ?></div>
                    <div class="prod-stock">
                        <?php if ($outOfStock): ?>
                            <span class="stock-dot out"></span> Out of stock
                        <?php elseif ($lowStock): ?>
                            <span class="stock-dot low"></span> Low stock &mdash; <?= $qty ?> left
                        <?php else: ?>
                            <span class="stock-dot in"></span> <?= $qty ?> in stock
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>

        <div class="no-results" id="shopNoResults" style="display:none;">
            <div class="nr-icon">🔍</div>
            <p>No products found.</p>
        </div>

    </div>
</div>

<script src="assets/app.js"></script>
<script>
    var _activeCat   = '';
    var _activeStock = '';

    function setCat(btn, cat) {
        _activeCat = cat;
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        shopFilter();
    }

    function setStock(btn, stock) {
        _activeStock = stock;
        document.querySelectorAll('.stock-filter button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        shopFilter();
    }

    function shopFilter() {
        const search = document.getElementById('shopSearch').value.toLowerCase().trim();
        const cards  = document.querySelectorAll('#shopGrid .prod-card');
        let visible  = 0;

        cards.forEach(card => {
            const ok = (!search       || card.dataset.name.includes(search))
                    && (!_activeCat   || card.dataset.cat === _activeCat)
                    && (!_activeStock || card.dataset.stock === _activeStock);
            card.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });

        document.getElementById('shopNoResults').style.display = visible === 0 ? 'block' : 'none';
        document.getElementById('shopCount').innerHTML =
            '<strong>' + visible + '</strong> product' + (visible !== 1 ? 's' : '') + ' available';
    }
</script>
</body>
</html>
