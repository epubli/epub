<?php

namespace Epubli\Epub\Dom;

use DOMElement;

/**
 * EPUB-specific subclass of DOMElement
 *
 * Source: https://github.com/splitbrain/php-epub-meta
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015–2018
 *
 * @property string $nodeValueUnescaped
 */
class Element extends DOMElement
{
    public function __construct($name, $value = '', $namespaceUri = '')
    {
        [$prefix, $name] = $this->splitQualifiedName($name);
        $value = htmlspecialchars($value);
        if (!$namespaceUri && $prefix) {
            $namespaceUri = XmlNamespace::getUri($prefix);
        }
        parent::__construct($name, $value, $namespaceUri);
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
     *
     * @param $name
     * @param string $value
     *
     * @return Element
     */
    public function newChild($name, $value = '')
    {
        [$localName, $namespaceUri] = $this->getNameContext($name);

        // this doesn't call the constructor: $node = $this->ownerDocument->createElement($name,$value);
        $node = new Element($namespaceUri ? $name : $localName, $value, $namespaceUri);

        /** @var Element $node */
        $node = $this->appendChild($node);
        return $node;
    }

    /**
     * Simple EPUB namespace aware attribute getter
     * @param string $name
     * @return string
     */
    public function getAttrib($name)
    {
        [$localName, $namespaceUri] = $this->getNameContext($name);

        // return value if none was given
        if ($namespaceUri) {
            return $this->getAttributeNS($namespaceUri, $localName);
        } else {
            return $this->getAttribute($localName);
        }
    }

    /**
     * Simple EPUB namespace aware attribute setter
     * @param string $name
     * @param mixed $value
     */
    public function setAttrib($name, $value)
    {
        [$localName, $namespaceUri] = $this->getNameContext($name);

        if ($namespaceUri) {
            $this->setAttributeNS($namespaceUri, $localName, $value);
        } else {
            $this->setAttribute($localName, $value);
        }
    }

    /**
     * Simple EPUB namespace aware attribute remover
     * @param string $name
     */
    public function removeAttrib($name)
    {
        [$localName, $namespaceUri] = $this->getNameContext($name);

        if ($namespaceUri) {
            $this->removeAttributeNS($namespaceUri, $localName);
        } else {
            $this->removeAttribute($localName);
        }
    }

    /**
     * Remove this node from the DOM
     */
    public function delete()
    {
        $this->parentNode->removeChild($this);
    }

    /**
     * Split given name in namespace prefix and local part
     *
     * @param  string $name
     * @return array  (prefix, name)
     */
    private function splitQualifiedName($name)
    {
        $list = explode(':', $name, 2);
        if (count($list) < 2) {
            array_unshift($list, '');
        }

        return $list;
    }

    /**
     * @param $name
     * @return array
     */
    private function getNameContext($name)
    {
        [$prefix, $localName] = $this->splitQualifiedName($name);

        $namespaceUri = '';
        if ($prefix) {
            $namespaceUri = XmlNamespace::getUri($prefix);
            if (
                !$this->namespaceURI && $this->isDefaultNamespace($namespaceUri)
                || $this->namespaceURI == $namespaceUri
            ) {
                $namespaceUri = '';
            }
        }

        return [$localName, $namespaceUri];
    }
}
