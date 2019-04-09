# Epubli EPUB Library #

PHP library for reading metadata, document structure, and plain text contents from EPUB files.

### Origin ###

This PHP EPUB library is a fork of [splitbrain/php-epub-meta](https://github.com/splitbrain/php-epub-meta).
The original code was changed quite a bit to fit our more conventional PHP coding standards in a symfony environment.
Additional functionality includes reading the TOC/spine structure of EPUBs and extracting contents from contained XML files.

### Installation ###

In your [composer](https://getcomposer.org/).json include the following:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/epubli/epub.git"
        }
    ],
    "require": {
        "epubli/epub": "dev-master"
    }
}
```
Then do:

```shell

$ composer install
```


### Usage ###


```php
<?php

// Open an EPUB file
$epub = new Epubli\Epub\Epub('/path/to/your/book.epub');
// and read some of its metadata
$title = $epub->getTitle();
$authors = $epub->getAuthors();
$desc = $epub->getDescription();

// Get the EPUB’s structural elements
$toc = $epub->getToc();
$spine = $epub->getSpine();

// Iterate over the EPUB structure
foreach ($spine as $spineItem) {
    // Get some text from the EPUB
    $chapterText = $spineItem->getContents();

    // Or find all navigation points pointing to that spine item
    $navPoints = $toc->findNavPointsForFile($spineItem->getHref());

    // Do something useful with the NavPoints.
}

```
