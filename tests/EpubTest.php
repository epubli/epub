<?php

namespace Epubli\Epub;

use DOMDocument;
use Epubli\Common\Enum\InternetMediaType;
use Epubli\Epub\Contents\NavPoint;
use Epubli\Exception\Exception;
use PHPUnit_Framework_TestCase;

/**
 * Test for EPUB library
 *
 * Source: https://github.com/splitbrain/php-epub-meta
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015
 */
class EpubTest extends PHPUnit_Framework_TestCase
{
    const TEST_EPUB = __DIR__ . '/data/test.epub';
    const TEST_EPUB_COPY = __DIR__ . '/data/test.copy.epub';
    const TEST_IMAGE = __DIR__ . '/data/test.jpg';
    const EMPTY_ZIP = __DIR__ . '/data/empty.zip';
    const BROKEN_ZIP = __DIR__ . '/data/broken.zip';
    const MARKUP_XML_1 = __DIR__ . '/data/markup.1.xml';
    const MARKUP_XML_2 = __DIR__ . '/data/markup.2.xml';
    const MARKUP_XML_3 = __DIR__ . '/data/markup.3.xml';
    const MARKUP_XML_4 = __DIR__ . '/data/markup.4.xml';
    const MARKUP_XML_5 = __DIR__ . '/data/markup.5.xml';

    /** @var Epub */
    private $epub;

    /**
     * @throws Exception
     */
    protected function setUp()
    {
        // sometime I might have accidentally broken the test file
        if (filesize(self::TEST_EPUB) != 768780) {
            die('test.epub has wrong size, make sure it\'s unmodified');
        }

        // we work on a copy to test saving
        if (!copy(self::TEST_EPUB, self::TEST_EPUB_COPY)) {
            die('failed to create copy of the test book');
        }

        $this->epub = new Epub(self::TEST_EPUB_COPY);
    }

    protected function tearDown()
    {
        unlink(self::TEST_EPUB_COPY);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to read EPUB file. Not a zip archive.
     */
    public function testLoadNonZip()
    {
        new Epub(self::TEST_IMAGE);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to read EPUB file. Zip archive inconsistent.
     */
    public function testLoadBrokenZip()
    {
        new Epub(self::BROKEN_ZIP);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to read EPUB file. No such file.
     */
    public function testLoadMissingFile()
    {
        new Epub('/a/file/that/is/not_there.epub');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to read EPUB file.
     * We cannot expect a more specific exception message. ZipArchive::open returns 28
     * which is not known as an error code.
     */
    public function testLoadDirectory()
    {
        new Epub(__DIR__);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to access EPUB container data: META-INF/container.xml
     */
    public function testLoadEmptyZip()
    {
        new Epub(self::EMPTY_ZIP);
    }

    public function testFilename()
    {
        $this->assertEquals(self::TEST_EPUB_COPY, $this->epub->getFilename());
    }

    public function testAuthors()
    {
        // read curent value
        $this->assertEquals(['Shakespeare, William' => 'William Shakespeare'], $this->epub->getAuthors());

        // remove value with string
        $this->epub->setAuthors('');
        $this->assertEquals([], $this->epub->getAuthors());

        // set single value by String
        $this->epub->setAuthors('John Doe');
        $this->assertEquals(['John Doe' => 'John Doe'], $this->epub->getAuthors());

        // set single value by indexed array
        $this->epub->setAuthors(array('John Doe'));
        $this->assertEquals(['John Doe' => 'John Doe'], $this->epub->getAuthors());

        // remove value with array
        $this->epub->setAuthors(array());
        $this->assertEquals([], $this->epub->getAuthors());

        // set single value by associative array
        $this->epub->setAuthors(array('Doe, John' => 'John Doe'));
        $this->assertEquals(['Doe, John' => 'John Doe'], $this->epub->getAuthors());

        // set multi value by string
        $this->epub->setAuthors('John Doe, Jane Smith');
        $this->assertEquals(['John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith'], $this->epub->getAuthors());

        // set multi value by indexed array
        $this->epub->setAuthors(array('John Doe', 'Jane Smith'));
        $this->assertEquals(['John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith'], $this->epub->getAuthors());

        // set multi value by associative  array
        $this->epub->setAuthors(array('Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith'));
        $this->assertEquals(['Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith'], $this->epub->getAuthors());

        // check escaping
        $this->epub->setAuthors(array('Doe, John&nbsp;' => 'John Doe&nbsp;'));
        $this->assertEquals(['Doe, John&nbsp;' => 'John Doe&nbsp;'], $this->epub->getAuthors());
    }

    public function testTitle()
    {
        // get current value
        $this->assertEquals('Romeo and Juliet', $this->epub->getTitle());

        // set new value
        $this->epub->setTitle('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getTitle());

        // delete current value
        $this->epub->setTitle('');
        $this->assertEquals('', $this->epub->getTitle());

        // check escaping
        $this->epub->setTitle('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getTitle());
    }

    public function testLanguage()
    {
        // get current value
        $this->assertEquals('en', $this->epub->getLanguage());

        // set new value
        $this->epub->setLanguage('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getLanguage());

        // delete current value
        $this->epub->setLanguage('');
        $this->assertEquals('', $this->epub->getLanguage());

        // check escaping
        $this->epub->setLanguage('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getLanguage());
    }

    public function testPublisher()
    {
        // get current value
        $this->assertEquals('Feedbooks', $this->epub->getPublisher());

        // set new value
        $this->epub->setPublisher('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getPublisher());

        // delete current value
        $this->epub->setPublisher('');
        $this->assertEquals('', $this->epub->getPublisher());

        // check escaping
        $this->epub->setPublisher('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getPublisher());
    }

    public function testCopyright()
    {
        // get current value
        $this->assertEquals('', $this->epub->getCopyright());

        // set new value
        $this->epub->setCopyright('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getCopyright());

        // delete current value
        $this->epub->setCopyright('');
        $this->assertEquals('', $this->epub->getCopyright());

        // check escaping
        $this->epub->setCopyright('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getCopyright());
    }

    public function testDescription()
    {
        // get current value
        $this->assertStringStartsWith('Romeo and Juliet is a tragic play written', $this->epub->getDescription());

        // set new value
        $this->epub->setDescription('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getDescription());

        // delete current value
        $this->epub->setDescription('');
        $this->assertEquals('', $this->epub->getDescription());

        // check escaping
        $this->epub->setDescription('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getDescription());
    }

    public function testUniqueIdentifier()
    {
        // get current value
        $this->assertEquals('urn:uuid:7d38d098-4234-11e1-97b6-001cc0a62c0b', $this->epub->getUniqueIdentifier());

        // set new value
        $this->epub->setUniqueIdentifier('134htb34tp089h1b');
        $this->assertEquals('134htb34tp089h1b', $this->epub->getUniqueIdentifier());
        // this should have affected the same node that is found when looking for UUID/URN scheme
        $this->assertEquals('134htb34tp089h1b', $this->epub->getUuid());
    }

    public function testUuid()
    {
        // get current value
        $this->assertEquals('urn:uuid:7d38d098-4234-11e1-97b6-001cc0a62c0b', $this->epub->getUuid());

        // set new value
        $this->epub->setUuid('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getUuid());

        // delete current value
        $this->epub->setUuid('');
        $this->assertEquals('', $this->epub->getUuid());

        // check escaping
        $this->epub->setUuid('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getUuid());
    }

    public function testUri()
    {
        // get current value
        $this->assertEquals('http://www.feedbooks.com/book/2936', $this->epub->getUri());

        // set new value
        $this->epub->setUri('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getUri());

        // delete current value
        $this->epub->setUri('');
        $this->assertEquals('', $this->epub->getUri());

        // check escaping
        $this->epub->setUri('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getUri());
    }

    public function testIsbn()
    {
        // get current value
        $this->assertEquals('', $this->epub->getIsbn());

        // set new value
        $this->epub->setIsbn('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getIsbn());

        // delete current value
        $this->epub->setIsbn('');
        $this->assertEquals('', $this->epub->getIsbn());

        // check escaping
        $this->epub->setIsbn('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getIsbn());
    }

    public function testSubject()
    {
        // get current values
        $this->assertEquals(['Fiction', 'Drama', 'Romance'], $this->epub->getSubjects());

        // delete current values with String
        $this->epub->setSubjects('');
        $this->assertEquals([], $this->epub->getSubjects());

        // set new values with String
        $this->epub->setSubjects('Fiction, Drama, Romance');
        $this->assertEquals(['Fiction', 'Drama', 'Romance'], $this->epub->getSubjects());

        // delete current values with Array
        $this->epub->setSubjects([]);
        $this->assertEquals([], $this->epub->getSubjects());

        // set new values with array
        $this->epub->setSubjects(['Fiction', 'Drama', 'Romance']);
        $this->assertEquals(['Fiction', 'Drama', 'Romance'], $this->epub->getSubjects());

        // check escaping
        $this->epub->setSubjects(['Fiction', 'Drama&nbsp;', 'Romance']);
        $this->assertEquals(['Fiction', 'Drama&nbsp;', 'Romance'], $this->epub->getSubjects());
    }

    public function testCover()
    {
        // read current cover
        $cover = $this->epub->getCover();
        $this->assertEquals(657911, strlen($cover));

        // change cover
        $this->epub->setCover(self::TEST_IMAGE, 'image/jpeg');
        $this->epub->save();

        // read recently changed cover
        $cover = $this->epub->getCover();
        $this->assertEquals(filesize(self::TEST_IMAGE), strlen($cover));

        // delete cover
        $this->epub->clearCover();
        $cover = $this->epub->getCover();
        $this->assertNull($cover);
    }

    /**
     * @throws Exception
     */
    public function testTitlePage()
    {
        // read current cover
        $this->epub->addCoverImageTitlePage();
        $this->epub->save();
        $spine = $this->epub->getSpine();
        $titlePage = $spine->first();

        $this->assertEquals('epubli-epub-titlepage.xhtml', $titlePage->getHref());
        $this->assertEquals('epubli-epub-titlepage', $titlePage->getId());
        $this->assertEquals('application/xhtml+xml', (string)$titlePage->getMediaType());

        // We expect an empty string since there is only an image but no text on that page.
        $this->assertEmpty(trim($titlePage->getContents()));
    }

    /**
     * @throws Exception
     */
    public function testToc()
    {
        $toc = $this->epub->getToc();
        $this->assertEquals('Romeo and Juliet', $toc->getDocTitle());
        $this->assertEquals('Shakespeare, William', $toc->getDocAuthor());
        $navMap = $toc->getNavMap();
        $this->assertEquals(8, $navMap->count());

        $navPoint = $navMap->first();
        /** @var NavPoint $navPoint */
        $this->assertEquals('level1-titlepage', $navPoint->getId());
        $this->assertEquals('titlepage', $navPoint->getClass());
        $this->assertEquals('1', $navPoint->getPlayOrder());
        $this->assertEquals('Title', $navPoint->getNavLabel());
        $this->assertEquals('title.xml', $navPoint->getContentSource());
        $this->assertCount(0, $navPoint->getChildren());

        $navMap->next();
        $navMap->next();
        $navPoint = $navMap->current();
        /** @var NavPoint $navPoint */
        $this->assertEquals('sec77303', $navPoint->getId());
        $this->assertEquals('section', $navPoint->getClass());
        $this->assertEquals('3', $navPoint->getPlayOrder());
        $this->assertEquals('Act I', $navPoint->getNavLabel());
        $this->assertEquals('main0.xml', $navPoint->getContentSource());
        $this->assertCount(6, $navPoint->getChildren());
        $this->assertEquals('Prologue', $navPoint->getChildren()->first()->getNavLabel());
        $this->assertEquals('SCENE V. A hall in Capulet\'s house.', $navPoint->getChildren()->last()->getNavLabel());
    }

    /**
     * @throws Exception
     */
    public function testSpine()
    {
        $spine = $this->epub->getSpine();
        $this->assertCount(31, $spine);

        $this->assertEquals('cover', $spine->first()->getId());
        $this->assertEquals(InternetMediaType::XHTML(), $spine->current()->getMediaType());
        $spine->next();
        $this->assertEquals('title.xml', $spine->current()->getHref());
        $this->assertEquals('feedbooks', $spine->last()->getId());

        $this->assertEquals('fb.ncx', $spine->getTocItem()->getHref());

        $this->assertSame($spine[0], $spine->first());
        $this->assertSame($spine[30], $spine->last());
    }

    /**
     * @dataProvider provideContentsTestParameters
     * @param string $referenceStart The expected start of the extracted contents.
     * @param string $referenceEnd The expected end of the extracted contents.
     * @param int $referenceSize The expected size of the extracted contents.
     * @param bool $keepMarkup Whether to extract contents with or without HTML markup.
     * @param float $fraction
     * @throws Exception
     */
    public function testContents(
        $referenceStart,
        $referenceEnd,
        $referenceSize,
        $keepMarkup,
        $fraction
    ) {
        $contents = trim($this->epub->getContents($keepMarkup, $fraction));
        $this->assertStringStartsWith($referenceStart, substr($contents, 0, 100));
        $this->assertStringEndsWith($referenceEnd, substr($contents, -100));
        $this->assertEquals($referenceSize, strlen($contents));
    }

    public function provideContentsTestParameters()
    {
        return [
            ["Romeo and Juliet\n\nWilliam Shakespeare", "www.feedbooks.com\n\n    Food for the mind", 152879, false, 1],
            ["Romeo and Juliet\n\nWilliam Shakespeare", "seek happy nights to happy days.\n\nExeunt", 24936, false, .2],
            ["Romeo and Juliet\n\nWilliam Shakespeare", "miss, our toil shall strive to mend.", 3810, false, .1],
        ];
    }

    /**
     * @dataProvider provideItemContentsTestParameters
     * @param string $referenceStart The expected start of the extracted contents.
     * @param string $referenceEnd The expected end of the extracted contents.
     * @param string $spineIndex The spine index of the item to extract contents from.
     * @param string $fragmentBegin The anchor name (ID) where to start extraction.
     * @param string $fragmentEnd The anchor name (ID) where to end extraction.
     * @throws Exception
     */
    public function testItemContents(
        $referenceStart,
        $referenceEnd,
        $spineIndex,
        $fragmentBegin = null,
        $fragmentEnd = null
    ) {
        $spine = $this->epub->getSpine();
        $contents = trim($spine[$spineIndex]->getContents($fragmentBegin, $fragmentEnd));
        $this->assertStringStartsWith($referenceStart, $contents);
        $this->assertStringEndsWith($referenceEnd, $contents);
    }

    public function provideItemContentsTestParameters()
    {
        return [
            ['Act I', 'our toil shall strive to mend.', 3],
            ['SCENE I. Verona. A public place.', "I'll pay that doctrine, or else die in debt.\n\nExeunt", 4],
            ['Act III', 'Act III', 16, 'section_77331', 'section_77332'],
            ['Act III', 'Act III', 16, null, 'section_77332'],
            ['SCENE I. A public place.', "pardoning those that kill.\n\nExeunt", 16, 'section_77332'],
        ];
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Begin of fragment not found:
     */
    public function testItemContentsStartFragmentException()
    {
        $spine = $this->epub->getSpine();
        $spine[3]->getContents('NonExistingElement');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage End of fragment not found:
     */
    public function testItemContentsEndFragmentException()
    {
        $spine = $this->epub->getSpine();
        $spine[3]->getContents(null, 'NonExistingElement');
    }

    /**
     * @dataProvider provideItemContentsMarkupTestParameters
     * @param string $referenceFile
     * @param string $spineIndex
     * @param string $fragmentBegin
     * @param string $fragmentEnd
     * @throws Exception
     */
    public function testItemContentsMarkup($referenceFile, $spineIndex, $fragmentBegin = null, $fragmentEnd = null)
    {
        $spine = $this->epub->getSpine();
        $contents = $spine[$spineIndex]->getContents($fragmentBegin, $fragmentEnd, true);
        $extracted = new DOMDocument();
        $extracted->loadXML($contents);
        $reference = new DOMDocument();
        $reference->load($referenceFile);
        $this->assertEqualXMLStructure($reference->documentElement, $extracted->documentElement);
    }

    public function provideItemContentsMarkupTestParameters()
    {
        return [
            [self::MARKUP_XML_1, 3],
            [self::MARKUP_XML_2, 4],
            [self::MARKUP_XML_3, 16, 'section_77331', 'section_77332'],
            [self::MARKUP_XML_4, 16, null, 'section_77332'],
            [self::MARKUP_XML_5, 16, 'section_77332'],
        ];
    }

    /**
     * @throws Exception
     */
    public function testItemDataSize()
    {
        $item = $this->epub->getSpine()[0];
        $size = $item->getSize();
        $data = $item->getData();

        $this->assertEquals(strlen($data), $size);
    }
}
