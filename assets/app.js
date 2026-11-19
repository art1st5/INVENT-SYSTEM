/**
 * app.js — Client-side JavaScript for the Matet Inventory System.
 *
 * PURPOSE:
 *   Handles all interactive behaviour in the browser:
 *     - Opening and closing modal dialogs
 *     - Pre-filling the product modal when editing
 *     - Toggling between existing/new category and supplier in the product form
 *     - Temporarily removing a category from the dropdown (remove/undo)
 *     - Live product search and filter on the products page
 *     - Pre-filling the delivery unit cost from the selected product
 *     - Navigating the reports page period filter
 *     - Live clock display in the topbar
 *     - Dark/light mode toggle with localStorage persistence
 *
 * This file is loaded at the bottom of index.php via:
 *   <script src="assets/app.js"></script>
 */


// =============================================================================
// MODAL HELPERS
// Modals are hidden <div class="modal"> elements. Adding the class 'show'
// makes them visible via CSS (display:flex). Removing 'show' hides them.
// =============================================================================

/**
 * openModal(id) — Make a modal visible by adding the 'show' CSS class.
 * @param {string} id - The id attribute of the modal element.
 */
function openModal(id) {
    document.getElementById(id)?.classList.add('show');
}

/**
 * closeModal(id) — Hide a modal by removing the 'show' CSS class.
 * @param {string} id - The id attribute of the modal element.
 */
function closeModal(id) {
    document.getElementById(id)?.classList.remove('show');
}

// Close any modal when the user clicks the dark overlay behind it.
// e.target === m means the click was on the backdrop, not on the modal card itself.
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => {
    if (e.target === m) m.classList.remove('show');
}));


// =============================================================================
// PRODUCT MODAL
// The same modal is used for both adding new products and editing existing ones.
// =============================================================================

/**
 * resetProduct() — Clear the product modal and set it to "Add" mode.
 *
 * Called when the "+ Add Product" button is clicked.
 * Resets the entire form, clears the hidden ID field (so the server
 * knows this is a new product, not an update), and restores all
 * toggle states to their defaults.
 */
function resetProduct() {
    document.querySelector('#productModal form').reset(); // Clear all inputs
    document.getElementById('p_id').value        = '';   // id='' means INSERT not UPDATE
    document.getElementById('p_old_image').value = '';   // no existing image
    document.getElementById('modalTitle').textContent = 'Add Product';

    // Reset image section to "upload" mode with no preview
    toggleImgMode('upload');
    clearProductImage();

    // Reset category and supplier sections back to "Select existing"
    resetCategoryRemove();
    toggleCategoryMode('existing');
    toggleSupplierMode('existing');

    // Clear any cost/price warning
    clearCostWarning();
}

// =============================================================================
// PRODUCT COST / PRICE VALIDATION
// Real-time check: if the user enters a cost higher than the selling price,
// show a warning immediately beneath the fields (before even submitting).
// The server also enforces this rule — this is just for instant feedback.
// =============================================================================

/**
 * validateCostVsPrice() — Warn if product cost exceeds selling price.
 *
 * Called by oninput on both the price and cost fields.
 * Shows a warning banner and disables the Save button until corrected.
 */
function validateCostVsPrice() {
    const priceInput = document.getElementById('p_price');
    const costInput  = document.getElementById('p_cost');
    const saveBtn    = document.querySelector('#productModal .btn.primary:last-child');

    if (!priceInput || !costInput) return;

    const price = parseFloat(priceInput.value) || 0;
    const cost  = parseFloat(costInput.value)  || 0;

    // Find or create the warning element
    let warning = document.getElementById('costPriceWarning');
    if (!warning) {
        warning = document.createElement('p');
        warning.id = 'costPriceWarning';
        warning.style.cssText = 'color:#d34750;font-size:12px;margin:4px 0 0;font-weight:600';
        // Insert after the cost input label
        costInput.closest('label').after(warning);
    }

    if (cost > price && price > 0) {
        // Show warning and block submission
        warning.textContent = '⚠ Product cost cannot exceed the selling price.';
        if (saveBtn) saveBtn.disabled = true;
        costInput.style.borderColor = '#d34750';
    } else {
        // Clear warning and re-enable submission
        warning.textContent = '';
        if (saveBtn) saveBtn.disabled = false;
        costInput.style.borderColor = '';
    }
}

/**
 * clearCostWarning() — Remove any cost/price warning (called on modal reset).
 */
function clearCostWarning() {
    const warning = document.getElementById('costPriceWarning');
    if (warning) warning.textContent = '';
    const costInput = document.getElementById('p_cost');
    if (costInput) costInput.style.borderColor = '';
    const saveBtn = document.querySelector('#productModal .btn.primary:last-child');
    if (saveBtn) saveBtn.disabled = false;
}

/**
 * editProduct(p) — Pre-fill the product modal with an existing product's data.
 *
 * Called when the ✎ edit button is clicked in the products table.
 * The PHP code passes the product row as a JSON object (via json_encode).
 *
 * @param {object} p - Product data object from the database row.
 */
function editProduct(p) {
    openModal('productModal');
    document.getElementById('modalTitle').textContent = 'Edit Product';

    // Map form field IDs to database column names and fill each input.
    // This loop avoids repeating getElementById(...).value = ... for every field.
    for (const [id, key] of Object.entries({
        id:          'product_id',
        name:        'product_name',
        price:       'selling_price',
        cost:        'product_cost',
        quantity:    'product_quantity',
        reorder:     'reorder_level',
        discount:    'product_discount',
        description: 'description',
    })) {
        document.getElementById('p_' + id).value = p[key] ?? '';
    }

    // ── Restore the existing image ────────────────────────────────────────
    // Store the current image path in old_image so the handler can keep it
    // unchanged if the user doesn't pick a new one.
    const existingImage = p['image_url'] || '';
    document.getElementById('p_old_image').value = existingImage;

    if (existingImage) {
        // If the saved value starts with 'uploads/', it was an uploaded file.
        // Otherwise it's a URL. Set the matching mode and show the preview.
        if (existingImage.startsWith('uploads/')) {
            toggleImgMode('upload');
        } else {
            toggleImgMode('url');
            document.getElementById('p_image').value = existingImage;
        }
        showImgPreview(existingImage);
    } else {
        // No image at all — reset to default upload mode with no preview
        toggleImgMode('upload');
        clearProductImage();
    }

    // Set the category dropdown to the product's current category.
    if (p['category_id']) {
        document.getElementById('p_category').value = p['category_id'];
        resetCategoryRemove(); // Clear any pending remove/undo state
        toggleCategoryMode('existing');
    }

    // Set the supplier dropdown to the product's current supplier.
    if (p['supplier_id']) {
        document.getElementById('p_supplier').value = p['supplier_id'];
        toggleSupplierMode('existing');
    }
}


// =============================================================================
// PRODUCT IMAGE — UPLOAD / URL TOGGLE + LIVE PREVIEW
//
// The image section has two modes:
//   "upload" — file picker; user selects an image from their computer.
//              The file is sent to the server and saved in /uploads/.
//   "url"    — text input; user pastes a web URL.
//
// A live preview appears as soon as a file is chosen or a URL is typed,
// so the user can verify the image before saving.
// =============================================================================

/**
 * toggleImgMode(mode) — Switch between upload and URL sub-panels.
 *
 * Also syncs the radio button state so the UI stays consistent when
 * called programmatically (e.g. from editProduct or resetProduct).
 *
 * @param {string} mode - 'upload' or 'url'
 */
function toggleImgMode(mode) {
    const uploadField = document.getElementById('imgUploadField');
    const urlField    = document.getElementById('imgUrlField');
    if (!uploadField || !urlField) return;

    if (mode === 'url') {
        uploadField.style.display = 'none';
        urlField.style.display    = 'block';
    } else {
        uploadField.style.display = 'block';
        urlField.style.display    = 'none';
    }

    // Keep the radio buttons in sync when this is called from code
    document.querySelectorAll('input[name="img_mode"]').forEach(r => {
        r.checked = (r.value === mode);
    });
}

/**
 * showImgPreview(src) — Display the preview image with the given src.
 *
 * Shows the preview container and sets the <img> src.
 * Called by previewUploadedImage() and previewImageUrl().
 *
 * @param {string} src - A data URL (from FileReader) or a web URL.
 */
function showImgPreview(src) {
    const wrap = document.getElementById('imgPreviewWrap');
    const img  = document.getElementById('imgPreview');
    if (!wrap || !img || !src) return;

    img.src          = src;
    wrap.style.display = 'block';
}

/**
 * previewUploadedImage(input) — Show a local preview of the chosen file.
 *
 * Uses the FileReader API to read the selected file entirely in the browser
 * as a data URL (a base64-encoded string). This lets us show the image
 * immediately without uploading anything yet — the actual upload happens
 * when the form is submitted.
 *
 * @param {HTMLInputElement} input - The file input element.
 */
function previewUploadedImage(input) {
    if (!input.files || !input.files[0]) return;

    const file   = input.files[0];
    const reader = new FileReader();

    // onload fires when FileReader finishes reading the file
    reader.onload = function (e) {
        showImgPreview(e.target.result); // e.target.result is the data URL
    };

    // Read the file as a base64-encoded data URL for the <img> src
    reader.readAsDataURL(file);
}

/**
 * previewImageUrl(url) — Show a preview of the image at the typed URL.
 *
 * Simply sets the preview src to the URL value. If the URL is invalid or
 * the image can't load, the browser will show its default broken-image icon.
 *
 * @param {string} url - The URL typed into the URL input field.
 */
function previewImageUrl(url) {
    if (!url) {
        clearProductImage();
        return;
    }
    showImgPreview(url);
}

/**
 * clearProductImage() — Remove the preview and reset both image inputs.
 *
 * Called by the "Remove image" button and by resetProduct().
 * Also clears old_image so the server removes the image on save.
 *
 * NOTE on resetting the file input:
 *   Browsers block setting .value on file inputs for security reasons.
 *   We reset it by cloning the element, which creates a fresh copy with
 *   no file selected, then re-attaching the onchange event listener.
 */
function clearProductImage() {
    // Hide the preview
    const wrap = document.getElementById('imgPreviewWrap');
    const img  = document.getElementById('imgPreview');
    if (wrap) wrap.style.display = 'none';
    if (img)  img.src = '';

    // Reset the file input by cloning it (browsers block .value = '' on file inputs)
    const fileInput = document.getElementById('p_image_file');
    if (fileInput) {
        const clone = fileInput.cloneNode(true);
        // Re-attach the onchange listener since cloneNode doesn't copy event listeners
        clone.addEventListener('change', function () { previewUploadedImage(this); });
        fileInput.parentNode.replaceChild(clone, fileInput);
    }

    // Clear the URL input
    const urlInput = document.getElementById('p_image');
    if (urlInput) urlInput.value = '';

    // Clear the hidden old_image field so the server removes the existing image
    const oldImg = document.getElementById('p_old_image');
    if (oldImg) oldImg.value = '';
}


// =============================================================================
// CATEGORY SECTION TOGGLE
// The product modal's category section has two modes:
//   "existing" — shows a dropdown of existing categories
//   "new"      — shows a text input to create a brand-new category inline
// =============================================================================

/**
 * toggleCategoryMode(mode) — Switch between existing and new category sub-panels.
 *
 * When switching to "new", the select dropdown is hidden and its required
 * attribute is removed (so the form can submit without a selection).
 * When switching back to "existing", the select is shown again and re-required.
 *
 * @param {string} mode - 'existing' or 'new'
 */
function toggleCategoryMode(mode) {
    const existing  = document.getElementById('existingCategoryFields');
    const newFields = document.getElementById('newCategoryFields');

    if (mode === 'new') {
        existing.style.display  = 'none';
        newFields.style.display = 'grid';
        document.getElementById('p_category').removeAttribute('required');
    } else {
        existing.style.display  = 'block';
        newFields.style.display = 'none';
        document.getElementById('p_category').setAttribute('required', '');
    }
}


// =============================================================================
// CATEGORY REMOVE / UNDO
// Lets the user hide a category from the dropdown within the modal session
// without permanently deleting it. Useful for de-cluttering the list when
// adding a product that only fits one specific category.
// The removal is purely client-side — refreshing or closing the modal restores it.
// =============================================================================

// Holds the most recently removed option so it can be put back via Undo.
var removedCategory = null;

/**
 * removeCategory() — Remove the currently selected category from the dropdown.
 *
 * Saves the removed option in removedCategory so it can be restored.
 * Shows the Undo button and a hint message.
 * Prevents removal if only one category remains (the form needs at least one).
 */
function removeCategory() {
    const sel = document.getElementById('p_category');
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;

    if (sel.options.length <= 1) {
        // Can't remove the last option — the field would be empty and unsubmittable.
        document.getElementById('catRemoveHint').textContent =
            'Cannot remove the last category. Add a new one first.';
        return;
    }

    // Save the removed option data before deleting it from the DOM.
    removedCategory = { value: opt.value, text: opt.text };
    sel.removeChild(opt);

    // Show the Undo button and a descriptive hint.
    document.getElementById('undoCategoryBtn').style.display = 'inline-block';
    document.getElementById('catRemoveHint').textContent =
        'Removed "' + removedCategory.text + '". Click Undo to restore.';
}

/**
 * undoCategoryRemove() — Restore the last removed category option.
 *
 * Re-adds the saved option to the dropdown, selects it,
 * and hides the Undo button.
 */
function undoCategoryRemove() {
    if (!removedCategory) return;

    const sel = document.getElementById('p_category');
    const opt = new Option(removedCategory.text, removedCategory.value);
    sel.add(opt);
    sel.value = removedCategory.value; // Select the restored option

    // Clear the saved state and hide the Undo button.
    removedCategory = null;
    document.getElementById('undoCategoryBtn').style.display = 'none';
    document.getElementById('catRemoveHint').textContent = '';
}

/**
 * resetCategoryRemove() — Clear any pending remove/undo state.
 *
 * Called when opening the modal fresh (resetProduct) or when switching
 * to edit mode so we don't carry over state from a previous session.
 */
function resetCategoryRemove() {
    removedCategory = null;
    document.getElementById('undoCategoryBtn').style.display = 'none';
    document.getElementById('catRemoveHint').textContent = '';
}


// =============================================================================
// SUPPLIER SECTION TOGGLE
// Same pattern as the category toggle — two modes in the product modal.
// =============================================================================

/**
 * toggleSupplierMode(mode) — Switch between existing and new supplier sub-panels.
 * @param {string} mode - 'existing' or 'new'
 */
function toggleSupplierMode(mode) {
    const existing  = document.getElementById('existingSupplierFields');
    const newFields = document.getElementById('newSupplierFields');

    if (mode === 'new') {
        existing.style.display  = 'none';
        newFields.style.display = 'grid';
        document.getElementById('p_supplier').removeAttribute('required');
    } else {
        existing.style.display  = 'block';
        newFields.style.display = 'none';
        document.getElementById('p_supplier').setAttribute('required', '');
    }
}


// =============================================================================
// EDIT CATEGORY MODAL
// =============================================================================

/**
 * editCategory(c) — Pre-fill the Edit Category modal with a category's data.
 *
 * Called when the ✎ button is clicked in the categories table.
 * PHP passes the category row as a JSON object.
 *
 * @param {object} c - Category data object from the database row.
 */
function editCategory(c) {
    document.getElementById('ce_id').value          = c.category_id  || '';
    document.getElementById('ce_name').value        = c.category_name || '';
    document.getElementById('ce_description').value = c.description   || '';
    openModal('categoryEditModal');
}


// =============================================================================
// PRODUCT TABLE — SEARCH AND FILTER
// Runs entirely in the browser. Hides rows that don't match the search term
// or the selected status filter without making a new server request.
// =============================================================================

/**
 * filterProducts() — Show only product rows matching the search term and status filter.
 *
 * Reads the search box value and the status dropdown, then loops through
 * every row in the product table and sets its display to '' (visible) or 'none' (hidden).
 *
 * Each <tr> has a data-status attribute set by PHP ('active', 'low', 'archived')
 * which the status filter matches against.
 */
function filterProducts() {
    const term   = document.getElementById('search').value.toLowerCase();
    const filter = document.getElementById('statusFilter').value;

    document.querySelectorAll('#productTable tbody tr').forEach(row => {
        // Text match: does the row contain the search term anywhere?
        const textMatch = row.textContent.toLowerCase().includes(term);

        // Status match:
        //   'all'      — show everything
        //   'active'   — show 'active' and 'low' (low stock is still active)
        //   'low'      — show only 'low'
        //   'archived' — show only 'archived'
        const statusMatch = filter === 'all'
            || row.dataset.status === filter
            || (filter === 'active' && row.dataset.status === 'low');

        row.style.display = (textMatch && statusMatch) ? '' : 'none';
    });
}


// =============================================================================
// DELIVERY — AUTO-FILL UNIT COST
// When the user selects a product on the Deliveries page, the Unit Cost field
// is automatically filled with that product's stored cost price.
// The cost comes from the data-cost attribute on each <option> element,
// which is set in the PHP template from the product's product_cost column.
// =============================================================================

/**
 * autoFillUnitCost() — Fill the Unit Cost field from the selected product's stored cost.
 *
 * Triggered by the onchange event on the product select dropdown.
 * The user can still edit the cost manually if the delivery price differs.
 */
function autoFillUnitCost() {

    const select = document.getElementById(
        'deliveryProductSelect'
    );

    const input = document.getElementById(
        'deliveryUnitCost'
    );

    if (!select || !input) return;

    const option = select.options[select.selectedIndex];

    if (!option) return;

    input.value = option.getAttribute('data-cost') || '0';
}


// =============================================================================
// REPORTS — PERIOD FILTER
// When the user changes the period dropdown, reload the reports page with
// the new ?period= URL parameter. The PHP code then re-runs the SQL queries
// with the appropriate date filter applied.
// =============================================================================

/**
 * changeReportPeriod() — Reload the reports page with the selected period filter.
 *
 * Triggered by the onchange event on the period select dropdown.
 */
function changeReportPeriod() {
    const sel = document.getElementById('reportPeriod');
    if (!sel) return;
    window.location.href = '?page=reports&period=' + sel.value;
}


// =============================================================================
// LIVE DATE — TOPBAR CLOCK
// The date shown in the top-right corner is updated every second using
// JavaScript so it always reflects the real current date — even if the
// browser has the page cached from a previous day.
// =============================================================================

/**
 * updateLiveDate() — Write the current date into the #live-date span.
 *
 * Format example: "Monday, Jan 6, 2025"
 * Called once immediately on load, then every 1000ms (1 second).
 */
function updateLiveDate() {
    const el = document.getElementById('live-date');
    if (!el) return;

    const now    = new Date();
    const days   = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    el.textContent = days[now.getDay()] + ', '
        + months[now.getMonth()] + ' '
        + now.getDate() + ', '
        + now.getFullYear();
}

updateLiveDate();                      // Run once immediately on page load
setInterval(updateLiveDate, 1000);     // Then update every second


// Theme toggle — black & white light/dark
function applyTheme(dark) {
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    const btn = document.getElementById('darkToggle');
    if (btn) btn.textContent = dark ? '☀️' : '🌙';
}

function toggleDarkMode() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    applyTheme(!isDark);
    try { localStorage.setItem('theme', !isDark ? 'dark' : 'light'); } catch(e) {}
}

(function () {
    var saved = null;
    try { saved = localStorage.getItem('theme'); } catch(e) {}
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(saved ? saved === 'dark' : prefersDark);
})();
