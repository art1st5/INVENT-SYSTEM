<?php
/**
 * src/classes/DeliveryRepository.php
 *
 * INHERITANCE — Extends BaseRepository.
 * ENCAPSULATION — Delivery business rules (status validation, stock update
 * only on 'received') are hidden inside this class.
 */
class DeliveryRepository extends BaseRepository
{
    private ProductRepository $products;

    public function __construct(PDO $pdo, Auditable $logger, ProductRepository $products)
    {
        parent::__construct($pdo, $logger);
        $this->products = $products;
    }

    /**
     * record() — Create a new delivery record with one product line.
     *
     * @param array $data   Validated delivery data
     * @param int   $userId Who is recording it
     * @return int  The new delivery_id
     */
    public function record(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();

        try {
            $pr = $this->products->findById($data['product_id'], true);
            if (!$pr)          throw new Exception('Product not found.');
            if (!$pr['active']) throw new Exception('Product is archived.');

            $unit  = round((float)$data['unit_cost'], 2);
            $total = round($unit * $data['qty'], 2);

            $receivedAt = $data['status'] === 'received' ? date('Y-m-d H:i:s') : null;
            $approvedBy = $data['status'] === 'received' ? $userId : null;

            $this->execute(
                'INSERT INTO deliveries
                 (supplier_id, admin_id, date_received, delivery_date,
                  delivery_status, total_amount, approved_by, approved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $data['supplier_id'] ?? $pr['supplier_id'], $userId,
                    $receivedAt, $data['date'],
                    $data['status'], $total,
                    $approvedBy, $receivedAt,
                ]
            );
            $did = $this->lastId();

            $this->execute(
                'INSERT INTO delivery_details
                 (delivery_id, product_id, quantity_received, unit_cost, discount_price, subtotal)
                 VALUES (?, ?, ?, ?, 0, ?)',
                [$did, $data['product_id'], $data['qty'], $unit, $total]
            );

            // Only increment stock when status is 'received'
            if ($data['status'] === 'received') {
                $this->products->incrementStock($data['product_id'], $data['qty'], $unit);
            }

            $this->logger->log(
                $userId,
                'Record delivery',
                "Delivery #$did - Product #{$data['product_id']} - Qty {$data['qty']}"
            );

            $this->pdo->commit();
            return $did;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * approve() — Mark a pending delivery as received and update stock.
     *
     * @param int $deliveryId  The delivery to approve
     * @param int $userId      Who is approving
     */
    public function approve(int $deliveryId, int $userId): void
    {
        $this->pdo->beginTransaction();

        try {
            // Prevent double-approval
            $delivery = $this->query(
                "SELECT * FROM deliveries
                 WHERE delivery_id = ? AND delivery_status = 'delivering'
                 FOR UPDATE",
                [$deliveryId]
            );

            if (!$delivery) {
                throw new Exception('Delivery is already received or does not exist.');
            }

            $lines = $this->queryAll(
                'SELECT * FROM delivery_details WHERE delivery_id = ?',
                [$deliveryId]
            );

            if (empty($lines)) {
                throw new Exception('Delivery has no product details.');
            }

            foreach ($lines as $line) {
                $this->products->incrementStock(
                    $line['product_id'],
                    $line['quantity_received'],
                    $line['unit_cost']
                );
            }

            $this->execute(
                "UPDATE deliveries
                 SET delivery_status = 'received',
                     date_received   = NOW(),
                     approved_by     = ?,
                     approved_at     = NOW()
                 WHERE delivery_id = ?",
                [$userId, $deliveryId]
            );

            $this->logger->log(
                $userId,
                'Receive delivery',
                "Delivery #$deliveryId approved and received."
            );

            $this->pdo->commit();

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * getRecent() — Fetch the most recent deliveries with full detail.
     */
    public function getRecent(int $limit = 100): array
    {
        return $this->queryAll(
            'SELECT d.*, u.user_name, s.supplier_name,
                    p.product_name, dd.quantity_received, dd.unit_cost
             FROM deliveries d
             LEFT JOIN users u ON u.user_id = d.admin_id
             LEFT JOIN suppliers s ON s.supplier_id = d.supplier_id
             JOIN delivery_details dd ON dd.delivery_id = d.delivery_id
             JOIN products p ON p.product_id = dd.product_id
             ORDER BY d.delivery_id DESC
             LIMIT ' . (int)$limit
        );
    }
}
