<?php

namespace Epubli\Epub;

/**
 * EPUB TOC structure
 *
 * @author Simon Schrape <simon@epubli.com>
 */
class EpubToc
{
    /** @var string */
    private $docTitle;
    /** @var string */
    private $docAuthor;
    /** @var EpubNavPointList */
    private $navMap;

    public function __construct($title, $author)
    {
        $this->docTitle = $title;
        $this->docAuthor = $author;
        $this->navMap = new EpubNavPointList();
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
     * @return EpubNavPointList
     */
    public function getNavMap()
    {
        return $this->navMap;
    }

    /**
     * @param $file
     * @return array|EpubNavPoint[]
     */
    public function findNavPointsForFile($file)
    {
        return $this->getNavMap()->findNavPointsForFile($file);
    }
}
