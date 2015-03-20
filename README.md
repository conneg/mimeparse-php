# Bitworking\Mimeparse

[![Build Status](https://travis-ci.org/ramsey/mimeparse.svg?branch=master)](https://travis-ci.org/ramsey/mimeparse)
[![Coverage Status](https://coveralls.io/repos/ramsey/mimeparse/badge.svg?branch=master)](https://coveralls.io/r/ramsey/mimeparse)
[![Latest Stable Version](https://poser.pugx.org/bitworking/mimeparse/v/stable.svg)](https://packagist.org/packages/bitworking/mimeparse)
[![Total Downloads](https://poser.pugx.org/bitworking/mimeparse/downloads.svg)](https://packagist.org/packages/bitworking/mimeparse)
[![Latest Unstable Version](https://poser.pugx.org/bitworking/mimeparse/v/unstable.svg)](https://packagist.org/packages/bitworking/mimeparse)
[![License](https://poser.pugx.org/bitworking/mimeparse/license.svg)](https://packagist.org/packages/bitworking/mimeparse)

This library provides basic functionality for parsing mime-types names and
matching them against a list of media-ranges. See [section 14.1][http-accept] of
[RFC 2616 (the HTTP specification)][http] for a complete explanation. More
information on the library can be found in the XML.com article
"[Just use Media Types?][jgregorio-restful]"

This library was taken from the [original mimeparse library][mimeparse]
on Google Project Hosting and has been cleaned up to conform to [PSR-1][],
[PSR-2][], and [PSR-4][] standards. It also now has support for [Composer][].
The Bitworking namespace is a nod to [Joe Gregorio][jgregorio], the original
author of this library.

## Examples

Use Mimeparse to specify a list of media types your application supports and
compare that to the list of media types the user agent accepts (via the
[HTTP Accept][http-accept] header; `$_SERVER['HTTP_ACCEPT']`). Mimeparse will
give you the best match to send back to the user agent for your list of
supported types or `null` if there is no best match.

```php
<?php
$supportedTypes = array('application/xbel+xml', 'text/xml');
$httpAcceptHeader = 'text/*;q=0.5,*/*; q=0.1';
$mimeType = \Bitworking\Mimeparse::bestMatch($supportedTypes, $httpAcceptHeader);
echo $mimeType; // Should echo "text/xml"
```

You may also use Mimeparse to get the quality value of a specific media type
when compared against a range of media types (from the Accept header, for example).

```php
<?php
$httpAcceptHeader = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, *\/*;q=0.5';
$quality = \Bitworking\Mimeparse::quality('text/html', $httpAcceptHeader);
echo $quality; // Should echo 0.7
```

## Installation

The preferred method of installation is via [Composer][]:

```
php composer.phar require bitworking/mimeparse
```


[http-accept]: http://tools.ietf.org/html/rfc2616#section-14.1
[http]: http://tools.ietf.org/html/rfc2616
[jgregorio-restful]: http://www.xml.com/pub/a/2005/06/08/restful.html
[mimeparse]: http://code.google.com/p/mimeparse/
[PSR-1]: http://www.php-fig.org/psr/psr-1/
[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/
[composer]: http://getcomposer.org/
[jgregorio]: http://bitworking.org/
