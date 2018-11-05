<?php

namespace Epubli\Epub\Toc;

/**
 * EPUB TOC structure
 *
 * @author Simon Schrape <simon@epubli.com>
 */
class Toc
{
    /** @var string */
    private $docTitle;
    /** @var string */
    private $docAuthor;
    /** @var NavPointList */
    private $navMap;

    public function __construct($title, $author)
    {
        $this->docTitle = $title;
        $this->docAuthor = $author;
        $this->navMap = new NavPointList();
    }

    /**
     * @return string
     */
    public function getDocTitle()
    {
        return $this->docTitle;
    }

    /**
     * @return string
     */
    public function getDocAuthor()
    {
        return $this->docAuthor;
    }

    /**
     * @return NavPointList
     */
    public function getNavMap()
    {
        return $this->navMap;
    }

    /**
     * @param $file
     * @return array|NavPoint[]
     */
    public function findNavPointsForFile($file)
    {
        return $this->getNavMap()->findNavPointsForFile($file);
    }
}
