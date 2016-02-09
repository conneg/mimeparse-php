# Bitworking\Mimeparse Changelog

## 2.2.0

_Released 2016-02-09_

* Remove namespaced folder in accordance with PSR-4

  This does not change functionality or affect code using this library in any way.

* Update test runner on Travis CI to use latest testing tools
* Add rules for Scrutinizer
* Update documentation and add contributing documentation
* Add .gitattributes file to restrict exported files

## 2.1.2

_Released 2015-04-25_

* Changed repository ownership to <https://github.com/conneg> and updated Packagist and URLs accordingly

## 2.1.1

_Released 2015-03-20_

* Added CHANGELOG to project
* Updated Travis CI build to include testing on PHP 7
* Updated Travis CI build to lint and check for coding standards
* Coding standards fixes

## 2.1.0

_Released 2014-02-26_

* Make type/subtype matches only add fitness when they aren't "*"
* Upgraded package to use PSR-4 autoloading
* Various documentation updates

## 2.0.0

_Released 2012-09-18_

* Rename `fitnessAndQualityParsed` to `qualityAndFitnessParsed` to represent order of returned array
* Simplify process of rejecting unacceptable types
* Use order of $supported array instead of $tieBreaker to break ties
* Clarify exception message
* Make difference between `parseMediaRange` and `parseMimeType` more clear
* More accurately differentiate between media-ranges and mime-types
* Various documentation updates

## 1.1.1

_Released 2012-09-08_

* Change visibility for for `parseMimeType` to public
* Parse subtype for data format
* Improved `parseMediaType()` logic for setting quality to one (`1`)
* Adding tie-breaker functionality
* Fixed bug when quality for a type was set to zero
* Fixed wrong ordering of candidates
* Improved test suite
* Support running tests on Travis CI
* Various documentation updates

## 1.0.0

_Released 2012-07-16_

* Initial release of port of [mimeparse.php](https://code.google.com/p/mimeparse/source/browse/trunk/mimeparse.php?r=23) script
