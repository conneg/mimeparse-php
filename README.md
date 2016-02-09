# bitworking/mimeparse

[![Source Code][badge-source]][source]
[![Latest Version][badge-release]][release]
[![Software License][badge-license]][license]
[![Build Status][badge-build]][build]
[![Scrutinizer][badge-quality]][quality]
[![Coverage Status][badge-coverage]][coverage]
[![Total Downloads][badge-downloads]][downloads]

This library provides basic functionality for parsing mime-types names and matching them against a list of media-ranges. See [section 5.3.2][http-accept] of [RFC 7231 (HTTP semantics and content specification)][http] for a complete explanation. More information on the library can be found in the XML.com article "[Just use Media Types?][jgregorio-restful]"

This library was taken from the [original mimeparse library][mimeparse] on Google Project Hosting and has been cleaned up to conform to [PSR-1][], [PSR-2][], and [PSR-4][] standards. It also now has support for [Composer][]. The Bitworking namespace is a nod to [Joe Gregorio][jgregorio], the original author of this library.

## Installation

The preferred method of installation is via [Packagist][] and [Composer][]. Run the following command to install the package and add it as a requirement to your project's `composer.json`:

```bash
composer require bitworking/mimeparse
```

## Examples

Use Mimeparse to specify a list of media types your application supports and compare that to the list of media types the user agent accepts (via the [HTTP Accept][http-accept] header; `$_SERVER['HTTP_ACCEPT']`). Mimeparse will give you the best match to send back to the user agent for your list of supported types or `null` if there is no best match.

```php
<?php
$supportedTypes = array('application/xbel+xml', 'text/xml');
$httpAcceptHeader = 'text/*;q=0.5,*/*; q=0.1';

$mimeType = \Bitworking\Mimeparse::bestMatch($supportedTypes, $httpAcceptHeader);

echo $mimeType; // Should echo "text/xml"
```

You may also use Mimeparse to get the quality value of a specific media type when compared against a range of media types (from the Accept header, for example).

```php
<?php
$httpAcceptHeader = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, *\/*;q=0.5';

$quality = \Bitworking\Mimeparse::quality('text/html', $httpAcceptHeader);

echo $quality; // Should echo 0.7
```

## Contributing

Contributions are welcome! Please read [CONTRIBUTING][] for details.

## Copyright and license

The original mimeparse library is copyright © [Joe Gregorio][jgregorio]. The bitworking/mimeparse library for PHP is copyright © [Ben Ramsey][]. Both authors have licensed the source for use under the MIT License (MIT). Please see [LICENSE][] for more information.


[http-accept]: http://tools.ietf.org/html/rfc7231#section-5.3.2
[http]: http://tools.ietf.org/html/rfc7231
[jgregorio-restful]: http://www.xml.com/pub/a/2005/06/08/restful.html
[mimeparse]: https://github.com/conneg/mimeparse
[PSR-1]: http://www.php-fig.org/psr/psr-1/
[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/
[composer]: http://getcomposer.org/
[jgregorio]: http://bitworking.org/
[ben ramsey]: https://benramsey.com/
[packagist]: https://packagist.org/packages/bitworking/mimeparse
[contributing]: https://github.com/conneg/mimeparse-php/blob/master/CONTRIBUTING.md

[badge-source]: https://img.shields.io/badge/source-conneg/mimeparse--php-blue.svg?style=flat-square
[badge-release]: https://img.shields.io/packagist/v/bitworking/mimeparse.svg?style=flat-square
[badge-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[badge-build]: https://img.shields.io/travis/conneg/mimeparse-php/master.svg?style=flat-square
[badge-quality]: https://img.shields.io/scrutinizer/g/conneg/mimeparse-php/master.svg?style=flat-square
[badge-coverage]: https://img.shields.io/coveralls/conneg/mimeparse-php/master.svg?style=flat-square
[badge-downloads]: https://img.shields.io/packagist/dt/bitworking/mimeparse.svg?style=flat-square

[source]: https://github.com/conneg/mimeparse-php
[release]: https://packagist.org/packages/bitworking/mimeparse
[license]: https://github.com/conneg/mimeparse-php/blob/master/LICENSE
[build]: https://travis-ci.org/conneg/mimeparse-php
[quality]: https://scrutinizer-ci.com/g/conneg/mimeparse-php/
[coverage]: https://coveralls.io/r/conneg/mimeparse-php?branch=master
[downloads]: https://packagist.org/packages/bitworking/mimeparse
