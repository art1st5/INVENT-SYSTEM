<?php
/**
 * src/classes/SearchAlgorithms.php
 *
 * Implements two search algorithms used across the inventory system:
 *
 *   LinearSearch  — scans every element until a match is found.
 *                   Used to look up a product by ID in an unsorted array.
 *                   Time complexity: O(n) worst case.
 *
 *   BinarySearch  — repeatedly halves a SORTED array to find a match.
 *                   Used to find a product by name in the alphabetically
 *                   sorted product list.
 *                   Time complexity: O(log n) worst case.
 *                   Prerequisite: the array must already be sorted by the
 *                   same key being searched.
 */
class SearchAlgorithms
{
    /**
     * linearSearch() — Find the first element whose $key matches $value.
     *
     * Walks through every row in $data from index 0 to n-1.
     * Returns the matching row as soon as one is found, or null if none match.
     *
     * Usage in this system:
     *   Finding a product by its product_id in the flat $active array.
     *
     * @param array  $data  Array of associative arrays (database rows).
     * @param string $key   The array key to compare against (e.g. 'product_id').
     * @param mixed  $value The value to search for.
     * @return array|null   The first matching row, or null if not found.
     */
    public static function linearSearch(array $data, string $key, mixed $value): ?array
    {
        foreach ($data as $row) {
            // Compare as strings to handle both int and string IDs safely
            if ((string)($row[$key] ?? '') === (string)$value) {
                return $row;
            }
        }
        return null;
    }

    /**
     * binarySearch() — Find an element by $key in a SORTED array.
     *
     * Uses the divide-and-conquer approach:
     *   1. Look at the middle element.
     *   2. If it matches, return it.
     *   3. If the target is less, search the left half.
     *   4. If the target is greater, search the right half.
     *   5. Repeat until found or the search space is empty.
     *
     * Usage in this system:
     *   Finding a product by name in the alphabetically sorted product list
     *   (after the list has been sorted with SortAlgorithms::mergeSort).
     *
     * @param array  $data        Sorted array of associative arrays.
     * @param string $key         The key to compare (e.g. 'product_name').
     * @param string $value       The exact value to find (case-insensitive).
     * @return array|null         The matching row, or null if not found.
     */
    public static function binarySearch(array $data, string $key, string $value): ?array
    {
        $low   = 0;
        $high  = count($data) - 1;
        $value = strtolower($value);

        while ($low <= $high) {
            // Integer midpoint — avoids overflow compared to ($low + $high) / 2
            $mid     = intdiv($low + $high, 2);
            $midVal  = strtolower((string)($data[$mid][$key] ?? ''));

            $cmp = strcmp($midVal, $value);

            if ($cmp === 0) {
                return $data[$mid];          // Exact match found
            } elseif ($cmp < 0) {
                $low = $mid + 1;             // Target is in the right half
            } else {
                $high = $mid - 1;            // Target is in the left half
            }
        }

        return null;                         // Not found
    }
}
