<?php
/*
    Copyright 2011 p12 <tir5c3@yahoo.co.uk>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

//
// $res_num - number of results to supply
// $max_cost_per_word - maximum cost per word to return the results for

$wgExtensionCredits['other'][] = array(
    'path'           => __FILE__,
    'name'           => 'SimpleSearch',
    'author'         => 'p12',
    'descriptionmsg' => 'Simple keyword search extension',
//  'url'            => '',
);


$wgAutoloadClasses['SimpleSearch'] = __FILE__;
$wgAutoloadClasses['SimpleResult'] = __FILE__;
$wgAutoloadClasses['SimpleSearchResultSet'] = __FILE__;

//Default settings
$wgSimpleSearchMaxResults = 100;
$wgSimpleSearchMaxResultCost = 4;
$wgSimpleSearchSplitWordCost = 2;
$wgSimpleSearchInsertCost = 3;
$wgSimpleSearchDeleteCost = 3;
$wgSimpleSearchReplaceCost = 2;
$wgSimpleSearchQueryWordLimit = 5;
$wgSimpleSearchCacheExpiry = 7200;

class SimpleSearch extends SearchEngine {

    function searchText( $term )
    {
        return SimpleSearchResultSet::new_from_query($term, $this->limit, $this->offset);
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

/**
 * @ingroup Search
 */
class SimpleSearchResultSet extends SearchResultSet {

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

    /**
        Returns parsed keyword data.

        The format of parsed data:

        'URLS' : map ( id -> url )

            Maps the id of each entry to URL representing that entry

        'KEYS' : map ( id -> full key )

            Maps the id of each entry to full key name representing that entry

        'WORDS' : map ( word -> array of ids )

            Maps words to a list of entries containing entries that match the word

        'WORDS_SPLIT' : map ( word -> array of ids )

            Maps words to a list of entries containing entries that match the
            word if '_' is also considered a word separator.

        'NUM_ID' : int

            The number of entries
    */
    static function get_data()
    {
        $data = false;

        $cache = wfGetMainCache();
        $T_DATA = 'SimpleSearch_data';
        $T_TIME = 'SimpleSearch_time';

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
            $data = array();
            $id = 0;
            $string = wfMsgGetKey('simple-search-list', true, false, false);

            foreach (preg_split("/(\r?\n)/", $string) as $line) {

                $words = explode('=>', $line);
                $words = array_map('trim', $words);

                $key = $words[0];
                $url = $words[1];

                if (isset($key) && isset($url) && $key != '' && $url != '') {
                    //set the data
                    $data['KEYS'][$id] = $key;
                    $data['URLS'][$id] = $url;

                    //split the keywords

                    $key_words = array();
                    $split_words = array();

                    self::split_words($key, $key_words, $split_words);

                    //Map all resulting words to the source entries
                    foreach ($key_words as $w) {
                        if ($w == '') continue;
                        $data['WORDS'][$w][] = $id;
                    }
                    //Map all resulting split words to the source entries
                    foreach ($split_words as $w) {
                        if ($w == '') continue;
                        $data['WORDS_SPLIT'][$w][] = $id;
                    }

                    $id++;
                }
            }
            $data['NUM_ID'] = $id;

            //update cache
            global $wgSimpleSearchCacheExpiry;

            $curr_time = gmdate('YmdHis', time());
            $cache->set($T_DATA, $data, $wgSimpleSearchCacheExpiry);
            $cache->set($T_TIME, $curr_time, $wgSimpleSearchCacheExpiry);
        }
        return $data;
    }

    static function new_from_query( $query, $limit = 20, $offset = 0 )
    {
        //check the cache for optimized keyword list
        global $wgSimpleSearchQueryWordLimit;
        global $wgSimpleSearchMaxResultCost;
        global $wgSimpleSearchMaxResults;
        global $wgSimpleSearchSplitWordCost;
        global $wgSimpleSearchInsertCost;
        global $wgSimpleSearchDeleteCost;
        global $wgSimpleSearchReplaceCost;

        $data = self::get_data();

        //split the query into words
        $qwords = array();
        self::split_words($query, $qwords, $dummy);

        //limit the number of words
        while (count($qwords) > $wgSimpleSearchQueryWordLimit) {
            array_pop($qwords);
        }

        //create and zero the cost table
        $key_cost = array();
        $key_cost_curr = array();
        for ($id = 0; $id < $data['NUM_ID']; $id++) {
            $key_cost[$id] = 0;
        }

        //add costs to the keywords not similar to the words compared
        $qi = 0;
        foreach ($qwords as $qw) {
            if ($qw == '') continue;
            $qw = trim($qw);

            //zero the keyword cost table for the current word
            for ($id = 0; $id < $data['NUM_ID']; $id++) {
                $key_cost_curr[$id] = $wgSimpleSearchMaxResultCost*2;
            }

            //compute the costs for each complete keyword
            foreach ($data['WORDS'] as $w => $id_array) {
                $cost = levenshtein($qw, $w, $wgSimpleSearchInsertCost,
                                    $wgSimpleSearchDeleteCost,
                                    $wgSimpleSearchReplaceCost);
                foreach ($id_array as $id) {
                    $key_cost_curr[$id] = min($key_cost_curr[$id], $cost);
                }
            }

            //compute the costs for each split keyword
            foreach ($data['WORDS_SPLIT'] as $w => $id_array) {
                $cost = levenshtein($qw, $w, $wgSimpleSearchInsertCost,
                                    $wgSimpleSearchDeleteCost,
                                    $wgSimpleSearchReplaceCost) + $wgSimpleSearchSplitWordCost;
                foreach ($id_array as $id) {
                    $key_cost_curr[$id] = min($key_cost_curr[$id], $cost);
                }
            }

            //update the total cost table
            for ($id = 0; $id < $data['NUM_ID']; $id++) {
                $key_cost[$id] += $key_cost_curr[$id];
            }

            $qi++;
        }

        asort($key_cost);

        $res = array();
        $i = 0;
        //select the best results
        foreach($key_cost as $id => $cost) {
            if ($i >= $wgSimpleSearchMaxResults) break;
            if ($cost > $wgSimpleSearchMaxResultCost) break;

            $res[] = array(
                'COST' => $cost,
                'ID' => $id,
                'KEY' => $data['KEYS'][$id],
                'URL' => $data['URLS'][$id]
                );
            $i++;
        }


        //sort the best results within each cost bucket
        //prefer results with lower cost and shorter key
        function cmp_res($lhs, $rhs)
        {
            $res = $lhs['COST'] - $rhs['COST'];
            if ($res != 0) return $res;

            return strlen($lhs['KEY']) - strlen($rhs['KEY']);
        }

        usort($res, 'cmp_res');

        $result_set = new SimpleSearchResultSet($query, $res);
        return $result_set;
    }

    ///Private constructor

    function SimpleSearchResultSet($query, $res)
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

    //Returns next SimpleSearchResult
    function next()
    {
        if ($this->pos_ >= count($this->res_)) return false;

        $key = $this->res_[$this->pos_]['KEY'];
        $url = $this->res_[$this->pos_]['URL'];
        $this->pos_++;
        return new SimpleSearchResult($key, $url);
    }
}

class SimpleSearchResult extends SearchResult {

    function SimpleSearchResult($key, $url)
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
        if(is_null($this->mHighlightTitle)) return '';
        return $this->mHighlightTitle;
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
