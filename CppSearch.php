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
    'descriptionmsg' => 'C/C++ keyword search extension',
//  'url'            => '',
);


$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['CppSearchEngine'] = $dir . 'CppSearchEngine.php';
$wgAutoloadClasses['CppSearchResult'] = $dir . 'CppSearchEngine.php';
$wgAutoloadClasses['CppSearchResultSet'] = $dir . 'CppSearchEngine.php';
$wgAutoloadClasses['CppSpecialSearch'] = $dir . 'CppSpecialSearch.php';

$wgSpecialPages['Search'] = 'CppSpecialSearch';

$wgExtensionMessagesFiles['CppSearch'] = $dir . 'CppSearch.i18n.php';

//Default settings

// maximum number of results to return
$wgCppSearchMaxResults = 100;

// if a result doesn't match identically, return it only if its 'cost' is not
// higher than this value
$wgCppSearchMaxResultCost = 4;

// the '_' is also considered a word separator. This value specified the cost
// added to the results acquired this way. E.g. 'unordered_set', when the query
// asks only for 'set'
$wgCppSearchSplitWordCost = 2;

// inexact match. Cost of each inserted symbol
$wgCppSearchInsertCost = 3;
// inexact match. Cost of each deleted symbol
$wgCppSearchDeleteCost = 3;
// inexact match. Cost of each replaced symbol
$wgCppSearchReplaceCost = 2;

// limit the numbor of words in the query to this value
$wgCppSearchQueryWordLimit = 5;

// the search files are loaded cached this number of seconds
$wgCppSearchCacheExpiry = 7200;

$wgCppSearchGroups = array ( 'cpp' );

// offer external search engines to the user
$wgCppSearchExternalEngines = array(
    'Google' => 'https://www.google.com/search?q=$1+site:en.cppreference.com',
    'Bing' => 'http://www.bing.com/search?q=$1+site:en.cppreference.com',
    'DuckDuckGo' => 'https://duckduckgo.com/html/?q=$1+site:en.cppreference.com'
    );
