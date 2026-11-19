<?php
/**
 * src/classes/ProductRepository.php
 *
 * INHERITANCE — Extends BaseRepository to inherit $pdo, $logger,
 * query(), queryAll(), execute(), and lastId().
 *
 * ENCAPSULATION — Business rules (cost ≤ price, valid discount) live
 * inside this class. Callers just call save() or archive(); they never
 * deal with SQL or validation logic directly.
 */
class ProductRepository extends BaseRepository
{
    /**
     * findById() — Fetch one active product by ID.
     *
     * Uses LINEAR SEARCH when scanning an in-memory array of active products
     * (passed in), falling back to a direct DB query when no array is provided.
     * Linear search walks every element until the matching product_id is found.
     * Time complexity: O(n).
     *
     * @param int        $id    The product_id to find.
     * @param bool       $lock  Add FOR UPDATE row lock (DB query path only).
     * @param array|null $pool  Optional in-memory product array to search first.
     */
    public function findById(int $id, bool $lock = false, ?array $pool = null): ?array
    {
        // LINEAR SEARCH — scan the in-memory pool if provided
        if ($pool !== null) {
            return SearchAlgorithms::linearSearch($pool, 'product_id', $id);
        }

        // Fall back to direct DB query (used during transaction confirm with FOR UPDATE)
        $sql = 'SELECT * FROM products WHERE product_id = ? AND active = 1';
        if ($lock) $sql .= ' FOR UPDATE';
        return $this->query($sql, [$id]);
    }

    /**
     * findByName() — Locate a product by exact name in a sorted list.
     *
     * Uses BINARY SEARCH on a list that has already been sorted alphabetically
     * by product_name (via SortAlgorithms::mergeSort). Binary search halves
     * the search space each step.
     * Time complexity: O(log n) — requires the array to be sorted first.
     *
     * @param array  $sortedProducts  Product array sorted by 'product_name'.
     * @param string $name            Exact product name to find (case-insensitive).
     */
    public function findByName(array $sortedProducts, string $name): ?array
    {
        return SearchAlgorithms::binarySearch($sortedProducts, 'product_name', $name);
    }

    /**
     * findAll() — All products joined to category and supplier.
     */
    public function findAll(): array
    {
        return $this->queryAll(
            'SELECT p.*, c.category_name, s.supplier_name
             FROM products p
             JOIN categories c ON c.category_id = p.category_id
             JOIN suppliers  s ON s.supplier_id = p.supplier_id
             ORDER BY p.active DESC, p.product_name'
        );
    }

    /**
     * save() — Insert or update a product.
     * Validates business rules before touching the database.
     *
     * @param array $data  Associative array of product fields
     * @param int   $userId Who is performing this action (for audit log)
     * @return int  The product_id that was saved
     */
    public function save(array $data, int $userId): int
    {
        // Business rule: cost must not exceed price
        if ((float)$data['cost'] > (float)$data['price']) {
            throw new Exception(
                'Product cost (₱' . number_format($data['cost'], 2) .
                ') cannot be higher than the selling price (₱' .
                number_format($data['price'], 2) . ').'
            );
        }

        if ($data['id']) {
            $this->execute(
                'UPDATE products SET product_name=?,category_id=?,supplier_id=?,
                 selling_price=?,product_cost=?,product_quantity=?,reorder_level=?,
                 product_discount=?,image_url=?,description=? WHERE product_id=?',
                [
                    $data['name'], $data['cat'], $data['sup'],
                    $data['price'], $data['cost'], $data['qty'],
                    $data['reorder'], $data['discount'], $data['image'],
                    $data['desc'], $data['id'],
                ]
            );
            $id = $data['id'];
        } else {
            $this->execute(
                'INSERT INTO products
                 (product_name,category_id,supplier_id,selling_price,product_cost,
                  product_quantity,reorder_level,product_discount,image_url,description)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [
                    $data['name'], $data['cat'], $data['sup'],
                    $data['price'], $data['cost'], $data['qty'],
                    $data['reorder'], $data['discount'],
                    $data['image'], $data['desc'],
                ]
            );
            $id = $this->lastId();
        }

        $this->logger->log($userId, 'Save product', "Product #$id: {$data['name']}");
        return $id;
    }

    /**
     * setActive() — Archive (0) or restore (1) a product.
     */
    public function setActive(int $id, bool $active, int $userId): void
    {
        $this->execute(
            'UPDATE products SET active = ? WHERE product_id = ?',
            [(int)$active, $id]
        );
        $action = $active ? 'restore_product' : 'archive_product';
        $this->logger->log($userId, $action, "Product #$id");
    }

    /**
     * decrementStock() — Reduce stock by a given quantity (used during sales).
     */
    public function decrementStock(int $productId, int $qty): void
    {
        $this->execute(
            'UPDATE products SET product_quantity = product_quantity - ? WHERE product_id = ?',
            [$qty, $productId]
        );
    }

    /**
     * incrementStock() — Increase stock (used during delivery or undo).
     */
    public function incrementStock(int $productId, int $qty, ?float $newCost = null): void
    {
        if ($newCost !== null) {
            $this->execute(
                'UPDATE products SET product_quantity = product_quantity + ?, product_cost = ? WHERE product_id = ?',
                [$qty, $newCost, $productId]
            );
        } else {
            $this->execute(
                'UPDATE products SET product_quantity = product_quantity + ? WHERE product_id = ?',
                [$qty, $productId]
            );
        }
    }
}
