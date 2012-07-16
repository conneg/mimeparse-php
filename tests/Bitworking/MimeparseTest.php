<?php
namespace Bitworking;

class MimeparseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Mimeparse
     */
    protected $object;

    protected function setUp()
    {
        $this->object = new Mimeparse();
    }

    /**
     * @covers Bitworking\Mimeparse::parseMediaRange
     */
    public function testParseMediaRange()
    {
        $expected1 = array(
            0 => 'application',
            1 => 'xml',
            2 => array('q' => '1'),
        );

        $this->assertEquals($expected1, $this->object->parseMediaRange('application/xml;q=1'));
        $this->assertEquals($expected1, $this->object->parseMediaRange('application/xml'));
        $this->assertEquals($expected1, $this->object->parseMediaRange('application/xml;q='));

        $expected2 = array(
            0 => 'application',
            1 => 'xml',
            2 => array('q' => '1', 'b' => 'other'),
        );

        $this->assertEquals($expected2, $this->object->parseMediaRange('application/xml ; q=1;b=other'));
        $this->assertEquals($expected2, $this->object->parseMediaRange('application/xml ; q=2;b=other'));

        // Java URLConnection class sends an Accept header that includes a single "*"
        $this->assertEquals(array(
            0 => '*',
            1 => '*',
            2 => array('q' => '.2'),
        ), $this->object->parseMediaRange(' *; q=.2'));
    }

    /**
     * @covers Bitworking\Mimeparse::quality
     */
    public function testQuality()
    {
        $accept = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5';

        $this->assertEquals(1, $this->object->quality('text/html;level=1', $accept));
        $this->assertEquals(0.7, $this->object->quality('text/html', $accept));
        $this->assertEquals(0.3, $this->object->quality('text/plain', $accept));
        $this->assertEquals(0.5, $this->object->quality('image/jpeg', $accept));
        $this->assertEquals(0.4, $this->object->quality('text/html;level=2', $accept));
        $this->assertEquals(0.7, $this->object->quality('text/html;level=3', $accept));
    }

    /**
     * @covers Bitworking\Mimeparse::bestMatch
     */
    public function testBestMatch()
    {
        $supportedMimeTypes1 = array('application/xbel+xml', 'application/xml');

        // direct match
        $this->assertEquals('application/xbel+xml', $this->object->bestMatch($supportedMimeTypes1, 'application/xbel+xml'));

        // direct match with a q parameter
        $this->assertEquals('application/xbel+xml', $this->object->bestMatch($supportedMimeTypes1, 'application/xbel+xml; q=1'));

        // direct match of our second choice with a q parameter
        $this->assertEquals('application/xml', $this->object->bestMatch($supportedMimeTypes1, 'application/xml; q=1'));

        // match using a subtype wildcard
        $this->assertEquals('application/xml', $this->object->bestMatch($supportedMimeTypes1, 'application/*; q=1'));

        // match using a type wildcard
        $this->assertEquals('application/xml', $this->object->bestMatch($supportedMimeTypes1, '* / *'));


        $supportedMimeTypes2 = array('application/xbel+xml', 'text/xml');

        // match using a type versus a lower weighted subtype
        $this->assertEquals('text/xml', $this->object->bestMatch($supportedMimeTypes2, 'text/ *;q=0.5,* / *;q=0.1'));

        // fail to match anything
        $this->assertEquals(null, $this->object->bestMatch($supportedMimeTypes2, 'text/html,application/atom+xml; q=0.9'));


        $supportedMimeTypes3 = array('application/json', 'text/html');

        // common Ajax scenario
        $this->assertEquals('application/json', $this->object->bestMatch($supportedMimeTypes3, 'application/json, text/javascript, */*'));

        // verify fitness sorting
        $this->assertEquals('application/json', $this->object->bestMatch($supportedMimeTypes3, 'application/json, text/html;q=0.9'));


        $supportedMimeTypes4 = array('image/*', 'application/xml');

        // match using a type wildcard
        $this->assertEquals('image/*', $this->object->bestMatch($supportedMimeTypes4, 'image/png'));

        // match using a wildcard for both requested and supported
        $this->assertEquals('image/*', $this->object->bestMatch($supportedMimeTypes4, 'image/*'));
    }
}
