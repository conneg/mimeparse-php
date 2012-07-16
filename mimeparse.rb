# mimeparse.rb
#
# This module provides basic functions for handling mime-types. It can
# handle matching mime-types against a list of media-ranges. See section
# 14.1 of the HTTP specification [RFC 2616] for a complete explanation.
#
#   http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
#
# ---------
#
# This is a port of Joe Gregario's mimeparse.py, which can be found at 
#   <http://code.google.com/p/mimeparse/>.
#
# ported from version 0.1.2
#
# Comments are mostly excerpted from the original.

module MIMEParse
  module_function

# Carves up a mime-type and returns an Array of the
#  [type, subtype, params] where "params" is a Hash of all
#  the parameters for the media range.
#
# For example, the media range "application/xhtml;q=0.5" would
#  get parsed into:
#
# ["application", "xhtml", { "q" => "0.5" }]
def parse_mime_type(mime_type)
  parts = mime_type.split(";")

  params = {}

  parts[1..-1].map do |param|
    k,v = param.split("=").map { |s| s.strip }
    params[k] = v
  end

  full_type = parts[0].strip
  # Java URLConnection class sends an Accept header that includes a single "*"
  # Turn it into a legal wildcard.
  full_type = "*/*" if full_type == "*"
  type, subtype = full_type.split("/")
  raise "malformed mime type" unless subtype

  [type.strip, subtype.strip, params]
end

# Carves up a media range and returns an Array of the
#  [type, subtype, params] where "params" is a Hash of all
#  the parameters for the media range.
#
# For example, the media range "application/*;q=0.5" would
#  get parsed into:
#
# ["application", "*", { "q", "0.5" }]
#
# In addition this function also guarantees that there
#  is a value for "q" in the params dictionary, filling it
#  in with a proper default if necessary.
def parse_media_range(range)
  type, subtype, params = parse_mime_type(range)
  unless params.has_key?("q") and params["q"] and params["q"].to_f and params["q"].to_f <= 1 and params["q"].to_f >= 0
    params["q"] = "1"
  end

  [type, subtype, params]
end

# Find the best match for a given mime-type against a list of
#  media_ranges that have already been parsed by #parse_media_range
#
# Returns the fitness and the "q" quality parameter of the best match,
#  or [-1, 0] if no match was found. Just as for #quality_parsed,
#  "parsed_ranges" must be an Enumerable of parsed media ranges.
def fitness_and_quality_parsed(mime_type, parsed_ranges)
  best_fitness = -1
  best_fit_q = 0
  target_type, target_subtype, target_params = parse_media_range(mime_type)

  parsed_ranges.each do |type,subtype,params|
    if (type == target_type or type == "*" or target_type == "*") and
        (subtype == target_subtype or subtype == "*" or target_subtype == "*")
      param_matches = target_params.find_all { |k,v| k != "q" and params.has_key?(k) and v == params[k] }.length

      fitness = (type == target_type) ? 100 : 0
      fitness += (subtype == target_subtype) ? 10 : 0
      fitness += param_matches

      if fitness > best_fitness
        best_fitness = fitness
        best_fit_q = params["q"]
      end
    end
  end

  [best_fitness, best_fit_q.to_f]
end

# Find the best match for a given mime-type against a list of
#  media_ranges that have already been parsed by #parse_media_range
#
# Returns the "q" quality parameter of the best match, 0 if no match
#  was found. This function behaves the same as #quality except that
#  "parsed_ranges" must be an Enumerable of parsed media ranges.
def quality_parsed(mime_type, parsed_ranges)
  fitness_and_quality_parsed(mime_type, parsed_ranges)[1]
end

# Returns the quality "q" of a mime_type when compared against
#  the media-ranges in ranges. For example:
#
#     irb> quality("text/html", "text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5")
#     => 0.7
def quality(mime_type, ranges)
  parsed_ranges = ranges.split(",").map { |r| parse_media_range(r) }
  quality_parsed(mime_type, parsed_ranges)
end

# Takes a list of supported mime-types and finds the best match
#  for all the media-ranges listed in header. The value of header
#  must be a string that conforms to the format of the HTTP Accept:
#  header. The value of supported is an Enumerable of mime-types
#
#     irb> best_match(["application/xbel+xml", "text/xml"], "text/*;q=0.5,*/*; q=0.1")
#     => "text/xml"
def best_match(supported, header)
  parsed_header = header.split(",").map { |r| parse_media_range(r) }

  weighted_matches = supported.map do |mime_type|
    [fitness_and_quality_parsed(mime_type, parsed_header), mime_type]
  end

  weighted_matches.sort!

  weighted_matches.last[0][1].zero? ? nil : weighted_matches.last[1]
end
end

if __FILE__ == $0
  require "test/unit"

  class TestMimeParsing < Test::Unit::TestCase
    include MIMEParse

    def test_parse_media_range
      assert_equal [ "application", "xml", { "q" => "1" } ],
                    parse_media_range("application/xml;q=1")

      assert_equal [ "application", "xml", { "q" => "1" } ],
                    parse_media_range("application/xml")

      assert_equal [ "application", "xml", { "q" => "1" } ],
                    parse_media_range("application/xml;q=")

      assert_equal [ "application", "xml", { "q" => "1", "b" => "other" } ],
                    parse_media_range("application/xml ; q=1;b=other")

      assert_equal [ "application", "xml", { "q" => "1", "b" => "other" } ],
                    parse_media_range("application/xml ; q=2;b=other")

      # Java URLConnection class sends an Accept header that includes a single "*"
      assert_equal [ "*", "*", { "q" => ".2" } ],
                    parse_media_range(" *; q=.2")
    end

    def test_rfc_2616_example
      accept = "text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5"

      assert_equal 1, quality("text/html;level=1", accept)
      assert_equal 0.7, quality("text/html", accept)
      assert_equal 0.3, quality("text/plain", accept)
      assert_equal 0.5, quality("image/jpeg", accept)
      assert_equal 0.4, quality("text/html;level=2", accept)
      assert_equal 0.7, quality("text/html;level=3", accept)
    end

    def test_best_match
      @supported_mime_types = [ "application/xbel+xml", "application/xml" ]

      # direct match
      assert_best_match "application/xbel+xml", "application/xbel+xml"
      # direct match with a q parameter
      assert_best_match "application/xbel+xml", "application/xbel+xml; q=1"
      # direct match of our second choice with a q parameter
      assert_best_match "application/xml", "application/xml; q=1"
      # match using a subtype wildcard
      assert_best_match "application/xml", "application/*; q=1"
      # match using a type wildcard
      assert_best_match "application/xml", "*/*"

      @supported_mime_types = [ "application/xbel+xml", "text/xml" ]
      # match using a type versus a lower weighted subtype
      assert_best_match "text/xml", "text/*;q=0.5,*/*;q=0.1"
      # fail to match anything
      assert_best_match nil, "text/html,application/atom+xml; q=0.9"
      # common AJAX scenario
      @supported_mime_types = [ "application/json", "text/html" ]
      assert_best_match "application/json", "application/json, text/javascript, */*"
      # verify fitness sorting
      assert_best_match "application/json", "application/json, text/html;q=0.9"
    end

    def test_support_wildcards
      @supported_mime_types = ['image/*', 'application/xml']
      # match using a type wildcard
      assert_best_match 'image/*', 'image/png'
      # match using a wildcard for both requested and supported
      assert_best_match 'image/*', 'image/*'
    end

    def assert_best_match(expected, header)
      assert_equal(expected, best_match(@supported_mime_types, header))
    end
  end
end
