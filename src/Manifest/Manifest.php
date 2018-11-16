<?php

namespace Epubli\Epub\Manifest;

use ArrayAccess;
use Epubli\Exception\NotSupportedException;
use Iterator;

/**
 * EPUB manifest structure
 *
 * @author Simon Schrape <simon@epubli.com>
 */
class Manifest implements Iterator, ArrayAccess
{
    /** @var array|Item[] */
    private $items = [];

    public function addItem(Item $item)
    {
        $this->items[] = $item;
    }

    public function add($id, $href, $handle, $mediaType = null)
    {
        $item = new Item($id, $href, $handle, $mediaType);
        $this->items[$id] = $item;

        return $item;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return Item
     */
    public function current()
    {
        return current($this->items);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        next($this->items);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return key($this->items);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return (bool)current($this->items);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        reset($this->items);
    }

    /**
     * @return Item
     */
    public function first()
    {
        return reset($this->items);
    }

    /**
     * @return Item
     */
    public function last()
    {
        return end($this->items);
    }

    public function count()
    {
        return count($this->items);
    }

    /**
     * Whether a offset exists
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param int $offset An offset to check for.
     * @return boolean true on success or false on failure.
     */
    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }

    /**
     * Offset to retrieve
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param int $offset The offset to retrieve.
     * @return Item
     */
    public function offsetGet($offset)
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
    public function offsetSet($offset, $value)
    {
        throw new NotSupportedException("Only reading array access is supported!");
    }

    /**
     * Offset to unset
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset.
     * @throws NotSupportedException
     */
    public function offsetUnset($offset)
    {
        throw new NotSupportedException("Only reading array access is supported!");
    }
}
