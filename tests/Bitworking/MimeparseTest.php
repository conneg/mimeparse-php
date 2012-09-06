<?php
namespace Bitworking;

use Bitworking\Mimeparse;

class MimeparseTest extends \PHPUnit_Framework_TestCase
{
    public function testParseMimeTypeWith()
    {
        $expected = array(
            'application',
            'xml',
            array('q' => '1'),
            'xml'
        );
        
        $this->assertEquals($expected, Mimeparse::parseMimeType('application/xml; q=1'));
    }
    
    public function testParseMimeTypeWithFormat()
    {
        $expected = array(
            'application',
            'xhtml+xml',
            array('q' => '1'),
            'xml'
        );
        
        $this->assertEquals($expected, Mimeparse::parseMimeType('application/xhtml+xml; q=1'));
    }

    public function testParseMimeTypeWithSingleWildCard()
    {
        $expected = array(
            '*',
            '*',
            array(),
            '*'
        );

        $this->assertEquals($expected, Mimeparse::parseMimeType('*'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage malformed mime type
     */
    public function testParseMimeTypeWithMalformedMimeType()
    {
        $parsed = Mimeparse::parseMimeType('application/;q=1');
    }

    /**
     * Testing this protected method because it includes a lot of parsing
     * functionality that we wish to isolate from other tests.
     *
     * @covers Bitworking\Mimeparse::parseMediaRange
     */
    public function testParseMediaRange()
    {
        $method = new \ReflectionMethod('Bitworking\Mimeparse', 'parseMediaRange');
        $method->setAccessible(true);

        $expected1 = array(
            0 => 'application',
            1 => 'xml',
            2 => array('q' => '1'),
        );

        $this->assertEquals($expected1, $method->invoke(null, 'application/xml;q=1'));
        $this->assertEquals($expected1, $method->invoke(null, 'application/xml'));
        $this->assertEquals($expected1, $method->invoke(null, 'application/xml;q='));

        $expected2 = array(
            0 => 'application',
            1 => 'xml',
            2 => array('q' => '1', 'b' => 'other'),
        );

        $this->assertEquals($expected2, $method->invoke(null, 'application/xml ; q=1;b=other'));
        $this->assertEquals($expected2, $method->invoke(null, 'application/xml ; q=2;b=other'));

        // Java URLConnection class sends an Accept header that includes a single "*"
        $this->assertEquals(array(
            0 => '*',
            1 => '*',
            2 => array('q' => '.2'),
        ), $method->invoke(null, ' *; q=.2'));
    }

    /**
     * @covers Bitworking\Mimeparse::quality
     * @covers Bitworking\Mimeparse::qualityParsed
     * @covers Bitworking\Mimeparse::fitnessAndQualityParsed
     */
    public function testQuality()
    {
        $accept = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5';

        $this->assertEquals(1, Mimeparse::quality('text/html;level=1', $accept));
        $this->assertEquals(0.7, Mimeparse::quality('text/html', $accept));
        $this->assertEquals(0.3, Mimeparse::quality('text/plain', $accept));
        $this->assertEquals(0.5, Mimeparse::quality('image/jpeg', $accept));
        $this->assertEquals(0.4, Mimeparse::quality('text/html;level=2', $accept));
        $this->assertEquals(0.7, Mimeparse::quality('text/html;level=3', $accept));
    }

    /**
     * @covers Bitworking\Mimeparse::bestMatch
     */
    public function testBestMatch()
    {
        $supportedMimeTypes1 = array('application/xbel+xml', 'application/xml');

        // direct match
        $this->assertEquals('application/xbel+xml', Mimeparse::bestMatch($supportedMimeTypes1, 'application/xbel+xml'));

        // direct match with a q parameter
        $this->assertEquals('application/xbel+xml', Mimeparse::bestMatch($supportedMimeTypes1, 'application/xbel+xml; q=1'));

        // direct match of our second choice with a q parameter
        $this->assertEquals('application/xml', Mimeparse::bestMatch($supportedMimeTypes1, 'application/xml; q=1'));

        // match using a subtype wildcard
        $this->assertEquals('application/xml', Mimeparse::bestMatch($supportedMimeTypes1, 'application/*; q=1'));

        // match using a type wildcard
        $this->assertEquals('application/xml', Mimeparse::bestMatch($supportedMimeTypes1, '* / *'));


        $supportedMimeTypes2 = array('application/xbel+xml', 'text/xml');

        // match using a type versus a lower weighted subtype
        $this->assertEquals('text/xml', Mimeparse::bestMatch($supportedMimeTypes2, 'text/ *;q=0.5,* / *;q=0.1'));

        // fail to match anything
        $this->assertEquals(null, Mimeparse::bestMatch($supportedMimeTypes2, 'text/html,application/atom+xml; q=0.9'));


        $supportedMimeTypes3 = array('application/json', 'text/html');

        // common Ajax scenario
        $this->assertEquals('application/json', Mimeparse::bestMatch($supportedMimeTypes3, 'application/json, text/javascript, */*'));

        // verify fitness sorting
        $this->assertEquals('application/json', Mimeparse::bestMatch($supportedMimeTypes3, 'application/json, text/html;q=0.9'));


        $supportedMimeTypes4 = array('image/*', 'application/xml');

        // match using a type wildcard
        $this->assertEquals('image/*', Mimeparse::bestMatch($supportedMimeTypes4, 'image/png'));

        // match using a wildcard for both requested and supported
        $this->assertEquals('image/*', Mimeparse::bestMatch($supportedMimeTypes4, 'image/*'));
    }

    /**
     * @covers Bitworking\Mimeparse::bestMatch
     * @see http://shiflett.org/blog/2011/may/the-accept-header
     */
    public function testZeroQuality()
    {
        $supportedMimeTypes = array('application/json');
        $httpAcceptHeader = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json;q=0.0';

        $this->assertNull(Mimeparse::bestMatch($supportedMimeTypes, $httpAcceptHeader));
    }

    /**
     * @covers Bitworking\Mimeparse::bestMatch
     */
    public function testStarSlashStarWithItemOfZeroQuality()
    {
        $supportedMimeTypes = array('text/plain', 'application/json');
        $httpAcceptHeader = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json;q=0.0';

        $this->assertEquals('text/plain', Mimeparse::bestMatch($supportedMimeTypes, $httpAcceptHeader));
    }

    /**
     * @covers Bitworking\Mimeparse::bestMatch
     * @see http://code.google.com/p/mimeparse/issues/detail?id=10
     */
    public function testStarSlashStarWithHigherQualityThanMoreSpecificType()
    {
        $supportedMimeTypes = array('image/jpeg', 'text/plain');
        $httpAcceptHeader = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5';

        $this->assertEquals('image/jpeg', Mimeparse::bestMatch($supportedMimeTypes, $httpAcceptHeader));
    }
}
