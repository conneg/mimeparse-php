<?php
/**
 * Mimeparser class. This class provides basic functions for handling mime-types. It can
 * handle matching mime-types against a list of media-ranges. See section
 * 14.1 of the HTTP specification [RFC 2616] for a complete explanation. 
 * 
 * It's just a port to php from original
 * Python code (http://code.google.com/p/mimeparse/)
 * @author Joe Gregario, Andrew "Venom" K.
 * 
 * ported from version 0.1.2
 * 
 * Comments are mostly excerpted from the original.
 */
class Mimeparse {        
    /**
     * Carves up a mime-type and returns an Array of the [type, subtype, params] 
     * where "params" is a Hash of all the parameters for the media range.
     *
     * For example, the media range "application/xhtml;q=0.5" would
     * get parsed into:
     *
     * array("application", "xhtml", array( "q" => "0.5" )) 
     *
     * @param string $mime_type
     * @return array ($type, $subtype, $params)
     */
    public function parse_mime_type($mime_type) {
      $parts = split(";", $mime_type);
      
      $params = array();
      foreach ($parts as $i=>$param) {
        if (strpos($param, '=') !== false) {
            list ($k, $v) = explode('=', trim($param));
            $params[$k] = $v;
        }
      }

      $full_type = trim($parts[0]);
      /* Java URLConnection class sends an Accept header that includes a single "*"
         Turn it into a legal wildcard. */
      if ($full_type == '*') {
          $full_type = '*/*';
      }
      list ($type, $subtype) = explode('/', $full_type);
      if (!$subtype) throw (new Exception("malformed mime type"));
    
      return array(trim($type), trim($subtype), $params);
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
    public function parse_media_range($range) {
      list ($type, $subtype, $params) = $this->parse_mime_type($range);
      
      if (!(isset($params['q']) && $params['q'] && floatval($params['q']) &&
        floatval($params['q']) <= 1 && floatval($params['q']) >= 0))
            $params['q'] = '1';
      
      return array($type, $subtype, $params);
    }
    
    /**
     * Find the best match for a given mime-type against a list of
     * media_ranges that have already been parsed by Mimeparser::parse_media_range()
     *
     * Returns the fitness and the "q" quality parameter of the best match, or an
     * array [-1, 0] if no match was found. Just as for Mimeparser::quality(),
     * "parsed_ranges" must be an Enumerable of parsed media ranges. 
     *
     * @param string $mime_type
     * @param array  $parsed_ranges
     * @return array ($best_fitness, $best_fit_q)
     */
    public function fitness_and_quality_parsed($mime_type, $parsed_ranges) {
      $best_fitness = -1;
      $best_fit_q   = 0;
      list ($target_type, $target_subtype, $target_params) = $this->parse_media_range($mime_type);
    
      foreach ($parsed_ranges as $item) {
        list ($type, $subtype, $params) = $item;

        if (($type == $target_type or $type == "*" or $target_type == "*") &&
            ($subtype == $target_subtype or $subtype == "*" or $target_subtype == "*")) {
    
          $param_matches = 0;
          foreach ($target_params as $k=>$v) {
            if ($k != 'q' && isset($params[$k]) && $v == $params[$k])
              $param_matches++;
          }
        
          $fitness  = ($type == $target_type) ? 100 : 0;
          $fitness += ($subtype == $target_subtype) ? 10 : 0;
          $fitness += $param_matches;
    
          if ($fitness > $best_fitness) {
            $best_fitness = $fitness;
            $best_fit_q   = $params['q'];
          }
        }
      }
    
      return array( $best_fitness, (float) $best_fit_q );
    }
    
    /**
     * Find the best match for a given mime-type against a list of
     * media_ranges that have already been parsed by Mimeparser::parse_media_range()
     *
     * Returns the "q" quality parameter of the best match, 0 if no match
     * was found. This function behaves the same as Mimeparser::quality() except that
     * "parsed_ranges" must be an Enumerable of parsed media ranges. 
     *
     * @param string $mime_type
     * @param array  $parsed_ranges
     * @return float $q
     */
    public function quality_parsed($mime_type, $parsed_ranges) {
      list ($fitness, $q) = $this->fitness_and_quality_parsed($mime_type, $parsed_ranges);
      return $q;
    }
    
    /**
     * Returns the quality "q" of a mime-type when compared against
     * the media-ranges in ranges. For example:
     *
     * Mimeparser::quality("text/html", "text/*;q=0.3, text/html;q=0.7, 
     * text/html;level=1, text/html;level=2;q=0.4, *\/*;q=0.5")
     * => 0.7 
     *
     * @param unknown_type $mime_type
     * @param unknown_type $ranges
     * @return unknown
     */
    public function quality($mime_type, $ranges) {
      $parsed_ranges = explode(',', $ranges);
      
      foreach ($parsed_ranges as $i=>$r)
          $parsed_ranges[ $i ] = $this->parse_media_range($r);
      
      return $this->quality_parsed($mime_type, $parsed_ranges);
    }
    
    /**
     * Takes a list of supported mime-types and finds the best match
     * for all the media-ranges listed in header. The value of header
     * must be a string that conforms to the format of the HTTP Accept:
     * header. The value of supported is an Enumerable of mime-types
     *
     * Mimeparser::best_match(array("application/xbel+xml", "text/xml"), "text/*;q=0.5,*\/*; q=0.1")
     * => "text/xml"
     *
     * @param  array  $supported
     * @param  string $header
     * @return mixed  $mime_type or NULL 
     */
    public function best_match($supported, $header) {
      $parsed_header = explode(',', $header);
      
      foreach ($parsed_header as $i=>$r)
          $parsed_header[ $i ] = $this->parse_media_range($r);
        
      $weighted_matches = array();
      foreach ($supported as $mime_type) {
          $weighted_matches[] = array(
            $this->fitness_and_quality_parsed($mime_type, $parsed_header),
            $mime_type
          );
      }
    
      array_multisort($weighted_matches);

      $a = $weighted_matches[ count($weighted_matches) - 1 ];
      return ( empty( $a[0][1] ) ? null :  $a[1] );
    }
}




// Unit tests //////////////////////////////////////////////////////////////////////////////////////////////////////////


$m = new Mimeparse;
if ($m->parse_media_range("application/xml;q=1") === array(0 => "application", 1=> "xml", 2=> array("q" => "1") ))
    echo "application/xml;q=1 - OK<br>";
else 
    echo "application/xml;q=1 - FAIL<br>";
    
if ($m->parse_media_range("application/xml") === array(0 => "application", 1=> "xml", 2=> array("q" => "1") ))
    echo "application/xml - OK<br>";
else 
    echo "application/xml - FAIL<br>";
    
if ($m->parse_media_range("application/xml;q=") === array(0 => "application", 1=> "xml", 2=> array("q" => "1") ))
    echo "application/xml;q= - OK<br>";
else 
    echo "application/xml;q= - FAIL<br>";
    

if ($m->parse_media_range("application/xml ; q=1;b=other") === array(0 => "application", 1=> "xml", 2=> array("q" => "1", "b" => "other") ))
    echo "application/xml ; q=1;b=other - OK<br>";
else 
    echo "application/xml ; q=1;b=other - FAIL<br>";
    
if ($m->parse_media_range("application/xml ; q=2;b=other") === array(0 => "application", 1=> "xml", 2=> array("q" => "1", "b" => "other") ))
    echo "application/xml ; q=2;b=other - OK<br>";
else 
    echo "application/xml ; q=2;b=other - FAIL<br>";
    
/* Java URLConnection class sends an Accept header that includes a single "*" */
if ($m->parse_media_range(" *; q=.2") === array(0 => "*", 1=> "*", 2=> array("q" => ".2") ))
    echo " *; q=.2 - OK<br>";
else 
    echo " *; q=.2 - FAIL<br>";
    
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$accept = "text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5";

echo (1 == $m->quality("text/html;level=1", $accept) ? 'text/html;level=1 - OK<br>' : 'text/html;level=1 - FAIL<br>');
echo (0.7 == $m->quality("text/html", $accept) ? 'text/html - OK<br>' : 'text/html - FAIL<br>');
echo (0.3 == $m->quality("text/plain", $accept) ? 'text/plain - OK<br>' : 'text/plain - FAIL<br>');
echo (0.5 == $m->quality("image/jpeg", $accept) ? 'image/jpeg - OK<br>' : 'image/jpeg - FAIL<br>');
echo (0.4 == $m->quality("text/html;level=2", $accept) ? 'text/html;level=2 - OK<br>' : 'text/html;level=2 - FAIL<br>');
echo (0.7 == $m->quality("text/html;level=3", $accept) ? 'text/html;level=3 - OK<br>' : 'text/html;level=3 - FAIL<br>');
 
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$supported_mime_types = array("application/xbel+xml", "application/xml");

# direct match
assert_best_match ("application/xbel+xml", "application/xbel+xml");
# direct match with a q parameter
assert_best_match ("application/xbel+xml", "application/xbel+xml; q=1");
# direct match of our second choice with a q parameter
assert_best_match ("application/xml", "application/xml; q=1");
# match using a subtype wildcard
assert_best_match ("application/xml", "application/*; q=1");
# match using a type wildcard
assert_best_match ("application/xml", "* / *");

$supported_mime_types = array( "application/xbel+xml", "text/xml" );
# match using a type versus a lower weighted subtype
assert_best_match ("text/xml", "text/ *;q=0.5,* / *;q=0.1");
# fail to match anything
assert_best_match (null, "text/html,application/atom+xml; q=0.9" );
# common AJAX scenario
$supported_mime_types = array( "application/json", "text/html" );
assert_best_match("application/json", "application/json, text/javascript, */*");
# verify fitness sorting
$supported_mime_types = array( "application/json", "text/html" );
assert_best_match("application/json", "application/json, text/html;q=0.9");


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$supported_mime_types = array('image/*', 'application/xml');
# match using a type wildcard
assert_best_match ('image/*', 'image/png');
# match using a wildcard for both requested and supported
assert_best_match ('image/*', 'image/*');

function assert_best_match($expected, $header) {
    global $m, $supported_mime_types;

    if ($expected == $m->best_match($supported_mime_types, $header)) 
        echo "$header - OK<br>";
    else 
        echo "$header - FAIL<br>";
}
