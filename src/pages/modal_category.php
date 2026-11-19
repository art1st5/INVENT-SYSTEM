<?php
/**
 * src/pages/modal_category.php — Edit Category modal.
 *
 * Always present in the DOM (not page-scoped) because the edit button
 * lives on the categories page and needs the modal available there.
 * Pre-filled by editCategory() in app.js when the ✎ button is clicked.
 */
?>

<div class="modal" id="categoryEditModal">
    <div class="modal-card">
        <div class="modal-head">
            <h2>Edit Category</h2>
            <button onclick="closeModal('categoryEditModal')">✕</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf"   value="<?= h(csrf()) ?>">
            <input type="hidden" name="action" value="edit_category">
            <!-- id populated by editCategory() in app.js -->
            <input type="hidden" name="id" id="ce_id">
            <div class="form-panel" style="display:flex;flex-direction:column;gap:16px">
                <label>Name
                    <input id="ce_name" name="name" required>
                </label>
                <label>Description
                    <textarea id="ce_description" name="description"></textarea>
                </label>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeModal('categoryEditModal')">Cancel</button>
                <button class="btn primary">Update Category</button>
            </div>
        </form>
    </div>
</div>
