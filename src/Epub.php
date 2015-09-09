<?php

namespace Epubli\Epub;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMText;
use DOMXPath;
use Epubli\Common\Tools\HTMLTools;
use Epubli\Exception\Exception;
use ZipArchive;

/**
 * PHP EPUB Meta library
 *
 * Source: https://github.com/splitbrain/php-epub-meta
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015
 */
class Epub
{
    /** Name for cover images inserted by this lib. */
    const COVER_NAME = 'php-epub-meta-cover';

    /** @var string The file path of the epub file */
    private $filename;
    /** @var ZipArchive The the epub file loaded as zip archive */
    private $zip;
    /** @var string The filename the root (.opf) file */
    private $opfFilename;
    /** @var string The (archive local) directory containing the root (.opf) file */
    private $opfDir;
    /** @var DOMDocument The DOM of the root (.opf) file */
    private $opfDom;
    /** @var EpubDOMXPath The XPath object for the root (.opf) file */
    private $opfXPath;
    /** @var DOMDocument The DOM of the TOC (.ncx) file */
    private $tocDom;
    /** @var string The file path to the cover image if set */
    private $newCoverImage = '';
    /** @var EpubSpine The spine structure of this epub */
    private $spine;
    /** @var EpubToc The TOC structure of this epub */
    private $toc;

    /**
     * Constructor
     *
     * @param string $file path to epub file to work on
     * @throws Exception if metadata could not be loaded
     */
    public function __construct($file)
    {
        // open file
        $this->filename = $file;
        $this->zip = new ZipArchive();
        if (($result = @$this->zip->open($this->filename)) !== true) {
            $msg = 'Failed to read epub file. ';
            switch ($result) {
                case ZipArchive::ER_INCONS:
                    $msg .= 'Zip archive inconsistent.';
                    break;
                case ZipArchive::ER_NOZIP:
                    $msg .= 'Not a zip archive.';
                    break;
                case ZipArchive::ER_OPEN:
                    $msg .= 'Can’t open file.';
                    break;
                case ZipArchive::ER_READ:
                    $msg .= 'Read error.';
                    break;
            }
            throw new Exception($msg);
        }

        // read container data
        $xml = $this->loadZipXML('META-INF/container.xml', false);
        $xpath = new EpubDOMXPath($xml);
        $nodes = $xpath->query('//n:rootfiles/n:rootfile[@media-type="application/oebps-package+xml"]');
        /** @var EpubDOMElement $node */
        $node = $nodes->item(0);
        $rootFile = $node->attr('full-path');
        $this->opfFilename = basename($rootFile);
        if ($rootFile != $this->opfFilename) {
            $this->opfDir = dirname($rootFile).DIRECTORY_SEPARATOR;
        }

        // load metadata
        $this->opfDom = $this->loadZipXML($this->opfFilename);
        $this->opfXPath = new EpubDOMXPath($this->opfDom);
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /**
     * file name getter
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Writes back all meta data changes
     */
    public function save()
    {
        $this->zip->addFromString($this->opfDir.$this->opfFilename, $this->opfDom->saveXML());
        // add the cover image
        if ($this->newCoverImage) {
            $path = $this->opfDir.self::COVER_NAME.'.img'; // image path is relative to meta file

            $this->zip->addFile($this->newCoverImage, $path);
            $this->newCoverImage = '';
        }
        // close and reopen zip archive
        $this->zip->close();
        $this->zip->open($this->filename);
    }

    /**
     * Set the book author(s)
     *
     * Authors should be given with a "file-as" and a real name. The file as
     * is used for sorting in e-readers.
     *
     * Example:
     *
     * array(
     *      'Pratchett, Terry'   => 'Terry Pratchett',
     *      'Simpson, Jacqeline' => 'Jacqueline Simpson',
     * )
     *
     * @param array $authors
     */
    public function setAuthors($authors)
    {
        // Author where given as a comma separated list
        if (is_string($authors)) {
            if ($authors == '') {
                $authors = array();
            } else {
                $authors = explode(',', $authors);
                $authors = array_map('trim', $authors);
            }
        }

        // delete existing nodes
        $nodes = $this->opfXPath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        foreach ($nodes as $node) {
            /** @var EpubDOMElement $node */
            $node->delete();
        }

        // add new nodes
        /** @var EpubDOMElement $parent */
        $parent = $this->opfXPath->query('//opf:metadata')->item(0);
        foreach ($authors as $as => $name) {
            if (is_int($as)) {
                $as = $name;
            } //numeric array given
            $node = $parent->newChild('dc:creator', $name);
            $node->attr('opf:role', 'aut');
            $node->attr('opf:file-as', $as);
        }

        $this->reparse();
    }

    /**
     * Get the book author(s)
     *
     * @return array
     */
    public function getAuthors()
    {
        $rolefix = false;
        $authors = array();
        $nodes = $this->opfXPath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        if ($nodes->length == 0) {
            // no nodes where found, let's try again without role
            $nodes = $this->opfXPath->query('//opf:metadata/dc:creator');
            $rolefix = true;
        }
        foreach ($nodes as $node) {
            /** @var EpubDOMElement $node */
            $name = $node->nodeValueUnescaped;
            $as = $node->attr('opf:file-as');
            if (!$as) {
                $as = $name;
                $node->attr('opf:file-as', $as);
            }
            if ($rolefix) {
                $node->attr('opf:role', 'aut');
            }
            $authors[$as] = $name;
        }

        return $authors;
    }

    /**
     * Set the book title
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->setMeta('dc:title', $title);
    }

    /**
     * Get the book title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getMeta('dc:title');
    }

    /**
     * Set the book's language
     *
     * @param string $lang
     */
    public function setLanguage($lang)
    {
        $this->setMeta('dc:language', $lang);
    }

    /**
     * Get the book's language
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->getMeta('dc:language');
    }

    /**
     * Set the book's publisher info
     *
     * @param string $publisher
     */
    public function setPublisher($publisher)
    {
        $this->setMeta('dc:publisher', $publisher);
    }

    /**
     * Get the book's publisher info
     *
     * @return string
     */
    public function getPublisher()
    {
        return $this->getMeta('dc:publisher');
    }

    /**
     * Set the book's copyright info
     *
     * @param string $rights
     */
    public function setCopyright($rights)
    {
        $this->setMeta('dc:rights', $rights);
    }

    /**
     * Get the book's copyright info
     *
     * @return string
     */
    public function getCopyright()
    {
        return $this->getMeta('dc:rights');
    }

    /**
     * Set the book's description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->setMeta('dc:description', $description);
    }

    /**
     * Get the book's description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getMeta('dc:description');
    }

    /**
     * Set an identifier in the opf file’s meta section.
     *
     * @param string|array $idScheme The identifier’s scheme. If an array is given
     * all matching identifiers are replaced by one with the first value as scheme.
     * @param string $value
     * @param bool $caseSensitive
     */
    public function setIdentifier($idScheme, $value, $caseSensitive = false)
    {
        $this->setMeta('dc:identifier', $value, 'opf:scheme', $idScheme, $caseSensitive);
    }

    /**
     * Set an identifier from the opf file’s meta section.
     *
     * @param string|array $idScheme The identifier’s scheme. If an array is given
     * the scheme can be any of its values.
     * @param bool $caseSensitive
     * @return string The value of the first matching element.
     */
    public function getIdentifier($idScheme, $caseSensitive = false)
    {
        return $this->getMeta('dc:identifier', 'opf:scheme', $idScheme, $caseSensitive);
    }

    /**
     * Set the book's unique identifier
     *
     * @param string $value
     */
    public function setUniqueIdentifier($value)
    {
        $idRef = $this->opfDom->documentElement->getAttribute('unique-identifier');
        $this->setMeta('dc:identifier', $value, 'id', $idRef);
    }

    /**
     * Get the book's unique identifier
     *
     * @param bool $normalize
     * @return string
     */
    public function getUniqueIdentifier($normalize = false)
    {
        $idRef = $this->opfDom->documentElement->getAttribute('unique-identifier');
        $idVal = $this->getMeta('dc:identifier', 'id', $idRef);
        if ($normalize) {
            $idVal = strtolower($idVal);
            $idVal = str_replace('urn:uuid:' ,'' ,$idVal);
        }

        return $idVal;
    }

    /**
     * Set the book's UUID
     *
     * @param string $uuid
     */
    public function setUUID($uuid)
    {
        $this->setIdentifier(['UUID', 'uuid', 'URN', 'urn'], $uuid);
    }

    /**
     * Get the book's UUID
     *
     * @return string
     */
    public function getUUID()
    {
        return $this->getIdentifier(['uuid', 'urn']);
    }

    /**
     * Set the book's URI
     *
     * @param string $uri
     */
    public function setURI($uri)
    {
        $this->setIdentifier('uri', $uri);
    }

    /**
     * Get the book's URI
     *
     * @return string
     */
    public function getURI()
    {
        return $this->getIdentifier('uri');
    }

    /**
     * Set the book's ISBN
     *
     * @param string $isbn
     */
    public function setISBN($isbn)
    {
        $this->setIdentifier('isbn', $isbn);
    }

    /**
     * Get the book's ISBN
     *
     * @return string
     */
    public function getISBN()
    {
        return $this->getIdentifier('isbn');
    }

    /**
     * Set the book's subjects (aka. tags)
     *
     * Subject should be given as array, but a comma separated string will also
     * be accepted.
     *
     * @param array|string $subjects
     */
    public function setSubjects($subjects)
    {
        if (is_string($subjects)) {
            if ($subjects === '') {
                $subjects = [];
            } else {
                $subjects = explode(',', $subjects);
                $subjects = array_map('trim', $subjects);
            }
        }

        // delete previous
        $nodes = $this->opfXPath->query('//opf:metadata/dc:subject');
        foreach ($nodes as $node) {
            /** @var EpubDOMElement $node */
            $node->delete();
        }
        // add new ones
        $parent = $this->opfXPath->query('//opf:metadata')->item(0);
        foreach ($subjects as $subj) {
            $node = new EpubDOMElement('dc:subject', $subj);
            $parent->appendChild($node);
        }

        $this->reparse();
    }

    /**
     * Get the book's subjects (aka. tags)
     *
     * @return array
     */
    public function getSubjects()
    {
        $subjects = [];
        $nodes = $this->opfXPath->query('//opf:metadata/dc:subject');
        foreach ($nodes as $node) {
            $subjects[] = $node->nodeValueUnescaped;
        }

        return $subjects;
    }

    /**
     * Set the cover image
     *
     * When adding a new image getCover() returns no or old data because the
     * image contents are not in the epub file, yet. The image will be added when
     * the save() method is called.
     *
     * @param string $path local filesystem path to a new cover image
     * @param string $mime mime type of the given file
     */
    public function setCover($path, $mime)
    {
        // remove current pointer
        $nodes = $this->opfXPath->query('//opf:metadata/opf:meta[@name="cover"]');
        foreach ($nodes as $node) {
            /** @var EpubDOMElement $node */
            $node->delete();
        }
        // remove previous manifest entries if they where made by us
        $nodes = $this->opfXPath->query('//opf:manifest/opf:item[@id="'.self::COVER_NAME.'"]');
        foreach ($nodes as $node) {
            /** @var EpubDOMElement $node */
            $node->delete();
        }

        if ($path) {
            // add pointer
            /** @var EpubDOMElement $parent */
            $parent = $this->opfXPath->query('//opf:metadata')->item(0);
            $node = $parent->newChild('opf:meta');
            $node->attr('opf:name', 'cover');
            $node->attr('opf:content', self::COVER_NAME);

            // add manifest
            $parent = $this->opfXPath->query('//opf:manifest')->item(0);
            $node = $parent->newChild('opf:item');
            $node->attr('id', self::COVER_NAME);
            $node->attr('opf:href', self::COVER_NAME.'.img');
            $node->attr('opf:media-type', $mime);

            // remember path for save action
            $this->newCoverImage = $path;
        }

        $this->reparse();
    }

    /**
     * Get the cover image
     *
     * Returns an associative array with the following keys:
     *
     *   mime  - filetype (usually image/jpeg)
     *   data  - the binary image data
     *   found - the internal path, or false if no image is set in epub
     *
     * When no image is set in the epub file, the binary data for a transparent
     * GIF pixel is returned.
     *
     * @return array
     */
    public function getCover()
    {
        $nodes = $this->opfXPath->query('//opf:metadata/opf:meta[@name="cover"]');
        if (!$nodes->length) {
            return $this->no_cover();
        }
        /** @var EpubDOMElement $node */
        $node = $nodes->item(0);
        $coverid = (String)$node->attr('opf:content');
        if (!$coverid) {
            return $this->no_cover();
        }

        $nodes = $this->opfXPath->query('//opf:manifest/opf:item[@id="'.$coverid.'"]');
        if (!$nodes->length) {
            return $this->no_cover();
        }
        $node = $nodes->item(0);
        $mime = $node->attr('opf:media-type');
        $path = $node->attr('opf:href');
        $path = $this->opfDir.$path; // image path is relative to meta file

        $data = $this->zip->getFromName($path);

        return array(
            'mime' => $mime,
            'data' => $data,
            'found' => $path
        );
    }

    /**
     * Delete the cover image
     */
    public function deleteCover()
    {
        $this->setCover('', '');
    }

    /**
     * Get the spine structure of this Epub.
     *
     * @return EpubSpine
     * @throws Exception
     */
    public function getSpine()
    {
        if (!$this->spine) {
            /** @var DOMElement $spineNode */
            $spineNode = $this->opfXPath->query('//opf:spine')->item(0);
            if (is_null($spineNode)) {
                throw new Exception('No spine element found in epub!');
            }
            $tocFile = $this->getTOCFile($spineNode);

            $this->spine = new EpubSpine();
            $this->spine->setTOCSource($tocFile);
            $itemRefNodes = $spineNode->getElementsByTagName('itemref');
            foreach ($itemRefNodes as $itemRef) {
                /** @var DOMElement $itemRef */
                $id = $itemRef->getAttribute('idref');
                /** @var DOMElement $item */
                $item = $this->opfXPath->query("//opf:manifest/opf:item[@id=\"$id\"]")->item(0);
                if (is_null($item)) {
                    throw new Exception('Item referenced in spine missing in manifest!');
                }
                $href = urldecode($item->getAttribute('href'));
                $mediaType = $item->getAttribute('media-type');
                $this->spine->addItem(new EpubSpineItem($id, $href, $mediaType));
            }
        }

        return $this->spine;
    }

    /**
     * Get the table of contents structure of this Epub.
     *
     * @return EpubToc
     * @throws Exception
     */
    public function getTOC()
    {
        if (!$this->toc) {
            if (!$this->tocDom) {
                $tocFile = $this->getSpine()->getTOCSource();
                $this->tocDom = $this->loadZipXML($tocFile);
            }
            $xp = new DOMXPath($this->tocDom);
            $xp->registerNamespace('ncx', 'http://www.daisy.org/z3986/2005/ncx/');
            $titleNode = $xp->query('//ncx:docTitle/ncx:text')->item(0);
            $title = $titleNode ? $titleNode->nodeValue : '';
            $authorNode = $xp->query('//ncx:docAuthor/ncx:text')->item(0);
            $author = $authorNode ? $authorNode->nodeValue : '';
            $this->toc = new EpubToc($title, $author);

            $navPointNodes = $xp->query('//ncx:navMap/ncx:navPoint');

            $this->loadNavPoints($navPointNodes, $this->toc->getNavMap(), $xp);
        }

        return $this->toc;
    }

    /**
     * Extract (a part of) the plain text contents from an XML file contained in the epub.
     *
     * @param string $file The XML file to load (path in zip archive)
     * @param string|null $fragmentBegin ID of the element where to start reading the contents.
     * @param string|null $fragmentEnd ID of the element where to stop reading the contents.
     * @return string The plain text contents of that fragment.
     * @throws Exception
     */
    public function getContents($file, $fragmentBegin = null, $fragmentEnd = null)
    {
        $dom = $this->loadZipXML($file, true, true);
        // get the starting point
        $xp = new DOMXPath($dom);
        if ($fragmentBegin) {
            $node = $xp->query("//*[@id='$fragmentBegin']")->item(0);
            if (!$node){
                throw new Exception("Begin of fragment not found: No element with ID $fragmentBegin in $file!");
            }
        } else {
            $node = $dom->getElementsByTagName('body')->item(0) ?: $dom->documentElement;
        }

        $contents = '';
        // traverse DOM structure till end point is reached, accumulating the contents
        while ($node && (!$fragmentEnd || !$node->hasAttributes() || $node->getAttribute('id') != $fragmentEnd)) {
            if ($node instanceof DOMText) {
                // when encountering a text node append its value to the contents
                $contents .= $node->nodeValue;
            }
            if ($node->hasChildNodes()) {
                // step into
                $node = $node->firstChild;
            } elseif ($node->nextSibling) {
                $node = $node->nextSibling;
            } else {
                // step out
                do {
                    $node = $node->parentNode;
                }
                while ($node && !$node->nextSibling);
                if ($node) {
                    // node has next sibling, select that one
                    if (HTMLTools::isBlockLevelElement($node->localName)) {
                        // add whitespace between contents of adjacent blocks (see #9670)
                        $contents .= PHP_EOL;
                    }
                    $node = $node->nextSibling;
                }
                else {
                    // reached end of DOM
                    if ($fragmentEnd) {
                        throw new Exception("End of fragment not found: No element with ID $fragmentEnd in $file!");
                    }
                }
            }
        }

        return $contents;
    }

    /**
     * A simple setter for simple meta attributes
     *
     * It should only be used for attributes that are expected to be unique
     *
     * @param string $item XML node to set
     * @param string $value New node value
     * @param bool|string $attribute Attribute name
     * @param bool|string $attributeValue Attribute value
     * @param bool $caseSensitive
     * @return string
     */
    private function setMeta($item, $value, $attribute = false, $attributeValue = false, $caseSensitive = true)
    {
        $xpath = $this->buildMetaXPath($item, $attribute, $attributeValue, $caseSensitive);

        // set value
        $nodes = $this->opfXPath->query($xpath);
        if ($nodes->length == 1) {
            /** @var EpubDOMElement $node */
            $node = $nodes->item(0);
            if ($value === '') {
                // the user wants to empty this value -> delete the node
                $node->delete();
            } else {
                // replace value
                $node->nodeValueUnescaped = $value;
            }
        } else {
            // if there are multiple matching nodes for some reason delete
            // them. we'll replace them all with our own single one
            foreach ($nodes as $node) {
                /** @var EpubDOMElement $node */
                $node->delete();
            }
            // re-add them
            if ($value) {
                $parent = $this->opfXPath->query('//opf:metadata')->item(0);
                $node = new EpubDOMElement($item, $value);
                $node = $parent->appendChild($node);
                if ($attribute) {
                    if (is_array($attributeValue)) {
                        // use first given value for new attribute
                        $attributeValue = reset($attributeValue);
                    }
                    $node->attr($attribute, $attributeValue);
                }
            }
        }

        $this->reparse();
    }

    /**
     * A simple getter for simple meta attributes
     *
     * It should only be used for attributes that are expected to be unique
     *
     * @param string $item XML node to get
     * @param bool|string $att Attribute name
     * @param bool|string $aval Attribute value
     * @param bool $caseSensitive
     * @return string
     */
    private function getMeta($item, $att = false, $aval = false, $caseSensitive = true)
    {
        $xpath = $this->buildMetaXPath($item, $att, $aval, $caseSensitive);

        // get value
        $nodes = $this->opfXPath->query($xpath);
        if ($nodes->length) {
            /** @var EpubDOMElement $node */
            $node = $nodes->item(0);

            return $node->nodeValueUnescaped;
        } else {
            return '';
        }
    }

    /**
     * Build an XPath expression to select certain nodes in the metadata section.
     *
     * @param string $element The node name of the elements to select.
     * @param string $attribute If set, the attribute required in the element.
     * @param string|array $value If set, the value of the above named attribute. If an array is given
     * all of its values will be allowed in the selector.
     * @param bool $caseSensitive If false, attribute values are matched case insensitively.
     * (This is not completely true, as only full upper or lower case strings are matched, not mixed case.
     * A lower-case function is missing in XPath 1.0.)
     * @return string
     */
    private function buildMetaXPath($element, $attribute, $value, $caseSensitive = true)
    {
        $xpath = '//opf:metadata/'.$element;
        if ($attribute) {
            $xpath .= "[@$attribute";
            if ($value) {
                $values = is_array($value) ? $value : [$value];
                if (!$caseSensitive) {
                    $temp = [];
                    foreach ($values as $item) {
                        $temp[] = strtolower($item);
                        $temp[] = strtoupper($item);
                    }
                    $values = $temp;
                }

                $xpath .= '="';
                $xpath .= implode("\" or @$attribute=\"", $values);
                $xpath .= '"';
            }
            $xpath .= ']';
        }

        return $xpath;
    }

    /**
     * Get the path of the TOC file inside the Epub.
     *
     * @param DOMElement $spineNode
     * @return string The path to the TOC file inside the Epub.
     * @throws Exception
     */
    private function getTOCFile(DOMElement $spineNode)
    {
        $tocId = $spineNode->getAttribute('toc');
        if (empty($tocId)) {
            throw new Exception('No toc ID given in spine!');
        }
        /** @var DOMElement $tocItem */
        $tocItem = $this->opfXPath->query("//opf:manifest/opf:item[@id=\"$tocId\"]")->item(0);
        if (is_null($tocItem)) {
            throw new Exception('TOC item referenced by spine missing in manifest!');
        }
        $tocFile = $tocItem->getAttribute('href');
        if (empty($tocFile)) {
            throw new Exception('TOC item does not contain hyper reference to TOC file!');
        }

        return $tocFile;
    }

    /**
     * Load navigation points from TOC XML DOM into TOC object structure.
     *
     * @param DOMNodeList $navPointNodes List of nodes to load from.
     * @param EpubNavPointList $navPointList List structure to load into.
     * @param DOMXPath $xp The XPath of the TOC document.
     */
    private static function loadNavPoints(DOMNodeList $navPointNodes, EpubNavPointList $navPointList, DOMXPath $xp)
    {
        foreach ($navPointNodes as $navPointNode) {
            /** @var DOMElement $navPointNode */
            $id = $navPointNode->getAttribute('id');
            $class = $navPointNode->getAttribute('class');
            $playOrder = $navPointNode->getAttribute('playOrder');
            $labelTextNode = $xp->query('ncx:navLabel/ncx:text', $navPointNode)->item(0);
            $label = $labelTextNode ? $labelTextNode->nodeValue : '';
            /** @var DOMElement $contentNode */
            $contentNode = $xp->query('ncx:content', $navPointNode)->item(0);
            $contentSource = $contentNode ? $contentNode->getAttribute('src') : '';
            $navPoint = new EpubNavPoint($id, $class, $playOrder, $label, $contentSource);
            $navPointList->addNavPoint($navPoint);
            $childNavPointNodes = $xp->query('ncx:navPoint', $navPointNode);
            $childNavPoints = $navPoint->getChildren();

            self::loadNavPoints($childNavPointNodes, $childNavPoints, $xp);
        }
    }

    /**
     * Return a not found response for Cover()
     */
    private function no_cover()
    {
        return array(
            'data' => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7'),
            'mime' => 'image/gif',
            'found' => false
        );
    }

    /**
     * Reparse the DOM tree
     *
     * I had to rely on this because otherwise xpath failed to find the newly
     * added nodes
     */
    private function reparse()
    {
        $this->opfDom->loadXML($this->opfDom->saveXML());
        $this->opfXPath = new EpubDOMXPath($this->opfDom);
    }

    /**
     * Load an XML file from the Epub/ZIP archive.
     *
     * @param $path string The xml file to load from the zip archive.
     * @param bool $relativeToOPFDir If true, $path is considered relative to OPF directory, else to zip root
     * @param bool $isHTML If true, file contents is considered HTML.
     * @return DOMDocument
     * @throws Exception
     */
    private function loadZipXML($path, $relativeToOPFDir = true, $isHTML = false)
    {
        $data = $this->zip->getFromName(($relativeToOPFDir ? $this->opfDir : '').$path);
        if (!$data) {
            throw new Exception('Failed to access epub container data: '.$path);
        }
        $xml = new DOMDocument();
        if ($isHTML) {
            $data = HTMLTools::convertEntitiesNamedToNumeric($data);
        } else {
            $xml->registerNodeClass(DOMElement::class, EpubDOMElement::class);
        }
        $xml->loadXML($data);

        return $xml;
    }
}

/**
 * EPUB-specific subclass of DOMXPath
 *
 * Source: https://github.com/splitbrain/php-epub-meta
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015
 */
class EpubDOMXPath extends DOMXPath
{
    public function __construct(DOMDocument $doc)
    {
        parent::__construct($doc);

        if ($doc->documentElement instanceof EpubDOMElement) {
            foreach ($doc->documentElement->namespaces as $ns => $url) {
                $this->registerNamespace($ns, $url);
            }
        }
    }
}

/**
 * EPUB-specific subclass of DOMElement
 *
 * Source: https://github.com/splitbrain/php-epub-meta
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015
 *
 * @property string $nodeValueUnescaped
 */
class EpubDOMElement extends DOMElement
{
    public $namespaces = [
        'n' => 'urn:oasis:names:tc:opendocument:xmlns:container',
        'opf' => 'http://www.idpf.org/2007/opf',
        'dc' => 'http://purl.org/dc/elements/1.1/'
    ];

    public function __construct($name, $value = '', $namespaceURI = '')
    {
        list($ns, $name) = $this->splitns($name);
        $value = htmlspecialchars($value);
        if (!$namespaceURI && $ns) {
            $namespaceURI = $this->namespaces[$ns];
        }
        parent::__construct($name, $value, $namespaceURI);
    }

    public function __get($name)
    {
        switch ($name) {
            case 'nodeValueUnescaped':
                return htmlspecialchars_decode($this->nodeValue);
        }
        return null;
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'nodeValueUnescaped':
                $this->nodeValue = htmlspecialchars($value);
        }
    }

    /**
     * Create and append a new child
     *
     * Works with our epub namespaces and omits default namespaces
     * @param $name
     * @param string $value
     * @return EpubDOMElement
     */
    public function newChild($name, $value = '')
    {
        list($ns, $local) = $this->splitns($name);
        $nsuri = '';
        if ($ns) {
            $nsuri = $this->namespaces[$ns];
            if ($this->isDefaultNamespace($nsuri)) {
                $name = $local;
                $nsuri = '';
            }
        }

        // this doesn't call the constructor: $node = $this->ownerDocument->createElement($name,$value);
        $node = new EpubDOMElement($name, $value, $nsuri);

        return $this->appendChild($node);
    }

    /**
     * Split given name in namespace prefix and local part
     *
     * @param  string $name
     * @return array  (namespace, name)
     */
    public function splitns($name)
    {
        $list = explode(':', $name, 2);
        if (count($list) < 2) {
            array_unshift($list, '');
        }

        return $list;
    }

    /**
     * Simple EPUB namespace aware attribute accessor
     * @param $attr
     * @param null $value
     * @return string
     */
    public function attr($attr, $value = null)
    {
        list($ns, $attr) = $this->splitns($attr);

        $nsuri = '';
        if ($ns) {
            $nsuri = $this->namespaces[$ns];
            if (!$this->namespaceURI) {
                if ($this->isDefaultNamespace($nsuri)) {
                    $nsuri = '';
                }
            } elseif ($this->namespaceURI == $nsuri) {
                $nsuri = '';
            }
        }

        if (!is_null($value)) {
            if ($value === false) {
                // delete if false was given
                if ($nsuri) {
                    $this->removeAttributeNS($nsuri, $attr);
                } else {
                    $this->removeAttribute($attr);
                }
            } else {
                // modify if value was given
                if ($nsuri) {
                    $this->setAttributeNS($nsuri, $attr, $value);
                } else {
                    $this->setAttribute($attr, $value);
                }
            }
        }

        // return value if none was given
        if ($nsuri) {
            return $this->getAttributeNS($nsuri, $attr);
        } else {
            return $this->getAttribute($attr);
        }
    }

    /**
     * Remove this node from the DOM
     */
    public function delete()
    {
        $this->parentNode->removeChild($this);
    }
}
