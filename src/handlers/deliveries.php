<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Reads delivery form inputs and delegates recording/approval to DeliveryRepository.
 * Customize form defaults and validation here; coordinate fields with the delivery page.
 */
/**
 * src/handlers/deliveries.php
 *
 * Uses DeliveryRepository (OOP layer) for all delivery operations.
 */

// Action delivery: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'delivery') {

    $id       = (int)($_POST['product']  ?? 0);
    $qty      = (int)($_POST['quantity'] ?? 0);
    $status   = $_POST['status']         ?? 'received';
    $date     = $_POST['date']           ?? date('Y-m-d');
    $rawUnit  = trim((string)($_POST['unit_cost'] ?? ''));
    $supId    = (int)($_POST['supplier'] ?? 0) ?: null; // null if blank/0

    if (!$id)  throw new Exception('No product selected.');
    if ($qty < 1) throw new Exception('Quantity must be at least 1.');
    if (!in_array($status, ['received', 'delivering'], true))
        throw new Exception('Invalid delivery status.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
        throw new Exception('Invalid delivery date.');

    if ($rawUnit !== '' && (!is_numeric($rawUnit) || (float)$rawUnit < 0))
        throw new Exception('Enter a valid unit cost.');

    // Default unit cost from product if not provided
    $pr = $productRepo->findById($id);
    if (!$pr) throw new Exception('Product not found.');

    $unitCost = $rawUnit !== '' ? (float)$rawUnit : (float)$pr['product_cost'];

    // DeliveryRepository handles the full delivery record + stock update
    $deliveryRepo->record([
        'product_id'  => $id,
        'supplier_id' => $supId,
        'qty'         => $qty,
        'status'      => $status,
        'date'        => $date,
        'unit_cost'   => $unitCost,
    ], (int)$_SESSION['uid']);

    flash('Delivery recorded successfully.');
    go('deliveries');
}

// Action receive_delivery: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'receive_delivery') {

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) throw new Exception('Invalid delivery ID.');

    // DeliveryRepository handles approval + stock increment
    $deliveryRepo->approve($id, (int)$_SESSION['uid']);

    flash('Delivery approved. Stock has been updated.');
    go('deliveries');
}
