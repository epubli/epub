<?php

namespace Epubli\Epub\Spine;

use Iterator;

/**
 * EPUB spine structure
 *
 * @author Simon Schrape <simon@epubli.com>
 */
class Spine implements Iterator
{
    /** @var string */
    private $tocSource;
    /** @var array|Item[] */
    private $items = [];

    /**
     * @return string
     */
    public function getTOCSource()
    {
        return $this->tocSource;
    }

    /**
     * @param string $tocSource
     */
    public function setTOCSource($tocSource)
    {
        $this->tocSource = $tocSource;
    }

    /**
     * @return array|Item[]
     */
    public function getItems()
    {
        return $this->items;
    }

    public function addItem(Item $item)
    {
        $this->items[] = $item;
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
}
