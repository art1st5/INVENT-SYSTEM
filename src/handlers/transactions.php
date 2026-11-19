<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Builds sale previews and handles confirmation, cancellation and undo actions. Customize
 * cart validation and feedback here; stock persistence belongs in TransactionRepository.
 */
/**
 * src/handlers/transactions.php
 *
 * Uses TransactionRepository (OOP layer) for all sale operations.
 * The repository handles validation, stock updates, and audit logging.
 */

// ── Preview ───────────────────────────────────────────────────────────────────
// Action sale_preview: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'sale_preview') {

    $rawItems = $_POST['items'] ?? [];
    if (empty($rawItems))
        throw new Exception('Add at least one product to the sale.');

    $items      = [];
    $grandTotal = 0;
    $seenIds    = [];

    foreach ($rawItems as $i => $row) {
        $pid = (int)($row['product_id'] ?? 0);
        $qty = (int)($row['qty']        ?? 0);

        if (!$pid || $qty < 1)
            throw new Exception('Row ' . ($i + 1) . ': product and quantity are required.');

        if (in_array($pid, $seenIds, true))
            throw new Exception('Duplicate product on row ' . ($i + 1) . '. Combine quantities instead.');
        $seenIds[] = $pid;

        // Use ProductRepository — OOP abstraction over raw SQL
        $pr = $productRepo->findById($pid);
        if (!$pr)
            throw new Exception('Row ' . ($i + 1) . ': product not found or inactive.');
        if ($pr['product_quantity'] < $qty)
            throw new Exception('"' . $pr['product_name'] . '": only ' . $pr['product_quantity'] . ' in stock.');

        $price    = (float)$pr['selling_price'];
        $discPct  = (float)$pr['product_discount'];
        $discount = round($price * $qty * $discPct / 100, 2);
        $subtotal = round($price * $qty - $discount, 2);
        $grandTotal += $subtotal;

        $items[] = [
            'product_id'   => $pid,
            'product_name' => $pr['product_name'],
            'qty'          => $qty,
            'unit_price'   => $price,
            'product_cost' => (float)$pr['product_cost'],
            'discount_pct' => $discPct,
            'discount'     => $discount,
            'subtotal'     => $subtotal,
        ];
    }

    $_SESSION['pending_sale'] = [
        'items'       => $items,
        'grand_total' => round($grandTotal, 2),
    ];

    go('transactions');
}

// ── Confirm ───────────────────────────────────────────────────────────────────
// Action sale_confirm: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'sale_confirm') {

    $pending = $_SESSION['pending_sale'] ?? null;
    if (!$pending || empty($pending['items']))
        throw new Exception('No pending sale found. Please start again.');

    // Sort cart items by product name (insertion sort) before saving
    $pending['items'] = SortAlgorithms::insertionSort($pending['items'], 'product_name');

    // TransactionRepository handles stock re-verification, inserts, and audit log
    $tid = $transactionRepo->confirm($pending, (int)$_SESSION['uid']);

    // Push the confirmed transaction ID onto the undo stack (Stack data structure)
    $undoStack = Stack::fromArray($_SESSION['undo_stack'] ?? []);
    $undoStack->push($tid);
    $_SESSION['undo_stack'] = $undoStack->toArray();

    unset($_SESSION['pending_sale']);
    $count = count($pending['items']);
    flash("Sale #$tid confirmed — $count item" . ($count !== 1 ? 's' : '') . ", total ₱{$pending['grand_total']}.");
    go('transactions');
}

// ── Cancel ────────────────────────────────────────────────────────────────────
// Action sale_cancel: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'sale_cancel') {
    unset($_SESSION['pending_sale']);
    go('transactions');
}

// ── Undo ──────────────────────────────────────────────────────────────────────
// Action sale_undo: customize its validation and business rules here; keep form action, permissions and database fields aligned.
if ($act === 'sale_undo') {
    owner();
    $tid = (int)($_POST['transaction_id'] ?? 0);
    if (!$tid) throw new Exception('Invalid transaction.');

    // Pop from the undo stack if this transaction is the top entry
    // (Stack data structure — LIFO undo history)
    $undoStack = Stack::fromArray($_SESSION['undo_stack'] ?? []);
    if (!$undoStack->isEmpty() && $undoStack->peek() === $tid) {
        $undoStack->pop();
        $_SESSION['undo_stack'] = $undoStack->toArray();
    }

    // TransactionRepository handles reversal and stock restoration
    $transactionRepo->undo($tid, (int)$_SESSION['uid']);

    flash("Sale #$tid undone. Stock restored.");
    go('transactions');
}
