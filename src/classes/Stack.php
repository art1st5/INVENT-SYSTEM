<?php
/**
 * src/classes/Stack.php
 *
 * DATA STRUCTURE — Stack (LIFO: Last In, First Out).
 *
 * A stack allows items to be pushed onto the top and popped off the top.
 * This is used in the transaction system to maintain an undo history of
 * confirmed sales. The most recent sale is always at the top and is the
 * first one that can be reversed.
 *
 * Operations:
 *   push($item)  — add an item to the top              O(1)
 *   pop()        — remove and return the top item       O(1)
 *   peek()       — return the top item without removing O(1)
 *   isEmpty()    — true if the stack has no items       O(1)
 *   size()       — number of items currently in stack   O(1)
 */
class Stack
{
    /** @var array Internal storage array; top of stack is the last element. */
    private array $items = [];

    /**
     * push() — Place a new item on top of the stack.
     *
     * @param mixed $item Any value to store.
     */
    public function push(mixed $item): void
    {
        $this->items[] = $item;
    }

    /**
     * pop() — Remove and return the top item.
     *
     * @return mixed The item that was on top.
     * @throws UnderflowException if the stack is empty.
     */
    public function pop(): mixed
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Cannot pop from an empty stack.');
        }
        return array_pop($this->items);
    }

    /**
     * peek() — Return the top item without removing it.
     *
     * @return mixed The top item.
     * @throws UnderflowException if the stack is empty.
     */
    public function peek(): mixed
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Cannot peek into an empty stack.');
        }
        return end($this->items);
    }

    /**
     * isEmpty() — Check whether the stack contains any items.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * size() — Return the number of items currently on the stack.
     */
    public function size(): int
    {
        return count($this->items);
    }

    /**
     * toArray() — Return all items as an array (bottom → top order).
     * Useful for persisting the stack to a session.
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * fromArray() — Rebuild a Stack from a plain array (e.g. from session).
     *
     * @param array $items Previously stored items, bottom → top.
     */
    public static function fromArray(array $items): self
    {
        $stack = new self();
        $stack->items = array_values($items);
        return $stack;
    }
}
