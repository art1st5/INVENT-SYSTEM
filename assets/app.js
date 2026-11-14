function openModal(id) {
    document.getElementById(id)?.classList.add('show')
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('show')
}

function resetProduct() {
    document.querySelector('#productModal form').reset();
    document.getElementById('p_id').value = '';
    document.getElementById('modalTitle').textContent = 'Add Product';
    resetCategoryRemove();
    toggleCategoryMode('existing');
    toggleSupplierMode('existing')
}

function editProduct(p) {
    openModal('productModal');
    document.getElementById('modalTitle').textContent = 'Edit Product';
    for (const [id, key] of Object.entries({id: 'product_id', name: 'product_name', price: 'selling_price', cost: 'product_cost', quantity: 'product_quantity', reorder: 'reorder_level', discount: 'product_discount', image: 'image_url', description: 'description'})) {
        document.getElementById('p_' + id).value = p[key] ?? ''
    }
    if (p['category_id']) {
        document.getElementById('p_category').value = p['category_id'];
        resetCategoryRemove();
        toggleCategoryMode('existing')
    }
    if (p['supplier_id']) {
        document.getElementById('p_supplier').value = p['supplier_id'];
        toggleSupplierMode('existing')
    }
}

function toggleCategoryMode(mode) {
    const existing = document.getElementById('existingCategoryFields');
    const newFields = document.getElementById('newCategoryFields');
    if (mode === 'new') {
        existing.style.display = 'none';
        newFields.style.display = 'grid';
        document.getElementById('p_category').removeAttribute('required')
    } else {
        existing.style.display = 'block';
        newFields.style.display = 'none';
        document.getElementById('p_category').setAttribute('required', '')
    }
}

function toggleSupplierMode(mode) {
    const existing = document.getElementById('existingSupplierFields');
    const newFields = document.getElementById('newSupplierFields');
    if (mode === 'new') {
        existing.style.display = 'none';
        newFields.style.display = 'grid';
        document.getElementById('p_supplier').removeAttribute('required')
    } else {
        existing.style.display = 'block';
        newFields.style.display = 'none';
        document.getElementById('p_supplier').setAttribute('required', '')
    }
}

function removeCategory() {
    const sel = document.getElementById('p_category');
    const opt = sel.options[sel.selectedIndex];
    if (!opt) {
        return
    }
    if (sel.options.length <= 1) {
        document.getElementById('catRemoveHint').textContent = 'Cannot remove the last category. Add a new one first.';
        return
    }
    removedCategory = {value: opt.value, text: opt.text};
    sel.removeChild(opt);
    document.getElementById('undoCategoryBtn').style.display = 'inline-block';
    document.getElementById('catRemoveHint').textContent = 'Removed "' + removedCategory.text + '". Click Undo to restore.'
}

function undoCategoryRemove() {
    if (!removedCategory) return;
    const sel = document.getElementById('p_category');
    const opt = new Option(removedCategory.text, removedCategory.value);
    sel.add(opt);
    sel.value = removedCategory.value;
    removedCategory = null;
    document.getElementById('undoCategoryBtn').style.display = 'none';
    document.getElementById('catRemoveHint').textContent = ''
}

function resetCategoryRemove() {
    removedCategory = null;
    const sel = document.getElementById('p_category');
    document.getElementById('undoCategoryBtn').style.display = 'none';
    document.getElementById('catRemoveHint').textContent = ''
}

function filterProducts() {
    const term = document.getElementById('search').value.toLowerCase(),
        filter = document.getElementById('statusFilter').value;
    document.querySelectorAll('#productTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) && (filter === 'all' || row.dataset.status === filter || (filter === 'active' && row.dataset.status === 'low')) ? '' : 'none'
    })
}

var removedCategory = null;

function editCategory(c) {
    document.getElementById('ce_id').value = c.category_id || '';
    document.getElementById('ce_name').value = c.category_name || '';
    document.getElementById('ce_description').value = c.description || '';
    openModal('categoryEditModal')
}

function autoFillUnitCost() {
    const sel = document.getElementById('deliveryProductSelect');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    const cost = opt.getAttribute('data-cost') || '0';
    const input = document.getElementById('deliveryUnitCost');
    if (input) input.value = cost
}

function changeReportPeriod() {
    const sel = document.getElementById('reportPeriod');
    if (!sel) return;
    window.location.href = '?page=reports&period=' + sel.value
}

document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => {
    if (e.target === m) m.classList.remove('show')
}));

// Live date display — updates every second so it always shows the correct date
function updateLiveDate() {
    const el = document.getElementById('live-date');
    if (!el) return;
    const now = new Date();
    const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    el.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
}
updateLiveDate();
setInterval(updateLiveDate, 1000);

// ── Dark mode ────────────────────────────────────────────────────────────────
function applyTheme(dark) {
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    const btn = document.getElementById('darkToggle');
    if (btn) btn.textContent = dark ? '☀️' : '🌙';
}

function toggleDarkMode() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    applyTheme(!isDark);
    localStorage.setItem('theme', !isDark ? 'dark' : 'light');
}

// Apply saved preference immediately on load (no flash)
(function () {
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(saved ? saved === 'dark' : prefersDark);
})();