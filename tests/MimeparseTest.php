<?php

declare(strict_types=1);

namespace Bitworking\Test;

use Bitworking\Mimeparse;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use UnexpectedValueException;

class MimeparseTest extends TestCase
{
    public function testParseMediaRange(): void
    {
        $expected = [
            'application',
            'xml',
            ['q' => '1'],
            'xml',
        ];

        $this->assertEquals($expected, Mimeparse::parseMediaRange('application/xml; q=1'));
    }

    public function testParseMediaRangeWithGenericSubtype(): void
    {
        $expected = [
            'application',
            'xhtml+xml',
            ['q' => '1'],
            'xml',
        ];

        $this->assertEquals($expected, Mimeparse::parseMediaRange('application/xhtml+xml; q=1'));
    }

    public function testParseMediaRangeWithSingleWildCard(): void
    {
        $expected = [
            '*',
            '*',
            [],
            '*',
        ];

        $this->assertEquals($expected, Mimeparse::parseMediaRange('*'));
    }

    public function testParseMediaRangeWithMalformedMediaRange(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Malformed media-range: application/;q=1');

        Mimeparse::parseMediaRange('application/;q=1');
    }

    /**
     * Testing this protected method because it includes a lot of parsing
     * functionality that we wish to isolate from other tests.
     */
    public function testParseAndNormalizeMediaRange(): void
    {
        $method = new ReflectionMethod('Bitworking\Mimeparse', 'parseAndNormalizeMediaRange');
        $method->setAccessible(true);

        $expected1 = [
            0 => 'application',
            1 => 'xml',
            2 => ['q' => '1'],
            3 => 'xml',
        ];

        $this->assertEquals($expected1, $method->invoke(null, 'application/xml;q=1'));
        $this->assertEquals($expected1, $method->invoke(null, 'application/xml'));
        $this->assertEquals($expected1, $method->invoke(null, 'application/xml;q='));

        $expected2 = [
            0 => 'application',
            1 => 'xml',
            2 => ['q' => '1', 'b' => 'other'],
            3 => 'xml',
        ];

        $this->assertEquals($expected2, $method->invoke(null, 'application/xml ; q=1;b=other'));
        $this->assertEquals($expected2, $method->invoke(null, 'application/xml ; q=2;b=other'));

        // Java URLConnection class sends an Accept header that includes a single "*"
        $this->assertEquals([
            0 => '*',
            1 => '*',
            2 => ['q' => '.2'],
            3 => '*',
        ], $method->invoke(null, ' *; q=.2'));
    }

    public function testQuality(): void
    {
        $accept = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5';

        $this->assertEquals(1, Mimeparse::quality('text/html;level=1', $accept));
        $this->assertEquals(0.7, Mimeparse::quality('text/html', $accept));
        $this->assertEquals(0.3, Mimeparse::quality('text/plain', $accept));
        $this->assertEquals(0.5, Mimeparse::quality('image/jpeg', $accept));
        $this->assertEquals(0.4, Mimeparse::quality('text/html;level=2', $accept));
        $this->assertEquals(0.7, Mimeparse::quality('text/html;level=3', $accept));
    }

    public function testBestMatch(): void
    {
        $supportedMimeTypes1 = ['application/xbel+xml', 'application/xml'];

        // direct match
        $this->assertEquals('application/xbel+xml', Mimeparse::bestMatch($supportedMimeTypes1, 'application/xbel+xml'));

        // direct match with a q parameter
        $this->assertEquals(
            'application/xbel+xml',
            Mimeparse::bestMatch($supportedMimeTypes1, 'application/xbel+xml; q=1'),
        );

        // direct match of our second choice with a q parameter
        $this->assertEquals('application/xml', Mimeparse::bestMatch($supportedMimeTypes1, 'application/xml; q=1'));

        // match using a subtype wildcard
        $this->assertEquals('application/xbel+xml', Mimeparse::bestMatch($supportedMimeTypes1, 'application/*; q=1'));

        // match using a type wildcard
        $this->assertEquals('application/xbel+xml', Mimeparse::bestMatch($supportedMimeTypes1, '* / *'));

        $supportedMimeTypes2 = ['application/xbel+xml', 'text/xml'];

        // match using a type versus a lower weighted subtype
        $this->assertEquals('text/xml', Mimeparse::bestMatch($supportedMimeTypes2, 'text/ *;q=0.5,* / *;q=0.1'));

        // fail to match anything
        $this->assertEquals(null, Mimeparse::bestMatch($supportedMimeTypes2, 'text/html,application/atom+xml; q=0.9'));

        $supportedMimeTypes3 = ['application/json', 'text/html'];

        // common Ajax scenario
        $this->assertEquals(
            'application/json',
            Mimeparse::bestMatch($supportedMimeTypes3, 'application/json, text/javascript, */*'),
        );

        // verify fitness sorting
        $this->assertEquals(
            'application/json',
            Mimeparse::bestMatch($supportedMimeTypes3, 'application/json, text/html;q=0.9'),
        );

        $supportedMimeTypes4 = ['*/*', 'image/*', 'text/*', 'application/xml'];

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
     * @see http://shiflett.org/blog/2011/may/the-accept-header
     * @see http://code.google.com/p/mimeparse/issues/detail?id=15
     */
    public function testZeroQuality(): void
    {
        $supportedMimeTypes = ['application/json'];
        $httpAcceptHeader = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json;q=0.0';

        $this->assertNull(Mimeparse::bestMatch($supportedMimeTypes, $httpAcceptHeader));
        $this->assertNull(Mimeparse::bestMatch($supportedMimeTypes, 'application/xml,*/*;q=0'));
    }

    public function testStarSlashStarWithItemOfZeroQuality(): void
    {
        $supportedMimeTypes = ['text/plain', 'application/json'];
        $httpAcceptHeader = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json;q=0.0';

        $this->assertEquals('text/plain', Mimeparse::bestMatch($supportedMimeTypes, $httpAcceptHeader));
    }

    /**
     * @see http://code.google.com/p/mimeparse/issues/detail?id=10
     */
    public function testStarSlashStarWithHigherQualityThanMoreSpecificType(): void
    {
        $supportedMimeTypes = ['image/jpeg', 'text/plain'];
        $httpAcceptHeader = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5';

        $this->assertEquals('image/jpeg', Mimeparse::bestMatch($supportedMimeTypes, $httpAcceptHeader));
    }

    public function testBestMatchWithTies(): void
    {
        $supportedMimeTypes1 = ['text/html', 'application/json', 'application/hal+xml', 'application/hal+json'];
        $supportedMimeTypes2 = ['text/html', 'application/hal+json', 'application/json', 'application/hal+xml'];
        $httpAcceptHeader = 'application/*, text/*;q=0.8';

        $this->assertEquals('application/json', Mimeparse::bestMatch($supportedMimeTypes1, $httpAcceptHeader));
        $this->assertEquals('application/hal+json', Mimeparse::bestMatch($supportedMimeTypes2, $httpAcceptHeader));
    }
}
