<?php

namespace Epubli\Epub\Dom;

use DOMDocument;
use DOMXPath;

/**
 * EPUB-specific subclass of DOMXPath
 *
 * Source: https://github.com/splitbrain/php-epub-meta
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015–2018
 */
class XPath extends DOMXPath
{
    public function __construct(DOMDocument $doc)
    {
        parent::__construct($doc);

        foreach (XmlNamespace::toPrefixArray() as $prefix => $namespaceUri) {
            $this->registerNamespace($prefix, $namespaceUri);
        }
    }
}
