<?php

namespace Epubli\Epub;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
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
    /** @var string The file path of the epub file */
    private $filename;
    /** @var ZipArchive The the epub file loaded as zip archive */
    private $zip;
    /** @var string The (archive local) path to the root (.opf) file */
    private $opfFile;
    /** @var DOMDocument The DOM of the root (.opf) file */
    private $opfDom;
    /** @var EpubDOMXPath The XPath object for the root (.opf) file */
    private $opfXPath;
    /** @var string The file path to the cover image if set */
    private $newCoverImage = '';

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
        if (!@$this->zip->open($this->filename)) {
            throw new Exception('Failed to read epub file');
        }

        // read container data
        $data = $this->zip->getFromName('META-INF/container.xml');
        if ($data == false) {
            throw new Exception('Failed to access epub container data');
        }
        $xml = new DOMDocument();
        $xml->registerNodeClass(DOMElement::class, EpubDOMElement::class);
        $xml->loadXML($data);
        $xpath = new EpubDOMXPath($xml);
        $nodes = $xpath->query('//n:rootfiles/n:rootfile[@media-type="application/oebps-package+xml"]');
        /** @var EpubDOMElement $node */
        $node = $nodes->item(0);
        $this->opfFile = $node->attr('full-path');

        // load metadata
        $data = $this->zip->getFromName($this->opfFile);
        if (!$data) {
            throw new Exception('Failed to access epub metadata');
        }
        $this->opfDom = new DOMDocument();
        $this->opfDom->registerNodeClass(DOMElement::class, EpubDOMElement::class);
        $this->opfDom->loadXML($data);
        $this->opfDom->formatOutput = true;
        $this->opfXPath = new EpubDOMXPath($this->opfDom);
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /**
     * file name getter
     */
    public function file()
    {
        return $this->filename;
    }

    /**
     * Writes back all meta data changes
     */
    public function save()
    {
        $this->zip->addFromString($this->opfFile, $this->opfDom->saveXML());
        // add the cover image
        if ($this->newCoverImage) {
            $path = dirname('/'.$this->opfFile).'/php-epub-meta-cover.img'; // image path is relative to meta file
            $path = ltrim($path, '/');

            $this->zip->addFile($this->newCoverImage, $path);
            $this->newCoverImage = '';
        }
        // close and reopen zip archive
        $this->zip->close();
        $this->zip->open($this->filename);
    }

    /**
     * Get or set the book author(s)
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
     * @params array $authors
     */
    public function Authors($authors = false)
    {
        // set new data
        if ($authors !== false) {
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

        // read current data
        $rolefix = false;
        $authors = array();
        $nodes = $this->opfXPath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        if ($nodes->length == 0) {
            // no nodes where found, let's try again without role
            $nodes = $this->opfXPath->query('//opf:metadata/dc:creator');
            $rolefix = true;
        }
        foreach ($nodes as $node) {
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
     * Set or get the book title
     *
     * @param string $title
     */
    public function Title($title = false)
    {
        return $this->getset('dc:title', $title);
    }

    /**
     * Set or get the book's language
     *
     * @param string $lang
     */
    public function Language($lang = false)
    {
        return $this->getset('dc:language', $lang);
    }

    /**
     * Set or get the book' publisher info
     *
     * @param string $publisher
     */
    public function Publisher($publisher = false)
    {
        return $this->getset('dc:publisher', $publisher);
    }

    /**
     * Set or get the book's copyright info
     *
     * @param string $rights
     */
    public function Copyright($rights = false)
    {
        return $this->getset('dc:rights', $rights);
    }

    /**
     * Set or get the book's description
     *
     * @param string $description
     */
    public function Description($description = false)
    {
        return $this->getset('dc:description', $description);
    }

    /**
     * Set or get the book's ISBN number
     *
     * @param string $isbn
     */
    public function ISBN($isbn = false)
    {
        return $this->getset('dc:identifier', $isbn, 'opf:scheme', 'ISBN');
    }

    /**
     * Set or get the Google Books ID
     *
     * @param string $google
     */
    public function Google($google = false)
    {
        return $this->getset('dc:identifier', $google, 'opf:scheme', 'GOOGLE');
    }

    /**
     * Set or get the Amazon ID of the book
     *
     * @param string $amazon
     */
    public function Amazon($amazon = false)
    {
        return $this->getset('dc:identifier', $amazon, 'opf:scheme', 'AMAZON');
    }

    /**
     * Set or get the book's subjects (aka. tags)
     *
     * Subject should be given as array, but a comma separated string will also
     * be accepted.
     *
     * @param array|bool $subjects
     * @return array|bool
     */
    public function Subjects($subjects = false)
    {
        // setter
        if ($subjects !== false) {
            if (is_string($subjects)) {
                if ($subjects === '') {
                    $subjects = array();
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

        //getter
        $subjects = array();
        $nodes = $this->opfXPath->query('//opf:metadata/dc:subject');
        foreach ($nodes as $node) {
            $subjects[] = $node->nodeValueUnescaped;
        }

        return $subjects;
    }

    /**
     * Read the cover data
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
     * When adding a new image this function return no or old data because the
     * image contents are not in the epub file, yet. The image will be added when
     * the save() method is called.
     *
     * @param bool|string $path local filesystem path to a new cover image
     * @param bool|string $mime mime type of the given file
     * @return array
     */
    public function Cover($path = false, $mime = false)
    {
        // set cover
        if ($path !== false) {
            // remove current pointer
            $nodes = $this->opfXPath->query('//opf:metadata/opf:meta[@name="cover"]');
            foreach ($nodes as $node) {
                /** @var EpubDOMElement $node */
                $node->delete();
            }
            // remove previous manifest entries if they where made by us
            $nodes = $this->opfXPath->query('//opf:manifest/opf:item[@id="php-epub-meta-cover"]');
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
                $node->attr('opf:content', 'php-epub-meta-cover');

                // add manifest
                $parent = $this->opfXPath->query('//opf:manifest')->item(0);
                $node = $parent->newChild('opf:item');
                $node->attr('id', 'php-epub-meta-cover');
                $node->attr('opf:href', 'php-epub-meta-cover.img');
                $node->attr('opf:media-type', $mime);

                // remember path for save action
                $this->newCoverImage = $path;
            }

            $this->reparse();
        }

        // load cover
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
        $path = dirname('/'.$this->opfFile).'/'.$path; // image path is relative to meta file
        $path = ltrim($path, '/');

        $data = $this->zip->getFromName($path);

        return array(
            'mime' => $mime,
            'data' => $data,
            'found' => $path
        );
    }

    /**
     * A simple getter/setter for simple meta attributes
     *
     * It should only be used for attributes that are expected to be unique
     *
     * @param string $item XML node to set/get
     * @param bool|string $value New node value
     * @param bool|string $att Attribute name
     * @param bool|string $aval Attribute value
     * @return string
     */
    protected function getset($item, $value = false, $att = false, $aval = false)
    {
        // construct xpath
        $xpath = '//opf:metadata/'.$item;
        if ($att) {
            $xpath .= "[@$att=\"$aval\"]";
        }

        // set value
        if ($value !== false) {
            $value = htmlspecialchars($value);
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
                    if ($att) {
                        $node->attr($att, $aval);
                    }
                }
            }

            $this->reparse();
        }

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
     * Return a not found response for Cover()
     */
    protected function no_cover()
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
    protected function reparse()
    {
        $this->opfDom->loadXML($this->opfDom->saveXML());
        $this->opfXPath = new EpubDOMXPath($this->opfDom);
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
