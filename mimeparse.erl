%%% @author Steve Vinoski <vinoski@ieee.org> [http://steve.vinoski.net/]
%%% @doc MIME-Type Parser based on Joe Gregorio's mimeparse.py Python module. This module
%%%      provides basic functions for handling mime-types. It can handle matching mime-types
%%%      against a list of media-ranges. Comments are mostly excerpted from the original.
%%% @reference See <a href="http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1">
%%%            RFC 2616, section 14.1</a> for a complete explanation of mime-type handling.
%%% @reference <a href="http://code.google.com/p/mimeparse/">mimeparse</a>

-module(mimeparse).
-author('vinoski@ieee.org').
-export([parse_mime_type/1, parse_media_range/1, quality/2, best_match/2]).
-export([test/0]).

%%% @type mime_type() = string().
%%% @type media_range() = mime_type().
%%% @type type() = atom().
%%% @type subtype() = atom().
%%% @type value() = string().
%%% @type param() = {atom(), value()}.
%%% @type mime_tuple() = {type(), subtype(), [param()]}.
%%% @type parsed_ranges() = [mime_tuple()].


%% @spec parse_mime_type(Mime_type::mime_type()) -> mime_tuple()
%% @doc Parses a mime-type into its component parts.
%%      Returns a tuple of the {type, subtype, params} where 'params' is a proplist
%%      of all the parameters for the mime-type. For example, the mime-type
%%      "application/xhtml;q=0.5" would get parsed into:
%%
%%         {application, xhtml, [{q, "0.5"}]}
%%
parse_mime_type(Mime_type) ->
    [Full_type | Parts] = string:tokens(string:strip(Mime_type), ";"),
    Params = lists:map(fun({K}) ->
                               {list_to_atom(K), ""};
                          ({K, V}) ->
                               {list_to_atom(K), V}
                       end, [list_to_tuple([string:strip(S) || S <- string:tokens(Param, "=")])
                             || Param <- Parts]),
    [Type, Subtype] = case Full_type of
                          % Java URLConnection class sends an Accept header that includes a single "*"
                          % Turn it into a legal wildcard.
                          "*" ->
                              ['*', '*'];
                          _ ->
                              [list_to_atom(string:strip(S)) || S <- string:tokens(Full_type, "/")]
                      end,
    {Type, Subtype, Params}.

%% @spec parse_media_range(Range::media_range()) -> mime_tuple()
%% @doc Parses a media-range into its component parts.
%%      Media-ranges are mime-types with wildcards and a 'q' quality parameter. This
%%      function performs the same as parse_mime_type/1 except that it also
%%      guarantees that there is a value for 'q' in the params dictionary, filling it
%%      in with a proper default if necessary.
%% @see parse_mime_type/1
%%
parse_media_range(Range) ->
    {Type, Subtype, Params} = parse_mime_type(Range),
    Default_q = {q, "1"},
    New_q = case lists:keysearch(q, 1, Params) of
                false ->
                    Default_q;
                {value, {q, Value}} ->
                    New_value = case Value of
                                    [$.|_] ->
                                        string:concat("0", Value);
                                    _ ->
                                        Value
                                end,
                    case number_to_float(New_value) of
                        {error, _} ->
                            Default_q;
                        Float ->
                            if
                                Float > 1.0;
                                Float < 0.0 ->
                                    Default_q;
                                true ->
                                    {q, New_value}
                            end
                    end
            end,
    {Type, Subtype, lists:keystore(q, 1, Params, New_q)}.

%% @spec quality(Mime_type::mime_type(), Range::media_range()) -> float()
%% @doc Determines the quality ('q') of a mime-type when compared against a media-range.
%%      Returns the quality 'q' of a mime_type when compared against the media-ranges
%%      in ranges. For example, given this media-range:
%%
%%      "text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5"
%%
%%      this function returns 0.7.
%%
quality(Mime_type, Range) ->
    {_, Q} = quality_parsed(Mime_type, [parse_media_range(R) || R <- string:tokens(Range, ",")]),
    Q.

%% @spec best_match(Supported_mime_types::[mime_type()], Header::media_range()) -> mime_type()
%% @doc Choose the mime-type with the highest quality ('q') from a list of candidates.
%%      Takes a list of supported mime-types and finds the best match for all the
%%      media-ranges listed in Header. The value of Header must be a string that
%%      conforms to the format of the HTTP Accept: header. The value of
%%      Supported_mime_types is a list of mime-types.
%%
%%       best_match(["application/xbel+xml", "text/xml"], "text/*;q=0.5,*/*; q=0.1").
%%
%%      returns "text/xml".
%%
best_match(Supported_mime_types, Header) ->
    Parsed_header = [parse_media_range(R) || R <- string:tokens(Header, ",")],
    Best_match = lists:foldl(fun(Mime_type, {{Best_fit, Best_q}, _}=Best_so_far) ->
                                     Score = {Fitness, Q} = quality_parsed(Mime_type, Parsed_header),
                                     if
                                         Fitness >= Best_fit andalso Q >= Best_q ->
                                             {Score, Mime_type};
                                         true ->
                                             Best_so_far
                                     end
                             end, {{-1, 0.0}, ""}, Supported_mime_types),
    case Best_match of
        {{_, 0.0}, _} ->
            "";
        {_Score, Mime_type} ->
            Mime_type
    end.

%% Internal functions.

quality_parsed(Mime_type, Parsed_ranges) ->
    {Target_type, Target_subtype, Target_params} = parse_media_range(Mime_type),
    lists:foldl(
      fun({Type, Subtype, Params}, {Best_fitness, _}=Best_so_far) ->
              case possible_best_fit(Target_type, Type, Target_subtype, Subtype) of
                  true ->
                      Type_fitness = type_fitness(Target_type, Type, 100),
                      Subtype_fitness = type_fitness(Target_subtype, Subtype, 10),
                      Param_matches = count_param_matches(Target_params, Params),
                      Fitness = Type_fitness + Subtype_fitness + Param_matches,
                      if
                          Fitness > Best_fitness ->
                              {Fitness, number_to_float(proplists:get_value(q, Params))};
                          true ->
                              Best_so_far
                      end;
                  _ ->
                      Best_so_far
              end
      end, {-1, 0.0}, Parsed_ranges).

count_param_matches(Target_params, Params) ->
    lists:foldl(fun({q,_}, Acc) ->
                        Acc;
                   ({Key,Value}, Acc) ->
                        case lists:keysearch(Key, 1, Params) of
                            false ->
                                Acc;
                            {value, {Key, Value}} ->
                                1 + Acc;
                            _ ->
                                Acc
                        end
                end, 0, Target_params).

possible_best_fit(Target_type, Type, Target_subtype, Subtype) ->
    (lists:member(Type, ['*', Target_type]) orelse Target_type =:= '*')
        andalso
          (lists:member(Subtype, ['*', Target_subtype]) orelse
           Target_subtype =:= '*').

type_fitness(Target_type, Type, Value) ->
    case Type of
        Target_type -> Value;
        _ -> 0
    end.

number_to_float(String) ->
    case string:to_float(String) of
        {error, _}=Error ->
            case string:to_integer(String) of
                {error, _} ->
                    Error;
                {Num, _} ->
                    float(Num)
            end;
        {Float, _} ->
            Float
    end.

%% Tests.

test_parse_media_range() ->
    {application, xml, [{q, "1"}]} = mimeparse:parse_media_range("application/xml;q=1"),
    {application, xml, [{q, "1"}]} = mimeparse:parse_media_range("application/xml"),
    {application, xml, [{q, "1"}]} = mimeparse:parse_media_range("application/xml;q="),
    {application, xml, [{q, "1"}]} = mimeparse:parse_media_range("application/xml ; q="),
    {application, xml, [{q, "1"}, {b, "other"}]} =
        mimeparse:parse_media_range("application/xml ; q=1;b=other"),
    {application, xml, [{q, "1"}, {b, "other"}]} =
        mimeparse:parse_media_range("application/xml ; q=2;b=other"),
    % Java URLConnection class sends an Accept header with a single *
    {'*', '*', [{q, "0.2"}]} = mimeparse:parse_media_range(" *; q=.2"),
    ok.

test_rfc_2616_example() ->
    Accept = "text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5",
    1.0 = mimeparse:quality("text/html;level=1", Accept),
    0.7 = mimeparse:quality("text/html", Accept),
    0.3 = mimeparse:quality("text/plain", Accept),
    0.5 = mimeparse:quality("image/jpeg", Accept),
    0.4 = mimeparse:quality("text/html;level=2", Accept),
    0.7 = mimeparse:quality("text/html;level=3", Accept),
    ok.

test_best_match() ->
    Mime_types_supported1 = ["application/xbel+xml", "application/xml"],
    % direct match
    "application/xbel+xml" = mimeparse:best_match(Mime_types_supported1, "application/xbel+xml"),
    % direct match with a q parameter
    "application/xbel+xml" = mimeparse:best_match(Mime_types_supported1, "application/xbel+xml; q=1"),
    % direct match of our second choice with a q parameter
    "application/xml" = mimeparse:best_match(Mime_types_supported1, "application/xml; q=1"),
    % match using a subtype wildcard
    "application/xml" = mimeparse:best_match(Mime_types_supported1, "application/*; q=1"),
    % match using a type wildcard
    "application/xml" = mimeparse:best_match(Mime_types_supported1, "*/*"),

    Mime_types_supported2 = ["application/xbel+xml", "text/xml"],
    % match using a type versus a lower weighted subtype
    "text/xml" = mimeparse:best_match(Mime_types_supported2, "text/*;q=0.5,*/*; q=0.1"),
    % fail to match anything
    "" = mimeparse:best_match(Mime_types_supported2, "text/html,application/atom+xml; q=0.9"),

    % common AJAX scenario
    Mime_types_supported3 = ["application/json", "text/html"],
    "application/json" = mimeparse:best_match(Mime_types_supported3, "application/json, text/javascript, */*"),
    % verify fitness ordering
    "application/json" = mimeparse:best_match(Mime_types_supported3, "application/json, text/html;q=0.9"),

    ok.

test_support_wildcards() ->
    Mime_types_supported = ["image/*", "application/xml"],
    % match using a type wildcard
    "image/*" = mimeparse:best_match(Mime_types_supported, "image/png"),
    % match using a wildcard for both requested and supported
    "image/*" = mimeparse:best_match(Mime_types_supported, "image/*"),
    ok.

test() ->
    test_parse_media_range(),
    test_rfc_2616_example(),
    test_best_match(),
    test_support_wildcards().
