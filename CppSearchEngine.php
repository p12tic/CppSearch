<?php
/*
    Copyright 2011, 2012 Povilas Kanapickas <tir5c3@yahoo.co.uk>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class CppSearchEngine extends SearchEngine {

    function searchText($term, $limit = 0, $offset = 0)
    {
        return CppSearchResultSet::new_from_query($term);
    }

    function search_text_group($term, $group)
    {
        return CppSearchResultSet::new_from_query_group($term, $group);
    }

    //Title search not supported
    function searchTitle( $term )
    {
        return null;
    }

    //Near match not supported
    public static function getNearMatch( $searchterm )
    {
        return null;
    }

    //Prefixes not supported
    function replacePrefixes( $query )
    {
        return $query;
    }

    //User namespaces not supported
    public static function userNamespaces( $user )
    {
        return array();
    }

    public static function legalSearchChars()
    {
        return ":" . parent::legalSearchChars();
    }

    function acceptListRedirects() {
        return false;
    }
}

// helper functions
// $lhs, $rhs are of type CppSearchMatchedWord
function _cmp_match($lhs, $rhs)
{
    $lnum = 0;
    foreach($lhs as $w) {
        $lnum = max($lnum, count($w->ids));
    }

    $rnum = 0;
    foreach($rhs as $w) {
        $rnum = max($rnum, count($w->ids));
    }

    return $lnum - $rnum;
}

// $lhs, $rhs are of type CppSearchInternalSearchResult
function _cmp_res($lhs, $rhs)
{
    $res = $lhs->cost - $rhs->cost;
    if ($res != 0) return $res;

    $res = strlen($lhs->key) - strlen($rhs->key);
    if ($res != 0) return $res;

    return strcmp($lhs->key, $rhs->key);
}

/** @private
    Represents parsed keyword info
*/
class CppSearchKeywordInfo {
    // map ( id -> id_info )
    // Maps ids to URL<->KEY pairs
    public $id_map;

    // map ( word -> array of ids )
    // Maps words to a list of entries containing entries that match the word
    public $words;

    // map ( word -> array of ids )
    // Maps words to a list of entries containing entries that match the
    // word if '_' is also considered a word separator.
    public $words_split;

    // The number of entries
    public $num_id;
}

/// @private
class CppSearchIdInfo {
    public $url;
    public $full_key;

    public function __construct($p_url, $p_full_key)
    {
        $this->url = $p_url;
        $this->full_key = $p_full_key;
    }
}

/** @private
    Represents a list of queries that match a word
*/
class CppSearchMatchedWord {
    public $word; // matched word
    public $cost; // cost
    public $ids;  // reference to an array of ids of entries containing
                    // this word

    public function __construct($p_word, $p_cost, &$p_ids)
    {
        $this->word = $p_word;
        $this->cost = $p_cost;
        $this->ids = $p_ids;
    }
}

/** @private
    Represents an internal search result
*/
class CppSearchInternalSearchResult {
    public $cost;
    public $id;
    public $key;
    public $url;

    public function __construct($p_cost, $p_id, $p_key, $p_url)
    {
        $this->cost = $p_cost;
        $this->id = $p_id;
        $this->key = $p_key;
        $this->url = $p_url;
    }
};

/**
 * @ingroup Search
 */
class CppSearchResultSet extends SearchResultSet {

    /**
        Splits a query into keywords that will be used in search
    */
    static function split_words($query, &$words, &$split_words)
    {
        $operator_pattern = '/(operator\s*(?:\(\)|<=|<<=|<<|<|>=|>>=|>>|>|==|=|\!=|\!|\[\]|->\*|->|\+\+|\+=|\+|--|-=|-|~=|~|\*=|\*|&=|&|^=|^|\/=|\/|%=|%|\|\||\|))/';

        $query = strtolower($query);

        $words = array();
        $split_words = array();

        //deal with various operators first
        if (preg_match($operator_pattern, $query, $oper) > 0) {
            //remove spaces
            $oper = preg_replace('/ */','',$oper[0]);
            $words[] = $oper;
            $words[] = 'operator';
            $query = preg_replace($operator_pattern, ' ', $query);
        }

        //split by non-alphanumeric characters
        $query = preg_replace('/[^a-z0-9\_]/', ' ', $query);
        $query = preg_replace('/ +/', ' ', $query);
        $query = trim($query);

        $words = array_merge($words, preg_split('/ /', $query));

        foreach ($words as $w) {
            if ($w == '') continue;
            $w_words = explode('_', $w);
            if (count($w_words) > 1) {
                foreach ($w_words as $ww) {
                    if ($ww == '') continue;
                    $split_words[] = $ww;
                }
            }
        }
    }

    /** Returns parsed keyword data.
    */
    static function get_data($group)
    {
        $data = false;

        $cache = wfGetMainCache();
        $T_DATA = 'CppSearch_data_' . $group;
        $T_TIME = 'CppSearch_time_' . $group;
        $T_MSG = 'cpp-search-list-' . $group;

        //Fetch data from cache. Do so only if the cache is newer than this file
        $mod_time = gmdate('YmdHis', filemtime(__FILE__));

        $cache_time = $cache->get($T_TIME);

        if ($cache_time && $cache_time >= $mod_time) {
            $data = $cache->get($T_DATA);
        }

        if ($data == false) {
            //drop existing cache
            $cache->delete($T_DATA);
            $cache->delete($T_TIME);

            //read the keyword string
            $data = new CppSearchKeywordInfo();
            $id = 0;
            $string = wfMsgGetKey($T_MSG, true, false, false);

            foreach (preg_split("/(\r?\n)/", $string) as $line) {

                $words = explode('=>', $line);
                $words = array_map('trim', $words);

                if (count ($words) != 2) {
                    continue;
                }

                $key = $words[0];
                $url = $words[1];

                if (!isset($key) || !isset($url) || $key == '' || $url == '') {
                    continue;
                }

                //set the data
                $data->id_map[$id] = new CppSearchIdInfo($url, $key);

                //split the keywords
                $key_words = array();
                $split_words = array();

                self::split_words($key, $key_words, $split_words);

                //Map all resulting words to the source entries
                foreach ($key_words as $w) {
                    if ($w == '') continue;
                    $data->words[$w][] = $id;
                }
                //Map all resulting split words to the source entries
                foreach ($split_words as $w) {
                    if ($w == '') continue;
                    $data->words_split[$w][] = $id;
                }

                $id++;
            }
            $data->num_id = $id;

            //update cache
            global $wgCppSearchCacheExpiry;

            $curr_time = gmdate('YmdHis', time());
            $cache->set($T_DATA, $data, $wgCppSearchCacheExpiry);
            $cache->set($T_TIME, $curr_time, $wgCppSearchCacheExpiry);
        }
        return $data;
    }

    static function new_from_query( $query )
    {
        return self::new_from_query_group($query);
    }

    static function new_from_query_group( $query, $group = 'cpp' )
    {
        //check the cache for optimized keyword list
        global $wgCppSearchQueryWordLimit;
        global $wgCppSearchMaxResultCost;
        global $wgCppSearchMaxResults;
        global $wgCppSearchSplitWordCost;
        global $wgCppSearchInsertCost;
        global $wgCppSearchDeleteCost;
        global $wgCppSearchReplaceCost;
        global $wgCppSearchGroups;

        //bail out if group is not within list of approved groups
        if (array_key_exists($group, $wgCppSearchGroups) == false) {
            $result_set = new CppSearchResultSet($query, array());
            return $result_set;
        }

        $data = self::get_data($group);

        //bail out if there's no data
        if (count($data->words) == 0) {
            $result_set = new CppSearchResultSet($query, array());
            return $result_set;
        }

        //split the query into words
        $qwords = array();
        self::split_words($query, $qwords, $dummy);

        //limit the number of words
        $qwords = array_slice($qwords, 0, $wgCppSearchQueryWordLimit);

        // short circuit if the query is empty
        if (count($qwords) == 0) {
            $result_set = new CppSearchResultSet($query, array());
            return $result_set;
        }

        /*  Find all words that match each of the word of the query (qword). Put
            all such words, matching costs and corresponding entries into an
            array

            array [
                {qword} => array [ matched_word ],
                ...
            ]
        */
        $matches = array();

        foreach ($qwords as $qw) {
            $matched_words = array();

            if (isset($matches[$qw])) {
                //already processed
                continue;
            }

            foreach ($data->words as $w => &$id_array) {
                $cost = levenshtein($qw, $w, $wgCppSearchInsertCost,
                                    $wgCppSearchDeleteCost,
                                    $wgCppSearchReplaceCost);
                if ($cost <= $wgCppSearchMaxResultCost) {
                    $matched_words[] = new CppSearchMatchedWord($w, $cost, $id_array);
                }
            }

            foreach ($data->words_split as $w => &$id_array) {
                $cost = levenshtein($qw, $w, $wgCppSearchInsertCost,
                                    $wgCppSearchDeleteCost,
                                    $wgCppSearchReplaceCost);
                $cost += $wgCppSearchSplitWordCost;
                if ($cost <= $wgCppSearchMaxResultCost) {
                    $matched_words[] = new CppSearchMatchedWord($w, $cost, $id_array);
                }
            }


            if (count($matched_words) == 0) {
                //no results for a word in query => no match
                $result_set = new CppSearchResultSet($query, array());
                return $result_set;
            }

            $matches[$qw] = $matched_words;
        }

        /*  Analyze the entries corresponding to matched words. We want to find
            a set of entries, for which each qword has at least one matching
            wond in the entry
        */

        // sort the match array to start from the words with the lowest number
        // of entries
        uasort($matches, '_cmp_match');

        //  Find a set of entry ids that have matches for all qwords
        // eid = array [ {id of entry} => cost ]

        $eid_map = array();
        $first_qword = true;

        foreach ($matches as $matched_words) {

            // curr_eid_map - matched eids for the current qword
            // array [ {id of entry} => cost
            $curr_eid_map = array();

            foreach ($matched_words as $mt) {
                foreach ($mt->ids as $eid) {
                    $cost = $mt->cost;
                    if (isset($curr_eid_map[$eid])) {
                        $cost = min($cost, $curr_eid_map[$eid]);
                    }
                    $curr_eid_map[$eid] = $cost;
                }
            }

            // Compute intersections of eids only for the second and subsequent
            // qwords
            if ($first_qword) {
                $first_qword = false;
                $eid_map = $curr_eid_map;
                continue;
            }

            // Compute the intersection of previously matched eids and eids that
            // have been matched for the current qword
            $intersected_eids = array_intersect(array_keys($eid_map),
                                                array_keys($curr_eid_map));

            $new_eid_map = array();
            foreach ($intersected_eids as $eid) {
                $cost = $eid_map[$eid] + $curr_eid_map[$eid];
                //only add if the cost is acceptable
                if ($cost <= $wgCppSearchMaxResultCost) {
                    $new_eid_map[$eid] = $cost;
                }
            }

            $eid_map = $new_eid_map;
        }

        // Sort $eid_map to have the best results at the beginning
        asort($eid_map);

        // Strip extra results
        $eid_map = array_slice($eid_map, 0, $wgCppSearchMaxResults*3, true);

        // Pull additional information
        $res = array();
        foreach ($eid_map as $eid => $cost) {
            $res[] = new CppSearchInternalSearchResult($cost, $eid,
                                                       $data->id_map[$eid]->full_key,
                                                       $data->id_map[$eid]->url);
        }

        // Sort the best results within each cost bucket
        // Prefer results with lower cost and shorter key
        usort($res, '_cmp_res');

        // Remove all results that contain a previous result. This fixes the
        // problem of showing all member and related functions even when they
        // have not been searched for
        for ($i = 0; $i < count($res); $i++) {
            for ($i2 = $i + 1; $i2 < count($res); $i2++) {
                if (strpos($res[$i2]->key, $res[$i]->key) !== false) {
                    unset($res[$i2]);
                    $res = array_values($res);
                    $i2--;
                }
            }
        }

        // Strip extra results
        $res = array_slice($res, 0, $wgCppSearchMaxResults);

        $result_set = new CppSearchResultSet($query, $res);
        return $result_set;
    }

    ///Private constructor

    function CppSearchResultSet($query, $res)
    {
        $this->query_ = $query;
        $this->res_ = $res;
        $this->pos_ = 0;
    }

    function numRows()
    {
        return count($this->res_);
    }

    function hasResults()
    {
        return count($this->res_) > 0;
    }

    //Returns next CppSearchResult
    function next()
    {
        if ($this->pos_ >= count($this->res_)) return false;

        $key = $this->res_[$this->pos_]->key;
        $url = $this->res_[$this->pos_]->url;
        $this->pos_++;
        return new CppSearchResult($key, $url);
    }
}

class CppSearchResult extends SearchResult {

    function CppSearchResult($key, $url)
    {
        //$this->mTitle = Title::newFromText($url);
        $this->mTitle = Title::makeTitle(NS_MAIN, $url);
        $this->mRevision = Revision::newFromTitle( $this->mTitle );
        $this->mHighlightTitle = $key;
    }

    function getTitle()
    {
        return $this->mTitle;
    }

    function getTitleSnippet($terms)
    {
        if (is_null($this->mHighlightTitle)) return '';
        return htmlspecialchars($this->mHighlightTitle);
    }

    function getScore()
    {
        return null;
    }

    function getTextSnippet($terms)
    {
        return '';
    }


}
