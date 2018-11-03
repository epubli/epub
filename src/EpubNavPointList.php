<?php

namespace Epubli\Epub;

use Iterator;

/**
 * A list of EPUB TOC navigation points.
 *
 * @author Simon Schrape <simon@epubli.com>
 */
class EpubNavPointList implements Iterator
{
    /** @var array|EpubNavPoint[] */
    private $navPoints = [];

    public function __construct()
    {
    }

    public function addNavPoint(EpubNavPoint $navPoint)
    {
        $this->navPoints[] = $navPoint;
    }

    /**
     * @param string $file
     * @return array|EpubNavPoint[]
     */
    public function findNavPointsForFile($file)
    {
        $matches = [];
        foreach ($this->navPoints as $navPoint) {
            if ($navPoint->getContentSourceFile() == $file) {
                $matches[] = $navPoint;
            }
            $childMatches = $navPoint->getChildren()->findNavPointsForFile($file);
            if (count($childMatches)) {
                $matches = array_merge($matches, $childMatches);
            }
        }
        return $matches;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return EpubNavPoint
     */
    public function current()
    {
        return current($this->navPoints);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        next($this->navPoints);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return key($this->navPoints);
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
        return (bool)current($this->navPoints);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        reset($this->navPoints);
    }

    /**
     * @return EpubNavPoint
     */
    public function first()
    {
        return reset($this->navPoints);
    }

    /**
     * @return EpubNavPoint
     */
    public function last()
    {
        return end($this->navPoints);
    }

    public function count()
    {
        return count($this->navPoints);
    }
}
