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
        $this->assertEquals(['Shakespeare, William' => 'William Shakespeare'], $this->epub->Authors());

        // remove value with string
        $this->epub->Authors('');
        $this->assertEquals([], $this->epub->Authors());

        // set single value by String
        $this->epub->Authors('John Doe');
        $this->assertEquals(['John Doe' => 'John Doe'], $this->epub->Authors());

        // set single value by indexed array
        $this->epub->Authors(array('John Doe'));
        $this->assertEquals(['John Doe' => 'John Doe'], $this->epub->Authors());

        // remove value with array
        $this->epub->Authors(array());
        $this->assertEquals([], $this->epub->Authors());

        // set single value by associative array
        $this->epub->Authors(array('Doe, John' => 'John Doe'));
        $this->assertEquals(['Doe, John' => 'John Doe'], $this->epub->Authors());

        // set multi value by string
        $this->epub->Authors('John Doe, Jane Smith');
        $this->assertEquals(['John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith'], $this->epub->Authors());

        // set multi value by indexed array
        $this->epub->Authors(array('John Doe', 'Jane Smith'));
        $this->assertEquals(['John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith'], $this->epub->Authors());

        // set multi value by associative  array
        $this->epub->Authors(array('Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith'));
        $this->assertEquals(['Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith'], $this->epub->Authors());

        // check escaping
        $this->epub->Authors(array('Doe, John&nbsp;' => 'John Doe&nbsp;'));
        $this->assertEquals(['Doe, John&nbsp;' => 'John Doe&nbsp;'], $this->epub->Authors());
    }

    public function testTitle()
    {
        // get current value
        $this->assertEquals('Romeo and Juliet', $this->epub->Title());

        // set new value
        $this->epub->Title('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->Title());

        // delete current value
        $this->epub->Title('');
        $this->assertEquals('', $this->epub->Title());

        // check escaping
        $this->epub->Title('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->Title());
    }

    public function testLanguage()
    {
        // get current value
        $this->assertEquals('en', $this->epub->Language());

        // set new value
        $this->epub->Language('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->Language());

        // delete current value
        $this->epub->Language('');
        $this->assertEquals('', $this->epub->Language());

        // check escaping
        $this->epub->Language('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->Language());
    }

    public function testPublisher()
    {
        // get current value
        $this->assertEquals('Feedbooks', $this->epub->Publisher());

        // set new value
        $this->epub->Publisher('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->Publisher());

        // delete current value
        $this->epub->Publisher('');
        $this->assertEquals('', $this->epub->Publisher());

        // check escaping
        $this->epub->Publisher('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->Publisher());
    }

    public function testCopyright()
    {
        // get current value
        $this->assertEquals('', $this->epub->Copyright());

        // set new value
        $this->epub->Copyright('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->Copyright());

        // delete current value
        $this->epub->Copyright('');
        $this->assertEquals('', $this->epub->Copyright());

        // check escaping
        $this->epub->Copyright('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->Copyright());
    }

    public function testDescription()
    {
        // get current value
        $this->assertStringStartsWith('Romeo and Juliet is a tragic play written', $this->epub->Description());

        // set new value
        $this->epub->Description('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->Description());

        // delete current value
        $this->epub->Description('');
        $this->assertEquals('', $this->epub->Description());

        // check escaping
        $this->epub->Description('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->Description());
    }

    public function testISBN()
    {
        // get current value
        $this->assertEquals('', $this->epub->ISBN());

        // set new value
        $this->epub->ISBN('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->ISBN());

        // delete current value
        $this->epub->ISBN('');
        $this->assertEquals('', $this->epub->ISBN());

        // check escaping
        $this->epub->ISBN('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->ISBN());
    }

    public function testGoogle()
    {
        // get current value
        $this->assertEquals('', $this->epub->Google());

        // set new value
        $this->epub->Google('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->Google());

        // delete current value
        $this->epub->Google('');
        $this->assertEquals('', $this->epub->Google());

        // check escaping
        $this->epub->Google('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->Google());
    }

    public function testAmazon()
    {
        // get current value
        $this->assertEquals('', $this->epub->Amazon());

        // set new value
        $this->epub->Amazon('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->Amazon());

        // delete current value
        $this->epub->Amazon('');
        $this->assertEquals('', $this->epub->Amazon());

        // check escaping
        $this->epub->Amazon('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->Amazon());
    }

    public function testSubject()
    {
        // get current values
        $this->assertEquals(
            $this->epub->Subjects(),
            array('Fiction', 'Drama', 'Romance')
        );

        // delete current values with String
        $this->assertEquals(
            $this->epub->Subjects(''),
            array()
        );

        // set new values with String
        $this->assertEquals(
            $this->epub->Subjects('Fiction, Drama, Romance'),
            array('Fiction', 'Drama', 'Romance')
        );

        // delete current values with Array
        $this->assertEquals(
            $this->epub->Subjects(array()),
            array()
        );

        // set new values with array
        $this->assertEquals(
            $this->epub->Subjects(array('Fiction', 'Drama', 'Romance')),
            array('Fiction', 'Drama', 'Romance')
        );

        // check escaping
        $this->assertEquals(
            $this->epub->Subjects(array('Fiction', 'Drama&nbsp;', 'Romance')),
            array('Fiction', 'Drama&nbsp;', 'Romance')
        );
    }


    public function testCover()
    {
        // read current cover
        $cover = $this->epub->Cover();
        $this->assertEquals($cover['mime'], 'image/png');
        $this->assertEquals($cover['found'], 'OPS/images/cover.png');
        $this->assertEquals(strlen($cover['data']), 657911);

        // delete cover
        $cover = $this->epub->Cover('');
        $this->assertEquals($cover['mime'], 'image/gif');
        $this->assertEquals($cover['found'], false);
        $this->assertEquals(strlen($cover['data']), 42);

        // set new cover (will return a not-found as it's not yet saved)
        $cover = $this->epub->Cover($this->testImage, 'image/jpeg');
        $this->assertEquals($cover['mime'], 'image/jpeg');
        $this->assertEquals($cover['found'], 'OPS/php-epub-meta-cover.img');
        $this->assertEquals(strlen($cover['data']), 0);

        // save
        $this->epub->save();

        // read now changed cover
        $cover = $this->epub->Cover();
        $this->assertEquals($cover['mime'], 'image/jpeg');
        $this->assertEquals($cover['found'], 'OPS/php-epub-meta-cover.img');
        $this->assertEquals(strlen($cover['data']), filesize($this->testImage));
    }
}
