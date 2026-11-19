<?php
/**
 * src/queries.php
 *
 * Loads all shared data needed to render any page.
 * Included by index.php after POST handling, before HTML output.
 *
 * Populates these variables (all available in page templates):
 *   $cats       — all categories, alphabetically sorted
 *   $sups       — all suppliers, alphabetically sorted
 *   $products   — all products joined with category and supplier names
 *   $active     — subset of $products that are currently active
 *   $low        — subset of $active at or below their reorder level
 *   $sales      — recent 100 transactions with line-item detail
 *   $deliveries — recent 100 deliveries with line-item detail
 */

// All categories — used in product modal dropdown and categories page
$cats = getall($pdo, 'SELECT * FROM categories ORDER BY category_name');

// All suppliers — used in product modal dropdown and suppliers page
$sups = getall($pdo, 'SELECT * FROM suppliers ORDER BY supplier_name');

// All products joined to their category and supplier
// Fetched unsorted from DB, then sorted in-application using MERGE SORT
// (SortAlgorithms::mergeSort) so the sorted list can be used with binary search.
$productsRaw = getall($pdo,
    'SELECT p.*, c.category_name, s.supplier_name
     FROM   products  p
     JOIN   categories c ON c.category_id = p.category_id
     JOIN   suppliers  s ON s.supplier_id = p.supplier_id
     WHERE  p.active = 1');

// MERGE SORT — sort active products alphabetically by product_name.
// This sorted array enables binary search (SearchAlgorithms::binarySearch)
// anywhere a product needs to be looked up by name.
$products = SortAlgorithms::mergeSort($productsRaw, 'product_name');

// Also include archived products (active = 0) for the full products page list
$allProducts = getall($pdo,
    'SELECT p.*, c.category_name, s.supplier_name
     FROM   products  p
     JOIN   categories c ON c.category_id = p.category_id
     JOIN   suppliers  s ON s.supplier_id = p.supplier_id
     ORDER  BY p.active DESC, p.product_name');

// Active-only products (not archived) — used in sales/delivery dropdowns
$active = array_values(array_filter($allProducts, fn($x) => $x['active']));

// Products at or below reorder level.
// Built using a MIN-HEAP (SortAlgorithms::buildMinHeap + heapInsert/siftUp)
// so the product with the LOWEST quantity is always at index 0.
// The heap property: every parent's quantity ≤ its children's quantities.
$lowRaw = array_values(array_filter($active, fn($x) => $x['product_quantity'] <= $x['reorder_level']));
$low    = SortAlgorithms::buildMinHeap($lowRaw, 'product_quantity');

// Recent transactions with cashier name and line-item price/cost snapshots
$sales = getall($pdo,
    'SELECT t.*, u.user_name,
            p.product_name, pt.product_quantity,
            pt.selling_price, pt.product_cost,
            pt.discount_amount, pt.subamount
     FROM   transactions         t
     LEFT JOIN users             u  ON u.user_id       = t.admin_id
     JOIN   product_transactions pt ON pt.transaction_id = t.transaction_id
     JOIN   products             p  ON p.product_id    = pt.product_id
     ORDER  BY t.transaction_id DESC
     LIMIT  100');

// Recent deliveries with supplier name, product name, and quantity received
$deliveries = getall($pdo,
    'SELECT d.*,
            u.user_name,
            s.supplier_name,
            p.product_name,
            dd.quantity_received,
            dd.unit_cost
     FROM deliveries d
     LEFT JOIN users u
        ON u.user_id = d.admin_id
     LEFT JOIN suppliers s
        ON s.supplier_id = d.supplier_id
     JOIN delivery_details dd
        ON dd.delivery_id = d.delivery_id
     JOIN products p
        ON p.product_id = dd.product_id
     ORDER BY d.delivery_id DESC
     LIMIT 100'
);
