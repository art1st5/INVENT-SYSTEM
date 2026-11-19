<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Processes product forms, image uploads, inline category/supplier creation and
 * archive/restore. Customize upload size (currently 2 MB), allowed image types and form
 * defaults here; match the product modal and preserve file validation.
 */
/**
 * src/handlers/products.php
 *
 * Uses ProductRepository (OOP layer) for save, archive, and restore.
 * Image handling and category/supplier inline-creation remain here
 * as they are form-processing concerns, not domain logic.
 */

// Action save_product: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'save_product') {

    notAccountant();

    $id       = (int)($_POST['id']         ?? 0);
    $name     = trim($_POST['name']        ?? '');
    $price    = (float)($_POST['price']    ?? 0);
    $cost     = (float)($_POST['cost']     ?? 0);
    $qty      = (int)($_POST['quantity']   ?? 0);
    $reorder  = (int)($_POST['reorder']    ?? 5);
    $discount = (float)($_POST['discount'] ?? 0);
    $desc     = trim($_POST['description'] ?? '');
    $image    = trim($_POST['image']       ?? '');

    // ── Image handling ────────────────────────────────────────────────────
    if (!empty($_FILES['image_file']['name'])) {
        $file    = $_FILES['image_file'];
        $fileErr = $file['error'];

        if ($fileErr !== UPLOAD_ERR_OK)
            throw new Exception('Image upload failed. Please try again.');
        if ($file['size'] > 2 * 1024 * 1024)
            throw new Exception('Image file is too large. Maximum size is 2 MB.');

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowed, true))
            throw new Exception('Only JPG, PNG, GIF, and WebP images are allowed.');

        $extMap  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $newName = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extMap[$mimeType];
        $destPath = __DIR__ . '/../../uploads/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $destPath))
            throw new Exception('Could not save the uploaded image. Check folder permissions.');

        $image = 'uploads/' . $newName;

        if ($id && !empty($_POST['old_image']) && str_starts_with($_POST['old_image'], 'uploads/')) {
            $oldFile = __DIR__ . '/../../' . $_POST['old_image'];
            if (is_file($oldFile)) @unlink($oldFile);
        }

    } elseif ($image !== '') {
        if (!filter_var($image, FILTER_VALIDATE_URL))
            throw new Exception('Image must be a valid URL.');
    } else {
        $image = trim($_POST['old_image'] ?? '');
    }

    // ── Category ──────────────────────────────────────────────────────────
    if (($_POST['category_mode'] ?? 'existing') === 'new') {
        $newCatName = trim($_POST['new_category_name'] ?? '');
        if (!$newCatName) throw new Exception('Category name is required when adding a new category.');
        $q = $pdo->prepare('INSERT INTO categories(category_name,description) VALUES(?,?)');
        $q->execute([$newCatName, trim($_POST['new_category_desc'] ?? '')]);
        $cat = (int)$pdo->lastInsertId();
    } else {
        $cat = (int)($_POST['category'] ?? 0);
    }

    // ── Supplier ──────────────────────────────────────────────────────────
    if (($_POST['supplier_mode'] ?? 'existing') === 'new') {
        $newSupName = trim($_POST['new_supplier_name'] ?? '');
        if (!$newSupName) throw new Exception('Supplier name is required when adding a new supplier.');
        $q = $pdo->prepare('INSERT INTO suppliers(supplier_name,contact_num,address) VALUES(?,?,?)');
        $q->execute([$newSupName, trim($_POST['new_supplier_contact'] ?? ''), trim($_POST['new_supplier_address'] ?? '')]);
        $sup = (int)$pdo->lastInsertId();
    } else {
        $sup = (int)($_POST['supplier'] ?? 0);
    }

    if (!$name || !$cat || !$sup || min($price, $cost, $qty, $reorder, $discount) < 0 || $discount > 100)
        throw new Exception('Check the product fields and values.');

    // ProductRepository enforces cost ≤ price rule and handles SQL
    $productRepo->save([
        'id' => $id, 'name' => $name, 'cat' => $cat, 'sup' => $sup,
        'price' => $price, 'cost' => $cost, 'qty' => $qty,
        'reorder' => $reorder, 'discount' => $discount,
        'image' => $image, 'desc' => $desc,
    ], (int)$_SESSION['uid']);

    flash('Product saved successfully.');
    go('products');
}

// Action archive_product: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'archive_product' || $act === 'restore_product') {
    notAccountant();
    $id     = (int)$_POST['id'];
    $active = $act === 'restore_product';

    // ProductRepository handles the update and audit log
    $productRepo->setActive($id, $active, (int)$_SESSION['uid']);

    flash('Product status updated.');
    go('products');
}
