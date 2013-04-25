<?php
/*
    Copyright 2012 p12 <tir5c3@yahoo.co.uk>

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

$wgExtensionCredits['other'][] = array(
    'path'           => __FILE__,
    'name'           => 'CppSearch',
    'author'         => 'p12',
    'descriptionmsg' => 'cppsearch_desc',
    'url'            => 'https://github.com/p12tic/CppSearch',
    'version'        => '1.0',
);


$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['CppSearchEngine'] = $dir . 'CppSearchEngine.php';
$wgAutoloadClasses['CppSearchResult'] = $dir . 'CppSearchEngine.php';
$wgAutoloadClasses['CppSearchResultSet'] = $dir . 'CppSearchEngine.php';
$wgAutoloadClasses['CppSpecialSearch'] = $dir . 'CppSpecialSearch.php';

$wgSpecialPages['Search'] = 'CppSpecialSearch';

$wgExtensionMessagesFiles['CppSearch'] = $dir . 'CppSearch.i18n.php';

$wgResourceModules['ext.CppSearch'] = array(
    'styles' => 'CppSearch.css',
    'localBasePath' => dirname(__FILE__),
    'remoteExtPath' => 'CppSearch'
);

//Default settings

// Maximum number of results to return
$wgCppSearchMaxResults = 100;

// If a result doesn't match identically, return it only if its 'cost' is not
// higher than this value
$wgCppSearchMaxResultCost = 4;

// The '_' is also considered a word separator. This value specified the cost
// added to the results acquired this way. E.g. 'unordered_set', when the query
// asks only for 'set'
$wgCppSearchSplitWordCost = 2;

// Inexact match. Cost of each inserted symbol
$wgCppSearchInsertCost = 3;
// Inexact match. Cost of each deleted symbol
$wgCppSearchDeleteCost = 3;
// Inexact match. Cost of each replaced symbol
$wgCppSearchReplaceCost = 2;

// Limit the numbor of words in the query to this value
$wgCppSearchQueryWordLimit = 5;

// The search files are loaded cached this number of seconds
$wgCppSearchCacheExpiry = 7200;

// Whether to directly redirect in case only one viable search result exists
$wgCppSearchDoRedirect = true;

// The engine can analyze several several search indexes. This setting defines
// the locations of the indexes and their human readable names. The value of the
// setting should be an associative array. Each key-value pair defines one
// index. The index is fetched from MediaWiki:cpp-search-list-$1 where $1 is the
// key. The value defines human-readable name of the index
$wgCppSearchGroups = array('cpp' => 'C++', 'c' => 'C');

// Offer external search engines to the user. The value of this setting should
// be an associative array. The keys define names of the search engine that will
// be shown in the search results, the values specify the search url. The search
// url should contain '$1' which defines where to insert the search query.
// If this array is empty, no external search engines are offered.
$wgCppSearchExternalEngines = array(
    'Google' => 'https://www.google.com/search?q=$1+site:en.cppreference.com',
    'Bing' => 'http://www.bing.com/search?q=$1+site:en.cppreference.com',
    'DuckDuckGo' => 'https://duckduckgo.com/html/?q=$1+site:en.cppreference.com'
    );
