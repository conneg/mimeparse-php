<?php

/**
 * This file is part of bitworking/mimeparse
 *
 * @copyright Copyright (c) Joe Gregorio
 * @copyright Copyright (c) Ben Ramsey <ben@ramsey.dev>
 * @license https://opensource.org/license/mit/ MIT License
 */

declare(strict_types=1);

namespace Bitworking;

use UnexpectedValueException;

use function array_multisort;
use function array_pop;
use function explode;
use function floatval;
use function is_numeric;
use function str_contains;
use function strpos;
use function substr;
use function trim;

class Mimeparse
{
    /**
     * Parses a media-range and returns an array with its components.
     *
     * The returned array contains:
     *
     * 1. type: The type categorization.
     * 2. subtype: The subtype categorization.
     * 3. params: An associative array of all the parameters for the media-range.
     * 4. generic subtype: A more generic subtype, if one is present. See
     *    {@link https://www.rfc-editor.org/rfc/rfc6838#section-4.2.8 RFC 6838, section 4.2.8}.
     *
     * For example, the media-range `"application/xhtml+xml;q=0.5"` would get
     * parsed into:
     *
     * ```
     * [
     *     "application",
     *     "xhtml+xml",
     *     [
     *         "q" => "0.5",
     *     ],
     *     "xml",
     * ]
     * ```
     *
     * @return array{string, string, array<string, string>, string}
     *
     * @throws UnexpectedValueException when `$mediaRange` does not include a valid subtype
     */
    public static function parseMediaRange(string $mediaRange): array
    {
        $parts = explode(';', $mediaRange);

        $params = [];
        foreach ($parts as $param) {
            if (str_contains($param, '=')) {
                [$k, $v] = explode('=', trim($param));
                $params[$k] = $v;
            }
        }

        $fullType = trim($parts[0]);

        // Java URLConnection class sends an Accept header that includes a
        // single "*". Turn it into a legal wildcard.
        if ($fullType === '*') {
            $fullType = '*/*';
        }

        [$type, $subtype] = explode('/', $fullType);

        if (!$subtype) {
            throw new UnexpectedValueException('Malformed media-range: ' . $mediaRange);
        }

        $plusPos = strpos($subtype, '+');
        if ($plusPos !== false) {
            $genericSubtype = substr($subtype, $plusPos + 1);
        } else {
            $genericSubtype = $subtype;
        }

        return [trim($type), trim($subtype), $params, $genericSubtype];
    }

    /**
     * Parses a media-range via `Mimeparse::parseMediaRange()` and guarantees that
     * there is a value for the `q` param, filling it in with a proper default
     * if necessary.
     *
     * @return array{string, string, array<string, string>, string}
     */
    protected static function parseAndNormalizeMediaRange(string $mediaRange): array
    {
        $parsedMediaRange = self::parseMediaRange($mediaRange);
        $params = $parsedMediaRange[2];

        if (
            !isset($params['q'])
            || !is_numeric($params['q'])
            || floatval($params['q']) > 1
            || floatval($params['q']) < 0
        ) {
            $parsedMediaRange[2]['q'] = '1';
        }

        return $parsedMediaRange;
    }

    /**
     * Find the best match for a given mime-type against a list of
     * media-ranges that have already been parsed by
     * `Mimeparse::parseAndNormalizeMediaRange()`
     *
     * Returns the fitness and the `q` quality parameter of the best match, or
     * an array of `[-1, 0]` if no match was found. Just as for `Mimeparse::quality()`,
     * `$parsedRanges` must be an array of parsed media-ranges.
     *
     * @param list<array{string, string, array<string, string>, string}> $parsedRanges
     *
     * @return array{float, int}
     */
    protected static function qualityAndFitnessParsed(string $mimeType, array $parsedRanges): array
    {
        $bestFitness = -1;
        $bestFitQuality = 0;
        [$targetType, $targetSubtype, $targetParams] = self::parseAndNormalizeMediaRange($mimeType);

        foreach ($parsedRanges as $item) {
            [$type, $subtype, $params] = $item;

            if (
                ($type === $targetType || $type === '*' || $targetType === '*')
                && ($subtype === $targetSubtype || $subtype === '*' || $targetSubtype === '*')
            ) {
                $paramMatches = 0;
                foreach ($targetParams as $k => $v) {
                    if ($k !== 'q' && isset($params[$k]) && $v === $params[$k]) {
                        $paramMatches++;
                    }
                }

                $fitness = $type === $targetType && $targetType !== '*' ? 100 : 0;
                $fitness += $subtype === $targetSubtype && $targetSubtype !== '*' ? 10 : 0;
                $fitness += $paramMatches;

                if ($fitness > $bestFitness) {
                    $bestFitness = $fitness;
                    $bestFitQuality = $params['q'];
                }
            }
        }

        return [(float) $bestFitQuality, $bestFitness];
    }

    /**
     * Find the best match for a given mime-type against a list of
     * media-ranges that have already been parsed by
     * `Mimeparse::parseAndNormalizeMediaRange()`
     *
     * Returns the `q` quality parameter of the best match, `0` if no match was
     * found. This function behaves the same as `Mimeparse::quality()` except
     * that `$parsedRanges` must be an array of parsed media-ranges.
     *
     * @param list<array{string, string, array<string, string>, string}> $parsedRanges
     */
    protected static function qualityParsed(string $mimeType, array $parsedRanges): float
    {
        [$q] = self::qualityAndFitnessParsed($mimeType, $parsedRanges);

        return $q;
    }

    /**
     * Returns the quality "q" of a mime-type when compared against the
     * media-ranges in ranges. For example:
     *
     * ```
     * Mimeparse::quality("text/html", "text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, *\/*;q=0.5")
     * => 0.7
     * ```
     */
    public static function quality(string $mimeType, string $ranges): float
    {
        $ranges = explode(',', $ranges);
        $parsedRanges = [];

        foreach ($ranges as $r) {
            $parsedRanges[] = self::parseAndNormalizeMediaRange($r);
        }

        return self::qualityParsed($mimeType, $parsedRanges);
    }

    /**
     * Takes a list of supported mime-types and finds the best match for all
     * the media-ranges listed in header. The value of $header must be a
     * string that conforms to the format of the HTTP Accept: header. The
     * value of $supported is an array of mime-types.
     *
     * In case of ties the mime-type with the lowest index in $supported will
     * be used.
     *
     * ```
     * Mimeparse::bestMatch(["application/xbel+xml", "text/xml"], "text/*;q=0.5,*\/*; q=0.1")
     * => "text/xml"
     * ```
     *
     * @param list<string> $supported
     */
    public static function bestMatch(array $supported, string $header): ?string
    {
        $header = explode(',', $header);
        $parsedHeader = [];

        foreach ($header as $range) {
            $parsedHeader[] = self::parseAndNormalizeMediaRange($range);
        }

        $weightedMatches = [];
        foreach ($supported as $index => $mimeType) {
            [$quality, $fitness] = self::qualityAndFitnessParsed($mimeType, $parsedHeader);
            if ($quality) {
                // Mime-types closer to the beginning of the array are
                // preferred. This preference score is used to break ties.
                $preference = 0 - $index;
                $weightedMatches[] = [
                    [$quality, $fitness, $preference],
                    $mimeType,
                ];
            }
        }

        // Note that since fitness and preference are present in
        // $weightedMatches they will also be used when sorting (after quality
        // level).
        array_multisort($weightedMatches);

        /** @var array{array{float, int, int}, string} $firstChoice */
        $firstChoice = array_pop($weightedMatches);
        $quality = $firstChoice[0][0] ?? 0;

        return $quality > 0 ? $firstChoice[1] : null;
    }

    /**
     * Disable access to constructor
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
}
