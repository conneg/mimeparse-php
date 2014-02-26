<?php
namespace Bitworking;

use Bitworking\Mimeparse;

class MimeparseTest extends \PHPUnit_Framework_TestCase
{
    public function testParseMediaRange()
    {
        $expected = array(
            'application',
            'xml',
            array('q' => '1'),
            'xml'
        );

        $this->assertEquals($expected, Mimeparse::parseMediaRange('application/xml; q=1'));
    }

    public function testParseMediaRangeWithGenericSubtype()
    {
        $expected = array(
            'application',
            'xhtml+xml',
            array('q' => '1'),
            'xml'
        );

        $this->assertEquals($expected, Mimeparse::parseMediaRange('application/xhtml+xml; q=1'));
    }

    public function testParseMediaRangeWithSingleWildCard()
    {
        $expected = array(
            '*',
            '*',
            array(),
            '*'
        );

        $this->assertEquals($expected, Mimeparse::parseMediaRange('*'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Malformed media-range: application/;q=1
     */
    public function testParseMediaRangeWithMalformedMediaRange()
    {
        $parsed = Mimeparse::parseMediaRange('application/;q=1');
    }

    /**
     * Testing this protected method because it includes a lot of parsing
     * functionality that we wish to isolate from other tests.
     *
     * @covers Bitworking\Mimeparse::parseAndNormalizeMediaRange
     */
    public function testParseAndNormalizeMediaRange()
    {
        $method = new \ReflectionMethod('Bitworking\Mimeparse', 'parseAndNormalizeMediaRange');
        $method->setAccessible(true);

        $expected1 = array(
            0 => 'application',
            1 => 'xml',
            2 => array('q' => '1'),
            3 => 'xml',
        );

        $this->assertEquals($expected1, $method->invoke(null, 'application/xml;q=1'));
        $this->assertEquals($expected1, $method->invoke(null, 'application/xml'));
        $this->assertEquals($expected1, $method->invoke(null, 'application/xml;q='));

        $expected2 = array(
            0 => 'application',
            1 => 'xml',
            2 => array('q' => '1', 'b' => 'other'),
            3 => 'xml',
        );

        $this->assertEquals($expected2, $method->invoke(null, 'application/xml ; q=1;b=other'));
        $this->assertEquals($expected2, $method->invoke(null, 'application/xml ; q=2;b=other'));

        // Java URLConnection class sends an Accept header that includes a single "*"
        $this->assertEquals(array(
            0 => '*',
            1 => '*',
            2 => array('q' => '.2'),
            3 => '*',
        ), $method->invoke(null, ' *; q=.2'));
    }

    /**
     * @covers Bitworking\Mimeparse::quality
     * @covers Bitworking\Mimeparse::qualityParsed
     * @covers Bitworking\Mimeparse::qualityAndFitnessParsed
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
        $this->assertEquals('application/xbel+xml', Mimeparse::bestMatch($supportedMimeTypes1, 'application/*; q=1'));

        // match using a type wildcard
        $this->assertEquals('application/xbel+xml', Mimeparse::bestMatch($supportedMimeTypes1, '* / *'));


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


        $supportedMimeTypes4 = array('*/*', 'image/*', 'text/*', 'application/xml');

        // match using a type wildcard
        $this->assertEquals('image/*', Mimeparse::bestMatch($supportedMimeTypes4, 'image/png'));

        // match using wildcards for both requested and supported
        $this->assertEquals('image/*', Mimeparse::bestMatch($supportedMimeTypes4, 'image/*'));

        // match using wildcards where non-wildcard should be more fit
        $this->assertEquals('application/xml', Mimeparse::bestMatch($supportedMimeTypes4, 'application/xml,image/*'));

        // match using wildcards where more-specific wildcard should be more fit
        $this->assertEquals('image/*', Mimeparse::bestMatch($supportedMimeTypes4, '*/*,image/*'));

        // match using tied wildcards to ensure $supported preference is respected
        $this->assertEquals('image/*', Mimeparse::bestMatch($supportedMimeTypes4, 'text/*,image/*'));

        // match using a wildcard which has a higher quality than a non-wildcard
        $this->assertEquals('image/*', Mimeparse::bestMatch($supportedMimeTypes4, 'application/xml;q=0.9,image/*'));

    }

    /**
     * @covers Bitworking\Mimeparse::bestMatch
     * @see http://shiflett.org/blog/2011/may/the-accept-header
     * @see http://code.google.com/p/mimeparse/issues/detail?id=15
     */
    public function testZeroQuality()
    {
        $supportedMimeTypes = array('application/json');
        $httpAcceptHeader = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json;q=0.0';

        $this->assertNull(Mimeparse::bestMatch($supportedMimeTypes, $httpAcceptHeader));
        $this->assertNull(Mimeparse::bestMatch($supportedMimeTypes, 'application/xml,*/*;q=0'));
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

    /**
     * @covers Bitworking\Mimeparse::bestMatch
     */
    public function testBestMatchWithTies()
    {
        $supportedMimeTypes1 = array('text/html', 'application/json', 'application/hal+xml', 'application/hal+json');
        $supportedMimeTypes2 = array('text/html', 'application/hal+json', 'application/json', 'application/hal+xml');
        $httpAcceptHeader = 'application/*, text/*;q=0.8';

        $this->assertEquals('application/json', Mimeparse::bestMatch($supportedMimeTypes1, $httpAcceptHeader));
        $this->assertEquals('application/hal+json', Mimeparse::bestMatch($supportedMimeTypes2, $httpAcceptHeader));
    }
}
