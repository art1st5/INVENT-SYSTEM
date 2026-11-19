<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Processes category create, edit and delete POST actions. Customize name validation and
 * messages here; preserve owner checks and product-reference restrictions.
 */
/**
 * src/handlers/categories.php
 *
 * POST action handlers for categories:
 *   - save_category    (owner only)
 *   - edit_category    (owner only)
 *   - delete_category  (owner only — blocked if products still use it)
 */

// Action save_category: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'save_category') {
    owner();
    $name = trim($_POST['name'] ?? '');
    if (!$name) throw new Exception('Category name required');
    $q = $pdo->prepare('INSERT INTO categories(category_name,description) VALUES(?,?)');
    $q->execute([$name, trim($_POST['description'] ?? '')]);
    logit($pdo, 'Add category', $name);
    flash('Category added.');
    go('categories');
}

// Action edit_category: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'edit_category') {
    owner();
    $id   = (int)($_POST['id']   ?? 0);
    $name = trim($_POST['name']  ?? '');
    if (!$id || !$name) throw new Exception('Category ID and name required.');
    $q = $pdo->prepare('UPDATE categories SET category_name=?,description=? WHERE category_id=?');
    $q->execute([$name, trim($_POST['description'] ?? ''), $id]);
    logit($pdo, 'Edit category', "Category #$id: $name");
    flash('Category updated.');
    go('categories');
}

// Action delete_category: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'delete_category') {
    owner();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) throw new Exception('Category ID required.');

    // Refuse deletion if any products still reference this category
    $qc = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
    $qc->execute([$id]);
    $count = (int)$qc->fetchColumn();
    if ($count > 0)
        throw new Exception("Cannot delete: $count product(s) are still using this category. Reassign them first.");

    $q = $pdo->prepare('DELETE FROM categories WHERE category_id=?');
    $q->execute([$id]);
    logit($pdo, 'Delete category', "Category #$id");
    flash('Category deleted.');
    go('categories');
}
