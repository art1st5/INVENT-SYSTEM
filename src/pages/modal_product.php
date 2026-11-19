<?php
/**
 * src/pages/modal_product.php — Add / Edit Product modal (horizontal layout).
 */
?>

<div class="modal" id="productModal">
    <div class="modal-card" style="width:min(900px,96vw);max-height:92vh;overflow-y:auto;background:var(--surface);color:var(--ink);">
        <div class="modal-head" style="border-bottom:1px solid var(--border);">
            <h2 id="modalTitle" style="color:var(--ink);">Add Product</h2>
            <button onclick="closeModal('productModal')" style="color:var(--ink);">✕</button>
        </div>
        <style>
            #productModal .modal-card label          { color: var(--ink); }
            #productModal .modal-card h3             { color: var(--ink); }
            #productModal .modal-card .radio-toggle  { color: var(--ink); }
            #productModal .modal-card .cat-action-hint { color: var(--muted); }
            #productModal .modal-card small          { color: var(--muted); }

            #productModal .img-section,
            #productModal .category-section,
            #productModal .supplier-section {
                background: var(--field-bg);
                border-color: var(--border);
            }

            #productModal input,
            #productModal select,
            #productModal textarea {
                background: var(--surface);
                color: var(--ink);
                border-color: var(--border);
            }

            #productModal input::placeholder,
            #productModal textarea::placeholder { color: var(--muted); }

            #productModal input:focus,
            #productModal select:focus,
            #productModal textarea:focus {
                border-color: var(--ink);
                outline: none;
            }
        </style>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf"      value="<?= h(csrf()) ?>">
            <input type="hidden" name="action"    value="save_product">
            <input type="hidden" name="id"        id="p_id">
            <input type="hidden" name="old_image" id="p_old_image">

            <!-- ── Row 1: name spans full width ───────────────────────── -->
            <div style="margin-bottom:10px;">
                <label style="display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:700;color:var(--ink)">
                    Product Name
                    <input id="p_name" name="name" required
                           style="border:1px solid var(--border);border-radius:7px;padding:8px 10px;font:inherit;">
                </label>
            </div>

            <!-- ── Row 2: price, cost, qty, reorder, discount ─────────── -->
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:10px;">
                <label style="display:flex;flex-direction:column;gap:4px;font-size:11px;font-weight:700;color:var(--ink);">
                    Selling Price (₱)
                    <input id="p_price" name="price" type="number" min="0" step="0.01" required
                           oninput="validateCostVsPrice()"
                           style="border:1px solid var(--border);border-radius:7px;padding:7px 8px;font:inherit;">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-size:11px;font-weight:700;color:var(--ink);">
                    Product Cost (₱)
                    <input id="p_cost" name="cost" type="number" min="0" step="0.01" required
                           oninput="validateCostVsPrice()"
                           style="border:1px solid var(--border);border-radius:7px;padding:7px 8px;font:inherit;">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-size:11px;font-weight:700;color:var(--ink);">
                    Quantity
                    <input id="p_quantity" name="quantity" type="number" min="0" required
                           style="border:1px solid var(--border);border-radius:7px;padding:7px 8px;font:inherit;">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-size:11px;font-weight:700;color:var(--ink);">
                    Reorder Level
                    <input id="p_reorder" name="reorder" type="number" min="0" value="5" required
                           style="border:1px solid var(--border);border-radius:7px;padding:7px 8px;font:inherit;">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-size:11px;font-weight:700;color:var(--ink);">
                    Discount (%)
                    <input id="p_discount" name="discount" type="number" min="0" max="100" step="0.01" value="0"
                           style="border:1px solid var(--border);border-radius:7px;padding:7px 8px;font:inherit;">
                </label>
            </div>

            <!-- ── Row 3: description + image side by side ────────────── -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">

                <!-- Description -->
                <label style="display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:700;color:var(--ink);">
                    Description
                    <textarea id="p_description" name="description"
                              style="border:1px solid var(--border);border-radius:8px;padding:10px 12px;font:inherit;min-height:90px;resize:vertical;"></textarea>
                </label>

                <!-- Image -->
                <div class="img-section" style="margin-top:0;">
                    <div class="img-section-head">
                        <h3 style="font-size:12px;font-weight:700;color:var(--ink);margin:0 0 8px;">Product Image</h3>
                        <div class="img-mode-toggle" style="margin-bottom:8px;">
                            <label class="radio-toggle">
                                <input type="radio" name="img_mode" value="upload" checked
                                       onchange="toggleImgMode('upload')"> Upload
                            </label>
                            <label class="radio-toggle">
                                <input type="radio" name="img_mode" value="url"
                                       onchange="toggleImgMode('url')"> URL
                            </label>
                        </div>
                    </div>
                    <div id="imgPreviewWrap" style="display:none;margin-bottom:8px;">
                        <img id="imgPreview"
                             style="max-height:80px;max-width:100%;border-radius:8px;border:1px solid var(--border);object-fit:cover;"
                             alt="">
                        <button type="button" class="btn mini danger-btn"
                                style="margin-top:5px;display:block"
                                onclick="clearProductImage()">Remove</button>
                    </div>
                    <div id="imgUploadField">
                        <label style="font-size:11px;font-weight:700;color:var(--ink);">
                            Choose file
                            <input type="file" name="image_file" id="p_image_file"
                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                   onchange="previewUploadedImage(this)">
                        </label>
                        <small style="color:var(--muted);font-size:10px;">JPG, PNG, GIF or WebP · max 2 MB</small>
                    </div>
                    <div id="imgUrlField" style="display:none;">
                        <label style="font-size:11px;font-weight:700;color:var(--ink);">
                            Image URL
                            <input type="url" name="image" id="p_image"
                                   placeholder="https://example.com/photo.jpg"
                                   oninput="previewImageUrl(this.value)"
                                   style="border:1px solid var(--border);border-radius:8px;padding:8px 10px;font:inherit;width:100%;">
                        </label>
                    </div>
                </div>

            </div>

            <!-- ── Row 4: category + supplier side by side ────────────── -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:6px;">

                <!-- Category -->
                <div class="category-section" style="margin-top:0;">
                    <div class="category-section-head">
                        <h3>Category</h3>
                        <div class="category-mode-toggle">
                            <label class="radio-toggle">
                                <input type="radio" name="category_mode" value="existing" checked
                                       onchange="toggleCategoryMode('existing')"> Existing
                            </label>
                            <label class="radio-toggle">
                                <input type="radio" name="category_mode" value="new"
                                       onchange="toggleCategoryMode('new')"> New
                            </label>
                        </div>
                    </div>
                    <div id="existingCategoryFields">
                        <label style="display:flex;flex-direction:column;gap:5px;font-size:11px;font-weight:700;color:var(--ink);margin-bottom:10px;">
                            Select Category
                            <select id="p_category" name="category" required
                                    style="border:1px solid var(--border);border-radius:8px;padding:9px 10px;font:inherit;">
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?= $c['category_id'] ?>"><?= h($c['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="cat-action-row">
                            <button type="button" class="btn mini danger-btn" onclick="removeCategory()">Remove</button>
                            <button type="button" class="btn mini undo-btn" id="undoCategoryBtn"
                                    onclick="undoCategoryRemove()" style="display:none">Undo</button>
                            <span class="cat-action-hint" id="catRemoveHint"></span>
                        </div>
                    </div>
                    <div class="form-grid" id="newCategoryFields" style="display:none;">
                        <label class="wide">Category Name
                            <input name="new_category_name" placeholder="e.g. Helmets &amp; Gear">
                        </label>
                        <label class="wide">Description
                            <textarea name="new_category_desc" placeholder="Optional description"></textarea>
                        </label>
                    </div>
                </div>

                <!-- Supplier -->
                <div class="supplier-section" style="margin-top:0;">
                    <div class="supplier-section-head">
                        <h3>Supplier</h3>
                        <div class="supplier-mode-toggle">
                            <label class="radio-toggle">
                                <input type="radio" name="supplier_mode" value="existing" checked
                                       onchange="toggleSupplierMode('existing')"> Existing
                            </label>
                            <label class="radio-toggle">
                                <input type="radio" name="supplier_mode" value="new"
                                       onchange="toggleSupplierMode('new')"> New
                            </label>
                        </div>
                    </div>
                    <div id="existingSupplierFields">
                        <label style="display:flex;flex-direction:column;gap:5px;font-size:11px;font-weight:700;color:var(--ink);margin-bottom:10px;">
                            Select Supplier
                            <select id="p_supplier" name="supplier" required
                                    style="border:1px solid var(--border);border-radius:8px;padding:9px 10px;font:inherit;">
                                <?php foreach ($sups as $s): ?>
                                    <option value="<?= $s['supplier_id'] ?>"><?= h($s['supplier_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="form-grid" id="newSupplierFields" style="display:none;">
                        <label class="wide">Supplier Name
                            <input name="new_supplier_name" placeholder="e.g. ABC Motorcycle Parts">
                        </label>
                        <label>Contact Number
                            <input name="new_supplier_contact" placeholder="0917 123 4567">
                        </label>
                        <label class="wide">Address
                            <textarea name="new_supplier_address" placeholder="Supplier address"></textarea>
                        </label>
                    </div>
                </div>

            </div>

            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeModal('productModal')">Cancel</button>
                <button class="btn primary">Save Product</button>
            </div>
        </form>
    </div>
</div>


