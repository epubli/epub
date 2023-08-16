<?php

namespace Epubli\Epub;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Epubli\Epub\Data\Item as DataItem;
use Epubli\Epub\Dom\Element as EpubDomElement;
use Epubli\Epub\Dom\XPath as EpubDomXPath;
use Epubli\Epub\Data\Manifest;
use Epubli\Epub\Contents\Spine;
use Epubli\Epub\Contents\NavPoint as TocNavPoint;
use Epubli\Epub\Contents\NavPointList as TocNavPointList;
use Epubli\Epub\Contents\Toc;
use Epubli\Exception\Exception;
use ZipArchive;

/**
 * Representation of an EPUB document.
 *
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015
 */
class Epub
{
    /** Identifier for cover image inserted by this lib. */
    public const COVER_ID = 'epubli-epub-cover';
    /** Identifier for title page inserted by this lib. */
    public const TITLE_PAGE_ID = 'epubli-epub-titlepage';

    /** @var string The file path of the EPUB file */
    private $filename;
    /** @var ZipArchive The the EPUB file loaded as zip archive */
    private $zip;
    /** @var array A map of ZIP items mapping filenames to file sizes */
    private $zipSizeMap;
    /** @var string The filename of the package (.opf) file */
    private $packageFile;
    /** @var string The (archive local) directory containing the package (.opf) file */
    private $packageDir;
    /** @var EpubDomXPath The XPath object for the root (.opf) file */
    private $packageXPath;
    /** @var Manifest|null The manifest (catalog of files) of this EPUB */
    private $manifest;
    /** @var Spine|null The spine structure of this EPUB */
    private $spine;
    /** @var Toc|null The TOC structure of this EPUB */
    private $toc;

    /**
     * Constructor
     *
     * @param string $file path to EPUB file to work on
     * @throws Exception if metadata could not be loaded
     */
    public function __construct($file)
    {
        if (!is_file($file)) {
            throw new Exception('Failed to read EPUB file. No such file.');
        }
        if (filesize($file) <= 0) {
            throw new Exception("Epub file is empty!");
        }
        // open file
        $this->filename = $file;
        $this->zip = new ZipArchive();
        if (($result = @$this->zip->open($this->filename)) !== true) {
            $msg = 'Failed to read EPUB file. ';
            switch ($result) {
                case ZipArchive::ER_SEEK:
                    $msg .= 'Seek error.';
                    break;
                case ZipArchive::ER_READ:
                    $msg .= 'Read error.';
                    break;
                case ZipArchive::ER_NOENT:
                    $msg .= 'No such file.';
                    break;
                case ZipArchive::ER_OPEN:
                    $msg .= 'Can’t open file.';
                    break;
                case ZipArchive::ER_MEMORY:
                    $msg .= 'Memory allocation failure.';
                    break;
                case ZipArchive::ER_INVAL:
                    $msg .= 'Invalid argument.';
                    break;
                case ZipArchive::ER_NOZIP:
                    $msg .= 'Not a zip archive.';
                    break;
                case ZipArchive::ER_INCONS:
                    $msg .= 'Zip archive inconsistent.';
                    break;
                default:
                    $msg .= "Unknown error: $result";
            }
            throw new Exception($msg, $result);
        }

        $this->zipSizeMap = $this->loadSizeMap($file);

        // read container data
        $containerXpath = $this->loadXPathFromItem('META-INF/container.xml');
        $nodes = $containerXpath->query('//ocf:rootfiles/ocf:rootfile[@media-type="application/oebps-package+xml"]');
        /** @var EpubDomElement $node */
        $node = $nodes->item(0);
        $rootFile = $node->getAttribute('full-path');
        $this->packageFile = basename($rootFile);
        $this->packageDir = substr($rootFile, 0, - strlen($this->packageFile));

        // load metadata
        $this->packageXPath = $this->loadXPathFromItem($this->packageDir . $this->packageFile);
    }

    public function __destruct()
    {
        try {
            $this->zip->close();
        } catch (\ValueError $er) {
            // ValueError: Invalid or uninitialized Zip object
        }
    }

    /**
     * Get the file path of the EPUB file.
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
        $this->zip->addFromString($this->packageDir.$this->packageFile, $this->packageXPath->document->saveXML());
        // close and reopen zip archive
        $result = $this->zip->close();
        $this->zip->open($this->filename);

        $this->sync();
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
     *      'Simpson, Jacqueline' => 'Jacqueline Simpson',
     * )
     *
     * @param array|string $authors
     */
    public function setAuthors($authors)
    {
        // Author where given as a comma separated list
        if (is_string($authors)) {
            if ($authors == '') {
                $authors = [];
            } else {
                $authors = explode(',', $authors);
                $authors = array_map('trim', $authors);
            }
        }

        // delete existing nodes
        $nodes = $this->packageXPath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }

        // add new nodes
        /** @var EpubDomElement $parent */
        $parent = $this->packageXPath->query('//opf:metadata')->item(0);
        foreach ($authors as $as => $name) {
            if (is_int($as)) {
                $as = $name;
            } //numeric array given
            $node = $parent->newChild('dc:creator', $name);
            $node->setAttrib('opf:role', 'aut');
            $node->setAttrib('opf:file-as', $as);
        }

        $this->sync();
    }

    /**
     * Get the book author(s)
     *
     * @return array
     */
    public function getAuthors()
    {
        $rolefix = false;
        $authors = [];
        $nodes = $this->packageXPath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        if ($nodes->length == 0) {
            // no nodes where found, let's try again without role
            $nodes = $this->packageXPath->query('//opf:metadata/dc:creator');
            $rolefix = true;
        }
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $name = $node->nodeValueUnescaped;
            $as = $node->getAttrib('opf:file-as');
            if (!$as) {
                $as = $name;
                $node->setAttrib('opf:file-as', $as);
            }
            if ($rolefix) {
                $node->setAttrib('opf:role', 'aut');
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
     * Set an identifier in the package file’s meta section.
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
     * Set an identifier from the package file’s meta section.
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
        $idRef = $this->packageXPath->document->documentElement->getAttribute('unique-identifier');
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
        $idRef = $this->packageXPath->document->documentElement->getAttribute('unique-identifier');
        $idVal = $this->getMeta('dc:identifier', 'id', $idRef);
        if ($normalize) {
            $idVal = strtolower($idVal);
            $idVal = str_replace('urn:uuid:', '', $idVal);
        }

        return $idVal;
    }

    /**
     * Set the book's UUID
     *
     * @param string $uuid
     */
    public function setUuid($uuid)
    {
        $this->setIdentifier(['UUID', 'uuid', 'URN', 'urn'], $uuid);
    }

    /**
     * Get the book's UUID
     *
     * @return string
     */
    public function getUuid()
    {
        return $this->getIdentifier(['uuid', 'urn']);
    }

    /**
     * Set the book's URI
     *
     * @param string $uri
     */
    public function setUri($uri)
    {
        $this->setIdentifier('uri', $uri);
    }

    /**
     * Get the book's URI
     *
     * @return string
     */
    public function getUri()
    {
        return $this->getIdentifier('uri');
    }

    /**
     * Set the book's ISBN
     *
     * @param string $isbn
     */
    public function setIsbn($isbn)
    {
        $this->setIdentifier('isbn', $isbn);
    }

    /**
     * Get the book's ISBN
     *
     * @return string
     */
    public function getIsbn()
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
        $nodes = $this->packageXPath->query('//opf:metadata/dc:subject');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }
        // add new ones
        $parent = $this->packageXPath->query('//opf:metadata')->item(0);
        foreach ($subjects as $subj) {
            $node = new EpubDomElement('dc:subject', $subj);
            $parent->appendChild($node);
        }

        $this->sync();
    }

    /**
     * Get the book's subjects (aka. tags)
     *
     * @return array
     */
    public function getSubjects()
    {
        $subjects = [];
        $nodes = $this->packageXPath->query('//opf:metadata/dc:subject');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $subjects[] = $node->nodeValueUnescaped;
        }

        return $subjects;
    }

    /**
     * Remove the cover image
     *
     * If the actual image file was added by this library it will be removed. Otherwise only the
     * reference to it is removed from the metadata, since the same image might be referenced
     * by other parts of the EPUB file.
     */
    public function clearCover()
    {
        if (!$this->hasCover()) {
            return;
        }

        // remove any cover image file added by us
        $this->zip->deleteName($this->packageDir . self::COVER_ID . '.img');

        // remove metadata cover pointer
        $nodes = $this->packageXPath->query('//opf:metadata/opf:meta[@name="cover"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }

        // remove previous manifest entries if they where made by us
        $nodes = $this->packageXPath->query('//opf:manifest/opf:item[@id="' . self::COVER_ID . '"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }

        $this->sync();
    }

    /**
     * Set the cover image
     *
     * @param string $path local filesystem path to a new cover image
     * @param string $mime mime type of the given file
     */
    public function setCover($path, $mime)
    {
        if (!$path) {
            throw new \InvalidArgumentException('Parameter $path must not be empty!');
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Cannot add $path as new cover image since that file is not readable!");
        }

        $this->clearCover();

        // add metadata cover pointer
        /** @var EpubDomElement $parent */
        $parent = $this->packageXPath->query('//opf:metadata')->item(0);
        $node = $parent->newChild('opf:meta');
        $node->setAttrib('opf:name', 'cover');
        $node->setAttrib('opf:content', self::COVER_ID);

        // add manifest item
        /** @var EpubDomElement $parent */
        $parent = $this->packageXPath->query('//opf:manifest')->item(0);
        $node = $parent->newChild('opf:item');
        $node->setAttrib('id', self::COVER_ID);
        $node->setAttrib('opf:href', self::COVER_ID . '.img');
        $node->setAttrib('opf:media-type', $mime);

        // add the cover image
        $this->zip->addFile($path, $this->packageDir . self::COVER_ID . '.img');

        $this->sync();
    }

    /**
     * Get the cover image
     *
     * @return string|null The binary image data or null if no image exists.
     */
    public function getCover()
    {
        $item = $this->getCoverItem();

        return $item ? $item->getData() : null;
    }

    /**
     * Whether a cover image meta entry does exist.
     *
     * @return bool
     */
    public function hasCover()
    {
        return !empty($this->getCoverId());
    }

    /**
     * Add a title page with the cover image to the EPUB.
     *
     * @param string $templatePath The path to the template file. Defaults to an XHTML file contained in this library.
     */
    public function addCoverImageTitlePage($templatePath = __DIR__ . '/../templates/titlepage.xhtml')
    {
        $xhtmlFilename = self::TITLE_PAGE_ID . '.xhtml';

        // add title page file to zip
        $template = file_get_contents($templatePath);
        $xhtml = strtr($template, ['{{ title }}' => $this->getTitle(), '{{ coverPath }}' => $this->getCoverPath()]);
        $this->zip->addFromString($this->packageDir . $xhtmlFilename, $xhtml);

        // prepend title page file to manifest
        $parent = $this->packageXPath->query('//opf:manifest')->item(0);
        $node = new EpubDomElement('opf:item');
        $parent->insertBefore($node, $parent->firstChild);
        $node->setAttrib('id', self::TITLE_PAGE_ID);
        $node->setAttrib('opf:href', $xhtmlFilename);
        $node->setAttrib('opf:media-type', 'application/xhtml+xml');

        // prepend title page spine item
        $parent = $this->packageXPath->query('//opf:spine')->item(0);
        $node = new EpubDomElement('opf:itemref');
        $parent->insertBefore($node, $parent->firstChild);
        $node->setAttrib('idref', self::TITLE_PAGE_ID);

        // prepend title page guide reference
        $parent = $this->packageXPath->query('//opf:guide')->item(0);
        $node = new EpubDomElement('opf:reference');
        $parent->insertBefore($node, $parent->firstChild);
        $node->setAttrib('opf:href', $xhtmlFilename);
        $node->setAttrib('opf:type', 'cover');
        $node->setAttrib('opf:title', 'Title Page');
    }

    /**
     * Remove the title page added by this library (determined by a certain manifest item ID).
     */
    public function removeTitlePage()
    {
        $xhtmlFilename = self::TITLE_PAGE_ID . '.xhtml';

        // remove title page file from zip
        $this->zip->deleteName($this->packageDir . $xhtmlFilename);

        // remove title page file from manifest
        $nodes = $this->packageXPath->query('//opf:manifest/opf:item[@id="' . self::TITLE_PAGE_ID . '"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }

        // remove title page spine item
        $nodes = $this->packageXPath->query('//opf:spine/opf:itemref[@idref="' . self::TITLE_PAGE_ID . '"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }

        // remove title page guide reference
        $nodes = $this->packageXPath->query('//opf:guide/opf:reference[@href="' . $xhtmlFilename . '"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }
    }

    /**
     * Get the manifest of this EPUB.
     *
     * @return Manifest
     * @throws Exception
     */
    public function getManifest()
    {
        if (!$this->manifest) {
            /** @var DOMElement|null $manifestNode */
            $manifestNode = $this->packageXPath->query('//opf:manifest')->item(0);
            if (is_null($manifestNode)) {
                throw new Exception('No manifest element found in EPUB!');
            }

            $this->manifest = new Manifest();
            /** @var DOMElement $item */
            foreach ($manifestNode->getElementsByTagName('item') as $item) {
                $id = $item->getAttribute('id');
                $href = urldecode($item->getAttribute('href'));
                $fullPath = $this->packageDir . $href;
                $callable = function () use ($fullPath): string|bool {
                    // Automatic binding of $this
                    return $this->zip->getFromName($fullPath);
                };
                $size = $this->zipSizeMap[$fullPath] ?? 0;
                $mediaType = $item->getAttribute('media-type');
                $this->manifest->createItem($id, $href, $callable, $size, $mediaType);
            }
        }

        return $this->manifest;
    }

    /**
     * Get the spine structure of this EPUB.
     *
     * @return Spine
     * @throws Exception
     */
    public function getSpine()
    {
        if (!$this->spine) {
            /** @var DOMElement|null $spineNode */
            $spineNode = $this->packageXPath->query('//opf:spine')->item(0);
            if (is_null($spineNode)) {
                throw new Exception('No spine element found in EPUB!');
            }

            $manifest = $this->getManifest();

            // Get the TOC item.
            $tocId = $spineNode->getAttribute('toc');
            if (empty($tocId)) {
                throw new Exception('No TOC ID given in spine!');
            }
            if (!isset($manifest[$tocId])) {
                throw new Exception('TOC item referenced in spine missing in manifest!');
            }

            $this->spine = new Spine($manifest[$tocId]);

            $itemRefNodes = $spineNode->getElementsByTagName('itemref');
            foreach ($itemRefNodes as $itemRef) {
                /** @var DOMElement $itemRef */
                $id = $itemRef->getAttribute('idref');
                if (!isset($manifest[$id])) {
                    throw new Exception("Item $id referenced in spine missing in manifest!");
                }
                // Link the item from the manifest to the spine.
                $this->spine->appendItem($manifest[$id]);
            }
        }

        return $this->spine;
    }

    /**
     * Get the table of contents structure of this EPUB.
     *
     * @return Toc
     * @throws Exception
     */
    public function getToc()
    {
        if (!$this->toc) {
            $xp = $this->loadXPathFromItem($this->packageDir . $this->getSpine()->getTocItem()->getHref());
            $titleNode = $xp->query('//ncx:docTitle/ncx:text')->item(0);
            $title = $titleNode ? $titleNode->nodeValue : '';
            $authorNode = $xp->query('//ncx:docAuthor/ncx:text')->item(0);
            $author = $authorNode ? $authorNode->nodeValue : '';
            $this->toc = new Toc($title, $author);

            $navPointNodes = $xp->query('//ncx:navMap/ncx:navPoint');

            $this->loadNavPoints($navPointNodes, $this->toc->getNavMap(), $xp);
        }

        return $this->toc;
    }

    /**
     * Extract the contents of this EPUB.
     *
     * This concatenates contents of items according to their order in the spine.
     *
     * @param bool $keepMarkup Whether to keep the XHTML markup rather than extracted plain text.
     * @param float $fraction If less than 1, only the respective part from the beginning of the book is extracted.
     * @return string The contents of this EPUB.
     * @throws Exception
     */
    public function getContents($keepMarkup = false, $fraction = 1.0)
    {
        $contents = '';
        if ($fraction < 1) {
            $totalSize = 0;
            foreach ($this->getSpine() as $item) {
                $totalSize += $item->getSize();
            }
            $fractionSize = $totalSize * $fraction;
            $contentsSize = 0;
            foreach ($this->spine as $item) {
                $itemSize = $item->getSize();
                if ($contentsSize + $itemSize > $fractionSize) {
                    break;
                }
                $contentsSize += $itemSize;
                $contents .= $item->getContents(null, null, $keepMarkup);
            }
        } else {
            foreach ($this->getSpine() as $item) {
                $contents .= $item->getContents(null, null, $keepMarkup);
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
     */
    private function setMeta($item, $value, $attribute = false, $attributeValue = false, $caseSensitive = true)
    {
        $xpath = $this->buildMetaXPath($item, $attribute, $attributeValue, $caseSensitive);

        // set value
        $nodes = $this->packageXPath->query($xpath);
        if ($nodes->length == 1) {
            /** @var EpubDomElement $node */
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
                /** @var EpubDomElement $node */
                $node->delete();
            }
            // re-add them
            if ($value) {
                $parent = $this->packageXPath->query('//opf:metadata')->item(0);
                $node = new EpubDomElement($item, $value);
                /** @var EpubDomElement $node */
                $node = $parent->appendChild($node);
                if ($attribute) {
                    if (is_array($attributeValue)) {
                        // use first given value for new attribute
                        $attributeValue = reset($attributeValue);
                    }
                    $node->setAttrib($attribute, $attributeValue);
                }
            }
        }

        $this->sync();
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
        $nodes = $this->packageXPath->query($xpath);
        if ($nodes->length) {
            /** @var EpubDomElement $node */
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
     * Load navigation points from TOC XML DOM into TOC object structure.
     *
     * @param DOMNodeList $navPointNodes List of nodes to load from.
     * @param TocNavPointList $navPointList List structure to load into.
     * @param DOMXPath $xp The XPath of the TOC document.
     */
    private static function loadNavPoints(DOMNodeList $navPointNodes, TocNavPointList $navPointList, DOMXPath $xp)
    {
        foreach ($navPointNodes as $navPointNode) {
            /** @var DOMElement $navPointNode */
            $id = $navPointNode->getAttribute('id');
            $class = $navPointNode->getAttribute('class');
            $playOrder = (int) $navPointNode->getAttribute('playOrder');
            $labelTextNode = $xp->query('ncx:navLabel/ncx:text', $navPointNode)->item(0);
            $label = $labelTextNode ? $labelTextNode->nodeValue : '';
            /** @var DOMElement|null $contentNode */
            $contentNode = $xp->query('ncx:content', $navPointNode)->item(0);
            $contentSource = $contentNode ? $contentNode->getAttribute('src') : '';
            $navPoint = new TocNavPoint($id, $class, $playOrder, $label, $contentSource);
            $navPointList->addNavPoint($navPoint);
            $childNavPointNodes = $xp->query('ncx:navPoint', $navPointNode);
            $childNavPoints = $navPoint->getChildren();

            self::loadNavPoints($childNavPointNodes, $childNavPoints, $xp);
        }
    }

    /**
     * Load an XML file from the EPUB/ZIP archive into a new XPath object.
     *
     * @param $path string The XML file to load from the ZIP archive.
     * @return EpubDomXPath The XPath representation of the XML file.
     * @throws Exception If the given path could not be read.
     */
    private function loadXPathFromItem($path)
    {
        $data = $this->zip->getFromName($path);
        if (!$data) {
            throw new Exception("Failed to read from EPUB container: $path.");
        }
        $xml = new DOMDocument();
        $xml->registerNodeClass(DOMElement::class, EpubDomElement::class);
        $xml->loadXML($data);

        return new EpubDomXPath($xml);
    }

    /**
     * Get the identifier of the cover image manifest item.
     *
     * @return null|string
     */
    private function getCoverId()
    {
        $nodes = $this->packageXPath->query('//opf:metadata/opf:meta[@name="cover"]');
        if (!$nodes->length) {
            return null;
        }
        /** @var EpubDomElement $node */
        $node = $nodes->item(0);

        return (string)$node->getAttrib('opf:content');
    }

    /**
     * Get the manifest item identified as cover image.
     *
     * @return DataItem|null
     */
    private function getCoverItem()
    {
        $coverId = $this->getCoverId();
        if (!$coverId) {
            return null;
        }
        try {
            $manifest = $this->getManifest();
        } catch (Exception $e) {
            return null;
        }
        if (!isset($manifest[$coverId])) {
            return null;
        }

        return $manifest[$coverId];
    }

    /**
     * Get the internal path of the cover image file.
     *
     * @return string|null
     */
    private function getCoverPath()
    {
        $item = $this->getCoverItem();
        if (!$item) {
            return null;
        }

        return $item->getHref();
    }

    /**
     * Sync XPath object with updated DOM.
     */
    private function sync()
    {
        $dom = $this->packageXPath->document;
        $dom->loadXML($dom->saveXML());
        $this->packageXPath = new EpubDomXPath($dom);
        // reset structural members
        $this->manifest = null;
        $this->spine = null;
        $this->toc = null;
    }

    /**
     * Map the items of a ZIP file to their respective file sizes.
     *
     * @param string $file Path to a ZIP file
     * @return array (filename => file size)
     */
    private function loadSizeMap($file)
    {
        $sizeMap = [];

        $zip = new ZipArchive();
        $result = $zip->open($file);
        if ($result !== true) {
            throw new Exception("Unable to open file", $result);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $sizeMap[$stat['name']] = $stat['size'];
        }
        $zip->close();

        return $sizeMap;
    }

    /**
     * @return int
     */
    public function getImageCount()
    {
        $images = array_filter($this->zipSizeMap, static function ($k) {
            return preg_match('/(.jpeg|.jpg|.png|.gif)/', $k);
        }, ARRAY_FILTER_USE_KEY);

        return count($images);
    }
}
