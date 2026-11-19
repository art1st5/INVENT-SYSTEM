<?php
/**
 * src/handlers/suppliers.php
 *
 * POST action handler for suppliers:
 *   - save_supplier  (owner only)
 */

if ($act === 'save_supplier') {
    owner();
    $name = trim($_POST['name'] ?? '');
    if (!$name) throw new Exception('Supplier name required');
    $q = $pdo->prepare('INSERT INTO suppliers(supplier_name,contact_num,address) VALUES(?,?,?)');
    $q->execute([$name, trim($_POST['contact'] ?? ''), trim($_POST['address'] ?? '')]);
    logit($pdo, 'Add supplier', $name);
    flash('Supplier added.');
    go('suppliers');
}

if ($act === 'edit_supplier') {
    owner();
    $id      = (int)($_POST['supplier_id'] ?? 0);
    $name    = trim($_POST['name']         ?? '');
    $contact = trim($_POST['contact']      ?? '');
    $address = trim($_POST['address']      ?? '');

    if (!$id)   throw new Exception('Invalid supplier.');
    if (!$name) throw new Exception('Supplier name is required.');

    $pdo->prepare('UPDATE suppliers SET supplier_name=?, contact_num=?, address=? WHERE supplier_id=?')
        ->execute([$name, $contact, $address, $id]);

    logit($pdo, 'Edit supplier', "Supplier #$id updated: $name");
    flash("Supplier \"$name\" updated.");
    go('suppliers');
}
