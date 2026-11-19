<?php
/**
 * src/classes/SortAlgorithms.php
 *
 * Implements three sorting algorithms:
 *
 *   insertionSort — simple, efficient on small or nearly-sorted arrays.
 *                   Used to sort cart line items by product name before
 *                   saving a transaction.
 *                   Time complexity: O(n²) worst, O(n) best (already sorted).
 *
 *   mergeSort     — divide-and-conquer, stable, consistent performance.
 *                   Used to sort the full product list alphabetically so
 *                   binary search can be applied to it.
 *                   Time complexity: O(n log n) always.
 *
 *   heapInsert    — inserts a single item into a min-heap and sifts it up
 *   / siftUp        to restore the heap property.
 *                   Used to maintain a priority queue of low-stock products
 *                   where the product with the lowest quantity is always
 *                   at the top (index 0).
 *                   Time complexity per insertion: O(log n).
 */
class SortAlgorithms
{
    // ─────────────────────────────────────────────────────────────────────────
    // INSERTION SORT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * insertionSort() — Sort an array of rows by a given key (ascending).
     *
     * Algorithm:
     *   For each element starting at index 1, compare it with the elements
     *   before it and shift them right until the correct position is found,
     *   then insert the element there. The left portion is always sorted.
     *
     * Usage: sort cart line items by product_name before saving a transaction.
     *
     * @param array  $data  Array of associative arrays to sort (modified in place).
     * @param string $key   The key to sort by (e.g. 'product_name').
     * @return array        The sorted array.
     */
    public static function insertionSort(array $data, string $key): array
    {
        $n = count($data);

        for ($i = 1; $i < $n; $i++) {
            $current = $data[$i];           // The element being positioned
            $j       = $i - 1;

            // Shift elements that are greater than $current one position right
            while ($j >= 0 && strcmp(
                strtolower((string)($data[$j][$key] ?? '')),
                strtolower((string)($current[$key]  ?? ''))
            ) > 0) {
                $data[$j + 1] = $data[$j];
                $j--;
            }

            // Insert $current into its correct sorted position
            $data[$j + 1] = $current;
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MERGE SORT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * mergeSort() — Recursively sort an array using divide-and-conquer.
     *
     * Algorithm:
     *   1. Base case: arrays of 0 or 1 elements are already sorted.
     *   2. Split the array into two halves.
     *   3. Recursively sort each half.
     *   4. Merge the two sorted halves back into one sorted array.
     *
     * Usage: sort the full product list by product_name so binary search works.
     *
     * @param array  $data  Array of associative arrays to sort.
     * @param string $key   The key to sort by.
     * @return array        A new sorted array (original is not modified).
     */
    public static function mergeSort(array $data, string $key): array
    {
        $n = count($data);

        // Base case — a single element or empty array is already sorted
        if ($n <= 1) {
            return $data;
        }

        // Divide: split at the midpoint
        $mid   = intdiv($n, 2);
        $left  = self::mergeSort(array_slice($data, 0, $mid),  $key);
        $right = self::mergeSort(array_slice($data, $mid),      $key);

        // Conquer: merge the two sorted halves
        return self::merge($left, $right, $key);
    }

    /**
     * merge() — Combine two sorted arrays into one sorted array.
     *
     * Compares the front elements of each half and always picks the smaller
     * one, building the merged result left to right.
     */
    private static function merge(array $left, array $right, string $key): array
    {
        $result = [];
        $i = $j = 0;

        while ($i < count($left) && $j < count($right)) {
            $lVal = strtolower((string)($left[$i][$key]  ?? ''));
            $rVal = strtolower((string)($right[$j][$key] ?? ''));

            if (strcmp($lVal, $rVal) <= 0) {
                $result[] = $left[$i++];
            } else {
                $result[] = $right[$j++];
            }
        }

        // Append any remaining elements from either half
        while ($i < count($left))  $result[] = $left[$i++];
        while ($j < count($right)) $result[] = $right[$j++];

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MIN-HEAP INSERTION + SIFT-UP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * heapInsert() — Add a new item to a min-heap and restore heap order.
     *
     * A min-heap is a binary tree stored as a flat array where the parent
     * of node i is at index floor((i-1)/2). The heap property requires
     * that every parent is ≤ its children (for a min-heap).
     *
     * After appending the new item at the end, siftUp() bubbles it toward
     * the root until the heap property is restored.
     *
     * Usage: add a low-stock product to the priority queue so the most
     * critical (lowest quantity) product always sits at index 0.
     *
     * @param array  $heap    The current heap array (passed by reference).
     * @param array  $item    The new associative array row to insert.
     * @param string $key     The numeric key to compare (e.g. 'product_quantity').
     */
    public static function heapInsert(array &$heap, array $item, string $key): void
    {
        // Append the new item at the end of the heap array
        $heap[] = $item;

        // Restore heap order by sifting the new item up
        self::siftUp($heap, count($heap) - 1, $key);
    }

    /**
     * siftUp() — Move the element at $index up until the heap property holds.
     *
     * Compares the element with its parent. If the element is smaller than
     * its parent (violating the min-heap property), swap them and continue
     * from the parent's position. Stop when the element is ≥ its parent
     * or the root is reached.
     *
     * @param array  $heap   The heap array (passed by reference).
     * @param int    $index  The starting index to sift up from.
     * @param string $key    The key holding the numeric priority value.
     */
    public static function siftUp(array &$heap, int $index, string $key): void
    {
        while ($index > 0) {
            // Parent index in a 0-based binary heap
            $parent = intdiv($index - 1, 2);

            $childVal  = (float)($heap[$index][$key] ?? 0);
            $parentVal = (float)($heap[$parent][$key] ?? 0);

            // Min-heap property satisfied — stop
            if ($childVal >= $parentVal) {
                break;
            }

            // Swap child and parent
            [$heap[$index], $heap[$parent]] = [$heap[$parent], $heap[$index]];

            // Continue from the parent position
            $index = $parent;
        }
    }

    /**
     * buildMinHeap() — Convert an arbitrary array into a valid min-heap.
     *
     * Inserts each item one by one using heapInsert so every insertion
     * uses the siftUp path. Alternative to heapify-in-place, chosen here
     * because it directly demonstrates the heap insertion algorithm.
     *
     * @param array  $data  Array of rows to heapify.
     * @param string $key   The key to compare.
     * @return array        A valid min-heap array.
     */
    public static function buildMinHeap(array $data, string $key): array
    {
        $heap = [];
        foreach ($data as $item) {
            self::heapInsert($heap, $item, $key);
        }
        return $heap;
    }
}
