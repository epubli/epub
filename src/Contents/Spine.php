<?php

namespace Epubli\Epub\Contents;

use ArrayAccess;
use Countable;
use Epubli\Epub\Data\Item;
use Epubli\Exception\NotSupportedException;
use Iterator;

/**
 * EPUB spine structure
 *
 * @author Simon Schrape <simon@epubli.com>
 */
class Spine implements Iterator, Countable, ArrayAccess
{
    /** @var Item */
    private $tocItem;
    /** @var array|Item[] The ordered list of all Items in this Spine. */
    private $items = [];

    /**
     * Spine Constructor.
     *
     * @param Item $tocItem The TOC Item of this Spine.
     */
    public function __construct(Item $tocItem)
    {
        $this->tocItem = $tocItem;
    }

    /**
     * Get the TOC Item of this Spine.
     *
     * @return Item
     */
    public function getTocItem()
    {
        return $this->tocItem;
    }

    /**
     * Append an Item to this Spine.
     *
     * @param Item $item The Item to append to this Spine.
     */
    public function appendItem(Item $item)
    {
        $this->items[] = $item;
    }

    /**
     * Return the current Item while iterating this Spine.
     *
     * @link http://php.net/manual/en/iterator.current.php
     * @return Item
     */
    public function current(): Item
    {
        return current($this->items);
    }

    /**
     * Move forward to next Item while iterating this Spine.
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next(): void
    {
        next($this->items);
    }

    /**
     * Return the index of the current Item while iterating this Spine.
     *
     * @link http://php.net/manual/en/iterator.key.php
     * @return int on success, or null on failure.
     */
    public function key(): int
    {
        return key($this->items);
    }

    /**
     * Checks if current Iterator position is valid.
     *
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean true on success or false on failure.
     */
    public function valid(): bool
    {
        return (bool)current($this->items);
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind(): void
    {
        reset($this->items);
    }

    /**
     * Get the first Item of this Spine.
     *
     * @return Item
     */
    public function first()
    {
        return reset($this->items);
    }

    /**
     * Get the last Item of this Spine.
     *
     * @return Item
     */
    public function last()
    {
        return end($this->items);
    }

    /**
     * Count items of this Spine.
     *
     * @link https://php.net/manual/en/countable.count.php
     * @return int The number of Items contained in this Spine.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Whether a offset exists
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param int $offset An offset to check for.
     * @return boolean true on success or false on failure.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Offset to retrieve
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param int $offset The offset to retrieve.
     * @return Item
     */
    public function offsetGet($offset): Item
    {
        return $this->items[$offset];
    }

    /**
     * Offset to set
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     * @throws NotSupportedException
     */
    public function offsetSet($offset, $value): void
    {
        throw new NotSupportedException("Only reading array access is supported!");
    }

    /**
     * Offset to unset
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset.
     * @throws NotSupportedException
     */
    public function offsetUnset($offset): void
    {
        throw new NotSupportedException("Only reading array access is supported!");
    }
}
