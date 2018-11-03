<?php

namespace Epubli\Epub;

use PHPUnit_Framework_TestCase;

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
//        unlink($this->testEpubCopy);
    }

    public function testAuthors()
    {
        // read curent value
        $this->assertEquals(
            $this->epub->Authors(),
            array('Shakespeare, William' => 'William Shakespeare')
        );

        // remove value with string
        $this->assertEquals(
            $this->epub->Authors(''),
            array()
        );

        // set single value by String

        $this->assertEquals(
            $this->epub->Authors('John Doe'),
            array('John Doe' => 'John Doe')
        );

        // set single value by indexed array
        $this->assertEquals(
            $this->epub->Authors(array('John Doe')),
            array('John Doe' => 'John Doe')
        );

        // remove value with array
        $this->assertEquals(
            $this->epub->Authors(array()),
            array()
        );

        // set single value by associative array
        $this->assertEquals(
            $this->epub->Authors(array('Doe, John' => 'John Doe')),
            array('Doe, John' => 'John Doe')
        );

        // set multi value by string
        $this->assertEquals(
            $this->epub->Authors('John Doe, Jane Smith'),
            array('John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith')
        );

        // set multi value by indexed array
        $this->assertEquals(
            $this->epub->Authors(array('John Doe', 'Jane Smith')),
            array('John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith')
        );

        // set multi value by associative  array
        $this->assertEquals(
            $this->epub->Authors(array('Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith')),
            array('Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith')
        );

        // check escaping
        $this->assertEquals(
            $this->epub->Authors(array('Doe, John&nbsp;' => 'John Doe&nbsp;')),
            array('Doe, John&nbsp;' => 'John Doe&nbsp;')
        );
    }

    public function testTitle()
    {
        // get current value
        $this->assertEquals(
            $this->epub->Title(),
            'Romeo and Juliet'
        );

        // delete current value
        $this->assertEquals(
            $this->epub->Title(''),
            ''
        );

        // get current value
        $this->assertEquals(
            $this->epub->Title(),
            ''
        );

        // set new value
        $this->assertEquals(
            $this->epub->Title('Foo Bar'),
            'Foo Bar'
        );

        // check escaping
        $this->assertEquals(
            $this->epub->Title('Foo&nbsp;Bar'),
            'Foo&nbsp;Bar'
        );
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
