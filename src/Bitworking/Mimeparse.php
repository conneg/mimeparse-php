<?php
/**
 * Mimeparse class. Provides basic functions for handling mime-types. It can
 * match mime-types against a list of media-ranges. See section 14.1 of the
 * HTTP specification [RFC 2616] for a complete explanation.
 *
 * It's a PHP port of the original Python code
 * (http://code.google.com/p/mimeparse/).
 *
 * Ported from version 0.1.2. Comments are mostly excerpted from the original.
 *
 * @author Joe Gregorio
 * @author Andrew "Venom" K.
 * @author Ben Ramsey
 */
namespace Bitworking;

class Mimeparse
{
    /**
     * Parses a media-range and returns an array with its components.
     *
     * The returned array contains:
     *
     * 1. type: The type categorization.
     * 2. subtype: The subtype categorization.
     * 3. params: An associative array of all the parameters for the
     *    media-range.
     * 4. generic subtype: A more generic subtype, if one is present. See
     *    http://tools.ietf.org/html/rfc3023#appendix-A.12
     *
     * For example, the media-range "application/xhtml+xml;q=0.5" would get
     * parsed into:
     *
     * array("application", "xhtml+xml", array( "q" => "0.5" ), "xml")
     *
     * @param string $mediaRange
     * @return array ($type, $subtype, $params, $genericSubtype)
     * @throws UnexpectedValueException when $mediaRange does not include a
     * valid subtype
     */
    public static function parseMediaRange($mediaRange)
    {
        $parts = explode(';', $mediaRange);

        $params = array();
        foreach ($parts as $i => $param) {
            if (strpos($param, '=') !== false) {
                list($k, $v) = explode('=', trim($param));
                $params[$k] = $v;
            }
        }

        $fullType = trim($parts[0]);

        // Java URLConnection class sends an Accept header that includes a
        // single "*". Turn it into a legal wildcard.
        if ($fullType == '*') {
            $fullType = '*/*';
        }

        list($type, $subtype) = explode('/', $fullType);

        if (!$subtype) {
            throw new \UnexpectedValueException('Malformed media-range: '.$mediaRange);
        }

        $plusPos = strpos($subtype, '+');
        if (false !== $plusPos) {
            $genericSubtype = substr($subtype, $plusPos + 1);
        } else {
            $genericSubtype = $subtype;
        }

        return array(trim($type), trim($subtype), $params, $genericSubtype);
    }


    /**
     * Parses a media-range via Mimeparse::parseMediaRange() and guarantees that
     * there is a value for the "q" param, filling it in with a proper default
     * if necessary.
     *
     * @param string $mediaRange
     * @return array ($type, $subtype, $params, $genericSubtype)
     */
    protected static function parseAndNormalizeMediaRange($mediaRange)
    {
        $parsedMediaRange = self::parseMediaRange($mediaRange);
        $params = $parsedMediaRange[2];

        if (!isset($params['q'])
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
     * Mimeparse::parseAndNormalizeMediaRange()
     *
     * Returns the fitness and the "q" quality parameter of the best match, or
     * an array [-1, 0] if no match was found. Just as for
     * Mimeparse::quality(), $parsedRanges must be an array of parsed
     * media-ranges.
     *
     * @param string $mimeType
     * @param array  $parsedRanges
     * @return array ($bestFitQuality, $bestFitness)
     */
    protected static function qualityAndFitnessParsed($mimeType, $parsedRanges)
    {
        $bestFitness = -1;
        $bestFitQuality = 0;
        list($targetType, $targetSubtype, $targetParams) = self::parseAndNormalizeMediaRange($mimeType);

        foreach ($parsedRanges as $item) {
            list($type, $subtype, $params) = $item;

            if (($type == $targetType || $type == '*' || $targetType == '*')
                && ($subtype == $targetSubtype || $subtype == '*' || $targetSubtype == '*')) {

                $paramMatches = 0;
                foreach ($targetParams as $k => $v) {
                    if ($k != 'q' && isset($params[$k]) && $v == $params[$k]) {
                        $paramMatches++;
                    }
                }

                $fitness  = ($type == $targetType && $targetType != '*') ? 100 : 0;
                $fitness += ($subtype == $targetSubtype && $targetSubtype != '*') ? 10 : 0;
                $fitness += $paramMatches;

                if ($fitness > $bestFitness) {
                    $bestFitness = $fitness;
                    $bestFitQuality = $params['q'];
                }
            }
        }

        return array((float) $bestFitQuality, $bestFitness);
    }

    /**
     * Find the best match for a given mime-type against a list of
     * media-ranges that have already been parsed by
     * Mimeparse::parseAndNormalizeMediaRange()
     *
     * Returns the "q" quality parameter of the best match, 0 if no match was
     * found. This function behaves the same as Mimeparse::quality() except
     * that $parsedRanges must be an array of parsed media-ranges.
     *
     * @param string $mimeType
     * @param array  $parsedRanges
     * @return float $q
     */
    protected static function qualityParsed($mimeType, $parsedRanges)
    {
        list($q, $fitness) = self::qualityAndFitnessParsed($mimeType, $parsedRanges);
        return $q;
    }

    /**
     * Returns the quality "q" of a mime-type when compared against the
     * media-ranges in ranges. For example:
     *
     * Mimeparse::quality("text/html", "text/*;q=0.3, text/html;q=0.7,
     * text/html;level=1, text/html;level=2;q=0.4, *\/*;q=0.5")
     * => 0.7
     *
     * @param string $mimeType
     * @param string $ranges
     * @return float
     */
    public static function quality($mimeType, $ranges)
    {
        $parsedRanges = explode(',', $ranges);

        foreach ($parsedRanges as $i => $r) {
            $parsedRanges[$i] = self::parseAndNormalizeMediaRange($r);
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
     * Mimeparse::bestMatch(array("application/xbel+xml", "text/xml"), "text/*;q=0.5,*\/*; q=0.1")
     * => "text/xml"
     *
     * @param  array  $supported
     * @param  string $header
     * @return mixed  $mimeType or NULL
     */
    public static function bestMatch($supported, $header)
    {
        $parsedHeader = explode(',', $header);

        foreach ($parsedHeader as $i => $r) {
            $parsedHeader[$i] = self::parseAndNormalizeMediaRange($r);
        }

        $weightedMatches = array();
        foreach ($supported as $index => $mimeType) {
            list($quality, $fitness) = self::qualityAndFitnessParsed($mimeType, $parsedHeader);
            if (!empty($quality)) {
                // Mime-types closer to the beginning of the array are 
                // preferred. This preference score is used to break ties.
                $preference = 0 - $index;
                $weightedMatches[] = array(
                    array($quality, $fitness, $preference),
                    $mimeType
                );
            }
        }

        // Note that since fitness and preference are present in 
        // $weightedMatches they will also be used when sorting (after quality 
        // level).
        array_multisort($weightedMatches);
        $firstChoice = array_pop($weightedMatches);

        return (empty($firstChoice[0][0]) ? null : $firstChoice[1]);
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

