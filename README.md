# Bitworking\Mimeparse

This library provides basic functionality for parsing mime-types names and
matching them against a list of media-ranges. See [section 14.1][http-accept] of
[RFC 2616 (the HTTP specification)][http] for a complete explanation. More
information on the library can be found in the XML.com article
"[Just use Media Types?][jgregorio-restful]"

This library was taken from the [original mimeparse library][mimeparse]
on Google Project Hosting and has been cleaned up to conform to [PSR-0][],
[PSR-1][], and [PSR-2][] standards. It also now has support for [Composer][].
The Bitworking namespace is a nod to [Joe Gregorio][jgregorio], the original
author of this library.

## Example

Use Mimeparse to specify a list of media types your application supports and
compare that to the list of media types the user agent accepts (via the
[HTTP Accept][http-accept] header; `$_SERVER['HTTP_ACCEPT']`). Mimeparse will
give you the best match to send back to the user agent for your list of
supported types or `null` if there is no best match.

```php
$supportedTypes = array('application/xbel+xml', 'text/xml');
$httpAcceptHeader = 'text/*;q=0.5,*/*; q=0.1';
$mimeType = \Bitworking\Mimeparse::bestMatch($supportedTypes, $httpAcceptHeader);
echo $mimeType; // Should echo "text/xml"
```


[http-accept]: http://tools.ietf.org/html/rfc2616#section-14.1
[http]: http://tools.ietf.org/html/rfc2616
[jgregorio-restful]: http://www.xml.com/pub/a/2005/06/08/restful.html
[mimeparse]: http://code.google.com/p/mimeparse/
[PSR-0]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[composer]: http://getcomposer.org/
[jgregorio]: http://bitworking.org/
