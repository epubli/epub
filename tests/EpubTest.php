<?php

namespace Epubli\Epub;

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

    private $testEpub = __DIR__.DIRECTORY_SEPARATOR.self::TEST_EPUB;
    private $testEpubCopy = __DIR__.DIRECTORY_SEPARATOR.self::TEST_EPUB_COPY;
    private $testImage = __DIR__.DIRECTORY_SEPARATOR.self::TEST_IMAGE;

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

    public function testURI()
    {
        // get current value
        $this->assertEquals('http://www.feedbooks.com/book/2936', $this->epub->getURI());

        // set new value
        $this->epub->setURI('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getURI());

        // delete current value
        $this->epub->setURI('');
        $this->assertEquals('', $this->epub->getURI());

        // check escaping
        $this->epub->setURI('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getURI());
    }

    public function testISBN()
    {
        // get current value
        $this->assertEquals('', $this->epub->getISBN());

        // set new value
        $this->epub->setISBN('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getISBN());

        // delete current value
        $this->epub->setISBN('');
        $this->assertEquals('', $this->epub->getISBN());

        // check escaping
        $this->epub->setISBN('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getISBN());
    }

    public function testGoogle()
    {
        // get current value
        $this->assertEquals('', $this->epub->getGoogle());

        // set new value
        $this->epub->setGoogle('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getGoogle());

        // delete current value
        $this->epub->setGoogle('');
        $this->assertEquals('', $this->epub->getGoogle());

        // check escaping
        $this->epub->setGoogle('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getGoogle());
    }

    public function testAmazon()
    {
        // get current value
        $this->assertEquals('', $this->epub->getAmazon());

        // set new value
        $this->epub->setAmazon('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getAmazon());

        // delete current value
        $this->epub->setAmazon('');
        $this->assertEquals('', $this->epub->getAmazon());

        // check escaping
        $this->epub->setAmazon('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getAmazon());
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
        $this->assertEquals($cover['mime'], 'image/png');
        $this->assertEquals($cover['found'], 'OPS/images/cover.png');
        $this->assertEquals(strlen($cover['data']), 657911);

        // delete cover
        $this->epub->deleteCover();
        $cover = $this->epub->getCover();
        $this->assertEquals($cover['mime'], 'image/gif');
        $this->assertEquals($cover['found'], false);
        $this->assertEquals(strlen($cover['data']), 42);

        // set new cover (will return a not-found as it's not yet saved)
        $this->epub->setCover($this->testImage, 'image/jpeg');
        $cover = $this->epub->getCover();
        $this->assertEquals($cover['mime'], 'image/jpeg');
        $this->assertEquals($cover['found'], 'OPS/php-epub-meta-cover.img');
        $this->assertEquals(strlen($cover['data']), 0);

        // save
        $this->epub->save();

        // read recently changed cover
        $cover = $this->epub->getCover();
        $this->assertEquals($cover['mime'], 'image/jpeg');
        $this->assertEquals($cover['found'], 'OPS/php-epub-meta-cover.img');
        $this->assertEquals(strlen($cover['data']), filesize($this->testImage));
    }
}
