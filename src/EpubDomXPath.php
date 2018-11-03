<?php

namespace Epubli\Epub;

use DOMDocument;
use DOMXPath;

/**
 * EPUB-specific subclass of DOMXPath
 *
 * Source: https://github.com/splitbrain/php-epub-meta
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015
 */
class EpubDomXPath extends DOMXPath
{
    public function __construct(DOMDocument $doc)
    {
        parent::__construct($doc);

        if ($doc->documentElement instanceof EpubDomElement) {
            foreach ($doc->documentElement->namespaces as $ns => $url) {
                $this->registerNamespace($ns, $url);
            }
        }
    }
}
