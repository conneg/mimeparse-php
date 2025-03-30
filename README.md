<h1 align="center">bitworking/mimeparse</h1>

<p align="center">
    <strong>Basic functions for handling mime-types</strong>
</p>

<p align="center">
    <a href="https://github.com/conneg/mimeparse-php"><img src="https://img.shields.io/badge/source-conneg/mimeparse--php-blue.svg?style=flat-square" alt="Source Code"></a>
    <a href="https://packagist.org/packages/bitworking/mimeparse"><img src="https://img.shields.io/packagist/v/bitworking/mimeparse.svg?style=flat-square&label=release" alt="Download Package"></a>
    <a href="https://php.net"><img src="https://img.shields.io/packagist/php-v/bitworking/mimeparse.svg?style=flat-square&colorB=%238892BF" alt="PHP Programming Language"></a>
    <a href="https://github.com/conneg/mimeparse-php/blob/main/LICENSE"><img src="https://img.shields.io/packagist/l/bitworking/mimeparse.svg?style=flat-square&colorB=darkcyan" alt="Read License"></a>
    <a href="https://github.com/conneg/mimeparse-php/actions/workflows/continuous-integration.yml"><img src="https://img.shields.io/github/actions/workflow/status/conneg/mimeparse-php/continuous-integration.yml?branch=main&style=flat-square&logo=github" alt="Build Status"></a>
    <a href="https://codecov.io/gh/conneg/mimeparse-php"><img src="https://img.shields.io/codecov/c/gh/conneg/mimeparse-php?label=codecov&logo=codecov&style=flat-square" alt="Codecov Code Coverage"></a>
</p>

## About

This library provides basic functionality for parsing mime-types names and matching
them against a list of media-ranges. See
[RFC 9110, section 5.3.2](https://www.rfc-editor.org/rfc/rfc9110.html#section-12.5.1)
for a complete explanation. More information on the library can be found in the
XML.com article "[Just use Media Types?](http://www.xml.com/pub/a/2005/06/08/restful.html)"

This library was forked from the [original mimeparse library](https://github.com/conneg/mimeparse)
on Google Project Hosting. The `Bitworking` namespace is a nod to original author
[Joe Gregorio](https://bitworking.org/).

This project adheres to a [code of conduct](CODE_OF_CONDUCT.md). By participating
in this project and its community, you are expected to uphold this code.

## Installation

Install this package as a dependency using [Composer](https://getcomposer.org).

``` bash
composer require bitworking/mimeparse
```

## Usage

Use Mimeparse to specify a list of media types your application supports and
compare that to the list of media types the user agent accepts (via the
[HTTP Accept](https://www.rfc-editor.org/rfc/rfc9110.html#section-12.5.1) header;
`$_SERVER['HTTP_ACCEPT']`). Mimeparse will give you the best match to send back
to the user agent for your list of supported types or `null` if there is no best
match.

``` php
$supportedTypes = ['application/xbel+xml', 'text/xml'];
$httpAcceptHeader = 'text/*;q=0.5,*/*; q=0.1';

$mimeType = \Bitworking\Mimeparse::bestMatch($supportedTypes, $httpAcceptHeader);

echo $mimeType; // Should echo "text/xml"
```

You may also use Mimeparse to get the quality value of a specific media type
when compared against a range of media types (from the `Accept` header, for
example).

``` php
$httpAcceptHeader = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, *\/*;q=0.5';

$quality = \Bitworking\Mimeparse::quality('text/html', $httpAcceptHeader);

echo $quality; // Should echo 0.7
```

## Contributing

Contributions are welcome! To contribute, please familiarize yourself with
[CONTRIBUTING.md](CONTRIBUTING.md).

## Coordinated Disclosure

Keeping user information safe and secure is a top priority, and we welcome the
contribution of external security researchers. If you believe you've found a
security issue in software that is maintained in this repository, please read
[SECURITY.md](SECURITY.md) for instructions on submitting a vulnerability report.

## Copyright and License

bitworking/mimeparse is copyright © [Ben Ramsey](https://ben.ramsey.dev)
and licensed for use under the terms of the MIT License (MIT).

The original mimeparse.php library is copyright © [Joe Gregorio](https://bitworking.org/)
and licensed for use under the terms of the MIT License (MIT).

Please see [LICENSE](LICENSE) for more information.

[http-accept]: https://www.rfc-editor.org/rfc/rfc9110.html#section-12.5.1
