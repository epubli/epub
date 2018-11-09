<?php

namespace Epubli\Epub;

use Epubli\Common\Enum\InternetMediaType;
use Epubli\Epub\Toc\NavPoint;
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
    /** @var Epub */
    protected $epub;

    const TEST_EPUB = 'test.epub';
    const TEST_EPUB_COPY = 'test.copy.epub';
    const TEST_IMAGE = 'test.jpg';
    const EMPTY_ZIP = 'empty.zip';
    const BROKEN_ZIP = 'broken.zip';

    private $testEpub = __DIR__ . DIRECTORY_SEPARATOR . self::TEST_EPUB;
    private $testEpubCopy = __DIR__ . DIRECTORY_SEPARATOR . self::TEST_EPUB_COPY;
    private $testImage = __DIR__ . DIRECTORY_SEPARATOR . self::TEST_IMAGE;
    private $emptyZip = __DIR__ . DIRECTORY_SEPARATOR . self::EMPTY_ZIP;
    private $brokenZip = __DIR__ . DIRECTORY_SEPARATOR . self::BROKEN_ZIP;

    protected function setUp()
    {
        // sometime I might have accidentally broken the test file
        if (filesize($this->testEpub) != 768780) {
            die('test.epub has wrong size, make sure it\'s unmodified');
        }

        // we work on a copy to test saving
        if (!copy($this->testEpub, $this->testEpubCopy)) {
            die('failed to create copy of the test book');
        }

        $this->epub = new Epub($this->testEpubCopy);
    }

    protected function tearDown()
    {
        unlink($this->testEpubCopy);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to read epub file. Not a zip archive.
     */
    public function testLoadNonZip()
    {
        new Epub($this->testImage);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to read epub file. Zip archive inconsistent.
     */
    public function testLoadBrokenZip()
    {
        new Epub($this->brokenZip);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to read epub file. No such file.
     */
    public function testLoadMissingFile()
    {
        new Epub('/a/file/that/is/not_there.epub');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to read epub file.
     * We cannot expect a more specific exception message. ZipArchive::open returns 28
     * which is not known as an error code.
     */
    public function testLoadDirectory()
    {
        new Epub(__DIR__);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to access epub container data: META-INF/container.xml
     */
    public function testLoadEmptyZip()
    {
        new Epub($this->emptyZip);
    }

    public function testFilename()
    {
        $this->assertEquals($this->testEpubCopy, $this->epub->getFilename());
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
        $this->epub->setCover($this->testImage, 'image/jpeg');
        $this->epub->save();

        // read recently changed cover
        $cover = $this->epub->getCover();
        $this->assertEquals(filesize($this->testImage), strlen($cover));

        // delete cover
        $this->epub->clearCover();
        $cover = $this->epub->getCover();
        $this->assertNull($cover);
    }

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
        $this->assertEmpty(trim($this->epub->getContents($titlePage->getHref())));
    }

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

    public function testSpine()
    {
        $spine = $this->epub->getSpine();
        $this->assertCount(31, $spine);
        $this->assertEquals(31, $spine->count());
        $items = $spine->getItems();
        $this->assertCount(31, $items);

        $this->assertEquals('cover', $spine->first()->getId());
        $this->assertEquals(InternetMediaType::XHTML(), $spine->current()->getMediaType());
        $spine->next();
        $this->assertEquals('title.xml', $spine->current()->getHref());
        $this->assertEquals('feedbooks', $spine->last()->getId());

        $this->assertEquals('fb.ncx', $spine->getTocSource());
    }

    /**
     * @expectedException Exception
     */
    public function testContentsNonExisting()
    {
        $this->epub->getContents('I-am-not-there.xml');
    }

    public function testContents()
    {
        $contents = trim($this->epub->getContents('main0.xml'));
        $this->assertStringStartsWith('Act I', $contents);
        $this->assertStringEndsWith('our toil shall strive to mend.', $contents);
        $contents = trim($this->epub->getContents('main1.xml'));
        $this->assertStringStartsWith('SCENE I. Verona. A public place.', $contents);
        $this->assertStringEndsWith(
            'I\'ll pay that doctrine, or else die in debt.' . PHP_EOL . PHP_EOL . 'Exeunt',
            $contents
        );
    }

    public function testContentsFragment1()
    {
        $contents = trim($this->epub->getContents('main13.xml', 'section_77331', 'section_77332'));
        $this->assertEquals('Act III', $contents);
    }

    public function testContentsFragment2()
    {
        $contents = trim($this->epub->getContents('main13.xml', null, 'section_77332'));
        $this->assertEquals('Act III', $contents);
    }

    public function testContentsFragment3()
    {
        $contents = trim($this->epub->getContents('main13.xml', 'section_77332'));
        $this->assertStringStartsWith('SCENE I. A public place.', $contents);
        $this->assertStringEndsWith(
            'Mercy but murders, pardoning those that kill.' . PHP_EOL . PHP_EOL . 'Exeunt',
            $contents
        );
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Begin of fragment not found:
     */
    public function testContentsStartFragmentException()
    {
        $this->epub->getContents('main0.xml', 'NonExistingElement');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage End of fragment not found:
     */
    public function testContentsEndFragmentException()
    {
        $this->epub->getContents('main0.xml', null, 'NonExistingElement');
    }
}
