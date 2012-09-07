<?php
/**
 * Mimeparse class. This class provides basic functions for handling mime-types. It can
 * handle matching mime-types against a list of media-ranges. See section
 * 14.1 of the HTTP specification [RFC 2616] for a complete explanation.
 *
 * It's just a port to php from original Python code (http://code.google.com/p/mimeparse/).
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
     * Parses a mime-type and returns an array with its components.
     *
     * The array returned contains:
     *
     * 1. type: The type categorization.
     * 2. subtype: The subtype categorization.
     * 3. params: A hash of all the parameters for the media range.
     * 4. format: The content format.
     *
     * For example, the media range "application/xhtml+xml;q=0.5" would
     * get parsed into:
     *
     * array("application", "xhtml", array( "q" => "0.5" ), "xml")
     *
     * @param string $mimeType
     * @return array ($type, $subtype, $params)
     * @throws UnexpectedValueException when $mimeType does not include a valid subtype
     */
    public static function parseMimeType($mimeType)
    {
        $parts = explode(';', $mimeType);

        $params = array();
        foreach ($parts as $i => $param) {
            if (strpos($param, '=') !== false) {
                list($k, $v) = explode('=', trim($param));
                $params[$k] = $v;
            }
        }

        $fullType = trim($parts[0]);

        // Java URLConnection class sends an Accept header that includes a single "*"
        // Turn it into a legal wildcard.
        if ($fullType == '*') {
            $fullType = '*/*';
        }

        list($type, $subtype) = explode('/', $fullType);

        if (!$subtype) {
            throw new \UnexpectedValueException('malformed mime type');
        }

        if (false !== strpos($subtype, '+')) {
            // don't rewrite subtype to prevent compatibility issues
            list(/*$subtype*/, $format) = explode('+', $subtype, 2);
        } else {
            $format = $subtype;
        }

        return array(trim($type), trim($subtype), $params, $format);
    }


    /**
     * Carves up a media range and returns an Array of the
     * [type, subtype, params] where "params" is a Hash of all
     * the parameters for the media range.
     *
     * For example, the media range "application/*;q=0.5" would
     * get parsed into:
     *
     * array("application", "*", ( "q", "0.5" ))
     *
     * In addition this function also guarantees that there
     * is a value for "q" in the params dictionary, filling it
     * in with a proper default if necessary.
     *
     * @param string $range
     * @return array ($type, $subtype, $params)
     */
    protected static function parseMediaRange($range)
    {
        list($type, $subtype, $params) = self::parseMimeType($range);

        if (!isset($params['q'])
            || !is_numeric($params['q'])
            || floatval($params['q']) > 1
            || floatval($params['q']) < 0
        ) {
            $params['q'] = '1';
        }

        return array($type, $subtype, $params);
    }

    /**
     * Find the best match for a given mime-type against a list of
     * media-ranges that have already been parsed by Mimeparse::parseMediaRange()
     *
     * Returns the fitness and the "q" quality parameter of the best match, or an
     * array [-1, 0] if no match was found. Just as for Mimeparse::quality(),
     * $parsedRanges must be an Enumerable of parsed media-ranges.
     *
     * @param string $mimeType
     * @param array  $parsedRanges
     * @return array ($bestFitness, $bestFitQuality)
     */
    protected static function fitnessAndQualityParsed($mimeType, $parsedRanges)
    {
        $bestFitness = -1;
        $bestFitQuality = 0;
        list($targetType, $targetSubtype, $targetParams) = self::parseMediaRange($mimeType);

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

                $fitness  = ($type == $targetType) ? 100 : 0;
                $fitness += ($subtype == $targetSubtype) ? 10 : 0;
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
     * media-ranges that have already been parsed by Mimeparse::parseMediaRange()
     *
     * Returns the "q" quality parameter of the best match, 0 if no match
     * was found. This function behaves the same as Mimeparse::quality() except that
     * $parsedRanges must be an Enumerable of parsed media-ranges.
     *
     * @param string $mimeType
     * @param array  $parsedRanges
     * @return float $q
     */
    protected static function qualityParsed($mimeType, $parsedRanges)
    {
        list($q, $fitness) = self::fitnessAndQualityParsed($mimeType, $parsedRanges);
        return $q;
    }

    /**
     * Returns the quality "q" of a mime-type when compared against
     * the media-ranges in ranges. For example:
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
            $parsedRanges[$i] = self::parseMediaRange($r);
        }

        return self::qualityParsed($mimeType, $parsedRanges);
    }

    /**
     * Takes a list of supported mime-types and finds the best match
     * for all the media-ranges listed in header. The value of header
     * must be a string that conforms to the format of the HTTP Accept:
     * header. The value of supported is an Enumerable of mime-types
     *
     * Mimeparse::bestMatch(array("application/xbel+xml", "text/xml"), "text/*;q=0.5,*\/*; q=0.1")
     * => "text/xml"
     *
     * @param  array  $supported
     * @param  string $header
     * @param  string $tieBreaker In case of a tie, this mime-type is preferred
     * @return mixed  $mimeType or NULL
     */
    public static function bestMatch($supported, $header, $tieBreaker = null)
    {
        $parsedHeader = explode(',', $header);

        foreach ($parsedHeader as $i => $r) {
            $parsedHeader[$i] = self::parseMediaRange($r);
        }

        $weightedMatches = array();
        foreach ($supported as $mimeType) {
            $weightedMatches[] = array(
                self::fitnessAndQualityParsed($mimeType, $parsedHeader),
                $mimeType
            );
        }

        // If the best fit quality is 0 for anything, then it is
        // not acceptable for the client; remove it from the list
        // of weighted matches.
        $unacceptableTypes = array();
        foreach ($weightedMatches as $k => $v) {
            if (empty($v[0][0])) {
                $unacceptableTypes[] = $k;
            }
        }
        foreach ($unacceptableTypes as $weightedMatchKey) {
            unset($weightedMatches[$weightedMatchKey]);
        }

        array_multisort($weightedMatches);
        $a = array_pop($weightedMatches);

        // If there's a tie breaker specified, see if we have any ties
        // and then break them with the $tieBreaker
        if ($tieBreaker) {
            array_push($weightedMatches, $a);
            $ties = array_filter($weightedMatches, function ($val) use ($a) {
                return ($val[0] == $a[0]);
            });
            if (count($ties) > 1) {
                return $tieBreaker;
            }
        }

        return (empty($a[0][0]) ? null : $a[1]);
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

