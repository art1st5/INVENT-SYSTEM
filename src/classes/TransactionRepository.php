<?php
/**
 * src/classes/TransactionRepository.php
 *
 * INHERITANCE — Extends BaseRepository.
 * ENCAPSULATION — The multi-step sale logic (insert header, insert lines,
 * decrement stock) is contained here. Callers just call confirm() or undo().
 */
class TransactionRepository extends BaseRepository
{
    private ProductRepository $products;

    public function __construct(PDO $pdo, Auditable $logger, ProductRepository $products)
    {
        parent::__construct($pdo, $logger);
        $this->products = $products;
    }

    /**
     * confirm() — Atomically commit a pending sale from session data.
     * Re-verifies stock inside a transaction before writing anything.
     *
     * @param array $pending  The pending_sale array from $_SESSION
     * @param int   $userId   Who is confirming the sale
     * @return int  The new transaction_id
     */
    public function confirm(array $pending, int $userId): int
    {
        $this->pdo->beginTransaction();

        try {
            // Re-verify stock for every line item
            foreach ($pending['items'] as $item) {
                $pr = $this->products->findById($item['product_id'], true);
                if (!$pr || $pr['product_quantity'] < $item['qty']) {
                    throw new Exception(
                        '"' . $item['product_name'] . '" stock changed since preview. Please start again.'
                    );
                }
            }

            // Insert transaction header
            $totalDiscount = array_sum(array_column($pending['items'], 'discount'));
            $this->execute(
                'INSERT INTO transactions (admin_id, total_discount, total_amount) VALUES (?, ?, ?)',
                [$userId, round($totalDiscount, 2), $pending['grand_total']]
            );
            $tid = $this->lastId();

            // Insert line items and decrement stock
            $logDetails = "Sale #$tid:";
            foreach ($pending['items'] as $item) {
                $this->execute(
                    'INSERT INTO product_transactions
                     (transaction_id, product_id, selling_price, product_quantity,
                      product_cost, discount_amount, subamount)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        $tid, $item['product_id'], $item['unit_price'],
                        $item['qty'], $item['product_cost'],
                        $item['discount'], $item['subtotal'],
                    ]
                );
                $this->products->decrementStock($item['product_id'], $item['qty']);
                $logDetails .= " [{$item['product_name']} ×{$item['qty']} @₱{$item['unit_price']}]";
            }

            $this->logger->log($userId, 'Record cash sale', $logDetails . " total ₱{$pending['grand_total']}");
            $this->pdo->commit();
            return $tid;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * undo() — Reverse a saved transaction and restore all stock.
     *
     * @param int $tid    Transaction ID to reverse
     * @param int $userId Who is undoing it
     */
    public function undo(int $tid, int $userId): void
    {
        $this->pdo->beginTransaction();

        try {
            $items = $this->queryAll(
                'SELECT product_id, product_quantity FROM product_transactions WHERE transaction_id = ?',
                [$tid]
            );

            if (empty($items)) {
                throw new Exception('Transaction not found.');
            }

            foreach ($items as $item) {
                $this->products->incrementStock($item['product_id'], $item['product_quantity']);
            }

            $this->execute('DELETE FROM product_transactions WHERE transaction_id = ?', [$tid]);
            $this->execute('DELETE FROM transactions WHERE transaction_id = ?', [$tid]);

            $this->logger->log(
                $userId,
                'Undo sale',
                "Reversed transaction #$tid — stock restored for " . count($items) . " item(s)"
            );
            $this->pdo->commit();

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * getRecent() — Fetch the most recent transactions with line-item detail.
     */
    public function getRecent(int $limit = 100): array
    {
        return $this->queryAll(
            'SELECT t.*, u.user_name,
                    p.product_name, pt.product_quantity,
                    pt.selling_price, pt.product_cost,
                    pt.discount_amount, pt.subamount
             FROM transactions t
             JOIN users u ON u.user_id = t.admin_id
             JOIN product_transactions pt ON pt.transaction_id = t.transaction_id
             JOIN products p ON p.product_id = pt.product_id
             ORDER BY t.transaction_id DESC
             LIMIT ' . (int)$limit
        );
    }
}
