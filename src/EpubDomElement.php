<?php

namespace Epubli\Epub;

use DOMElement;

/**
 * EPUB-specific subclass of DOMElement
 *
 * Source: https://github.com/splitbrain/php-epub-meta
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015
 *
 * @property string $nodeValueUnescaped
 */
class EpubDomElement extends DOMElement
{
    public $namespaces = [
        'n' => 'urn:oasis:names:tc:opendocument:xmlns:container',
        'opf' => 'http://www.idpf.org/2007/opf',
        'dc' => 'http://purl.org/dc/elements/1.1/'
    ];

    public function __construct($name, $value = '', $namespaceURI = '')
    {
        list($ns, $name) = $this->splitNamespacedName($name);
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
     * @return EpubDomElement
     */
    public function newChild($name, $value = '')
    {
        list($ns, $local) = $this->splitNamespacedName($name);
        $nsuri = '';
        if ($ns) {
            $nsuri = $this->namespaces[$ns];
            if ($this->isDefaultNamespace($nsuri)) {
                $name = $local;
                $nsuri = '';
            }
        }

        // this doesn't call the constructor: $node = $this->ownerDocument->createElement($name,$value);
        $node = new EpubDomElement($name, $value, $nsuri);

        return $this->appendChild($node);
    }

    /**
     * Split given name in namespace prefix and local part
     *
     * @param  string $name
     * @return array  (namespace, name)
     */
    public function splitNamespacedName($name)
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
        list($ns, $attr) = $this->splitNamespacedName($attr);

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
